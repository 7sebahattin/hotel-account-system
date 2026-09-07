<?php

declare(strict_types=1);

namespace App\Modules\Stays\Services;

use App\Models\User;
use App\Modules\AuditLog\Enums\AuditEvent;
use App\Modules\AuditLog\Services\AuditLogger;
use App\Modules\Core\Support\Money;
use App\Modules\Ledger\Models\LedgerEntry;
use App\Modules\Ledger\Services\LedgerService;
use App\Modules\Stays\Enums\StayStatus;
use App\Modules\Stays\Exceptions\CannotUpdateCancelledStay;
use App\Modules\Stays\Exceptions\StayAlreadyCancelled;
use App\Modules\Stays\Models\Stay;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Konaklama modülünün dışa açık tek kapısı (CLAUDE.md §4.3).
 *
 * Her yazma işlemi konaklama kaydı ile cari hareketini AYNI transaction içinde
 * ele alır (CLAUDE.md §6/L6): biri yazılıp diğeri yazılamazsa ikisi de geri alınır.
 *
 * Değişmez: bir konaklamanın her an **tam olarak bir geçerli borç hareketi**
 * vardır (iptal edilmişse hiç yoktur). Düzeltmeler eski hareketi ters kaydeder
 * ve yenisini ekler; eski satır asla güncellenmez.
 */
class StayService
{
    /**
     * Değişmesi cari hareketini etkileyen alanlar.
     *
     * @var array<int, string>
     */
    public const FINANCIAL_FIELDS = ['company_id', 'check_in_date', 'check_out_date', 'amount', 'currency'];

    public function __construct(
        private readonly LedgerService $ledger,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?User $actor = null): Stay
    {
        $user = $this->resolveActor($actor);

        return DB::transaction(function () use ($data, $user): Stay {
            $stay = new Stay;
            $stay->fill($this->fillable($data));
            $stay->nights = $this->nightsFor($stay);
            $stay->status = StayStatus::Active;
            $stay->created_by = $user->getKey();
            $stay->updated_by = $user->getKey();
            $stay->save();

            $entry = $this->postDebit($stay, $user);

            $this->auditLogger->log(
                event: AuditEvent::Created,
                subject: $stay,
                newValues: $this->snapshot($stay) + ['ledger_entry_id' => $entry->id],
                user: $user,
            );

            return $stay;
        });
    }

    /**
     * Konaklamayı günceller.
     *
     * Finansal alanlardan biri değişirse akış şudur (CLAUDE.md §6/L7):
     *   1. mevcut cari hareketi ters kaydedilir
     *   2. konaklama güncellenir
     *   3. yeni borç hareketi oluşturulur
     * Üçü de aynı transaction içindedir.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws CannotUpdateCancelledStay
     */
    public function update(Stay $stay, array $data, ?User $actor = null): Stay
    {
        if ($stay->isCancelled()) {
            throw CannotUpdateCancelledStay::make($stay->getKey());
        }

        $user = $this->resolveActor($actor);

        return DB::transaction(function () use ($stay, $data, $user): Stay {
            $before = $this->snapshot($stay);
            $financialBefore = $this->financialSnapshot($stay);

            $candidate = clone $stay;
            $candidate->fill($this->fillable($data));
            $financialChanged = $this->financialSnapshot($candidate) !== $financialBefore;

            $reversal = null;

            if ($financialChanged) {
                $active = $this->ledger->activeEntryForSource(Stay::LEDGER_SOURCE_TYPE, $stay->getKey());

                if ($active !== null) {
                    $reversal = $this->ledger->reverse($active, actor: $user);
                }
            }

            $stay->fill($this->fillable($data));
            $stay->nights = $this->nightsFor($stay);
            $stay->updated_by = $user->getKey();
            $stay->save();

            $newEntry = $financialChanged ? $this->postDebit($stay, $user) : null;

            $after = $this->snapshot($stay);
            $changed = $this->changedFields($before, $after);

            if ($changed === [] && ! $financialChanged) {
                return $stay;
            }

            $context = [];

            if ($financialChanged) {
                // Denetim kaydı, düzeltme zincirinin tamamını taşır.
                $context = [
                    'financial_change' => true,
                    'reversed_ledger_entry_id' => $reversal?->reverses_entry_id,
                    'reversal_ledger_entry_id' => $reversal?->id,
                    'new_ledger_entry_id' => $newEntry?->id,
                ];
            }

            $this->auditLogger->log(
                event: AuditEvent::Updated,
                subject: $stay,
                oldValues: array_intersect_key($before, $changed),
                newValues: $changed + $context,
                user: $user,
            );

            return $stay;
        });
    }

    /**
     * Konaklamayı iptal eder ve cari hareketini ters kaydeder.
     *
     * İptal geri alınamaz (V1'de restore akışı yoktur).
     *
     * @throws StayAlreadyCancelled
     */
    public function cancel(Stay $stay, ?User $actor = null): Stay
    {
        if ($stay->isCancelled()) {
            throw StayAlreadyCancelled::make($stay->getKey());
        }

        $user = $this->resolveActor($actor);

        return DB::transaction(function () use ($stay, $user): Stay {
            $active = $this->ledger->activeEntryForSource(Stay::LEDGER_SOURCE_TYPE, $stay->getKey());
            $reversal = $active !== null ? $this->ledger->reverse($active, actor: $user) : null;

            $stay->status = StayStatus::Cancelled;
            $stay->updated_by = $user->getKey();
            $stay->save();

            $this->auditLogger->log(
                event: AuditEvent::Cancelled,
                subject: $stay,
                oldValues: ['status' => StayStatus::Active->value],
                newValues: [
                    'status' => StayStatus::Cancelled->value,
                    'reversed_ledger_entry_id' => $active?->id,
                    'reversal_ledger_entry_id' => $reversal?->id,
                ],
                user: $user,
            );

            return $stay;
        });
    }

    /**
     * Firmanın son konaklamaları — firma detay ekranı için.
     *
     * @return Collection<int, Stay>
     */
    public function recentForCompany(int $companyId, int $limit = 10): Collection
    {
        return Stay::query()
            ->where('company_id', $companyId)
            ->orderByDesc('check_in_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function countForCompany(int $companyId): int
    {
        return Stay::query()->where('company_id', $companyId)->count();
    }

    // ------------------------------------------------------------------ iç

    private function postDebit(Stay $stay, User $user): LedgerEntry
    {
        return $this->ledger->debit(
            companyId: $stay->company_id,
            amount: $stay->amountMoney(),
            currency: $stay->currency,
            entryDate: $stay->check_out_date,
            description: $stay->ledgerDescription(),
            sourceType: Stay::LEDGER_SOURCE_TYPE,
            sourceId: $stay->getKey(),
            actor: $user,
        );
    }

    /**
     * Yalnızca modelde doldurulabilir alanları geçirir; nights ve status
     * kullanıcı girdisinden ASLA alınmaz.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function fillable(array $data): array
    {
        return array_intersect_key($data, array_flip([
            'company_id', 'guest_name', 'room_no',
            'check_in_date', 'check_out_date', 'amount', 'currency', 'notes',
        ]));
    }

    private function nightsFor(Stay $stay): int
    {
        return Stay::calculateNights(
            Carbon::parse($stay->check_in_date),
            Carbon::parse($stay->check_out_date),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Stay $stay): array
    {
        return [
            'company_id' => (int) $stay->company_id,
            'guest_name' => $stay->guest_name,
            'room_no' => $stay->room_no,
            'check_in_date' => Carbon::parse($stay->check_in_date)->toDateString(),
            'check_out_date' => Carbon::parse($stay->check_out_date)->toDateString(),
            'nights' => (int) $stay->nights,
            'amount' => Money::fromDecimal($stay->amount)->toDecimal(),
            'currency' => $stay->currency->value,
            'notes' => $stay->notes,
            'status' => $stay->status->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function financialSnapshot(Stay $stay): array
    {
        return array_intersect_key($this->snapshot($stay), array_flip(self::FINANCIAL_FIELDS));
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, mixed>
     */
    private function changedFields(array $before, array $after): array
    {
        $changed = [];

        foreach ($after as $key => $value) {
            if (($before[$key] ?? null) !== $value) {
                $changed[$key] = $value;
            }
        }

        return $changed;
    }

    private function resolveActor(?User $actor): User
    {
        $user = $actor ?? (Auth::check() ? Auth::user() : null);

        if ($user === null) {
            throw new \RuntimeException('Konaklama işlemi için işlemi yapan kullanıcı belirtilmelidir.');
        }

        return $user;
    }
}
