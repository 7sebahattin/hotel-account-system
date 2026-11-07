<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Services;

use App\Models\User;
use App\Modules\AuditLog\Enums\AuditEvent;
use App\Modules\AuditLog\Services\AuditLogger;
use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
use App\Modules\Ledger\Exceptions\CannotReverseEntry;
use App\Modules\Ledger\Exceptions\InvalidLedgerEntry;
use App\Modules\Ledger\Models\LedgerEntry;
use App\Modules\Ledger\Support\LedgerVisibility;
use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Cari defterine yazan TEK kapı (CLAUDE.md §6/L9).
 *
 * Hiçbir controller, görünüm veya başka modül LedgerEntry::create() çağırmaz.
 *
 * Bu servis firma kimliğini `int $companyId` olarak alır; Companies modülünün
 * modeline bağımlı değildir (CLAUDE.md §4.3/3: Ledger hiçbir modülü tanımaz).
 * source_type/source_id yalnızca izleme içindir; bakiye her zaman defterden
 * hesaplanır, kaynak kayıttan değil.
 */
class LedgerService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /** Firma borçlanır (bakiyeyi artırır). */
    public function debit(
        int $companyId,
        Money $amount,
        Currency $currency,
        DateTimeInterface|string $entryDate,
        string $description,
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?User $actor = null,
    ): LedgerEntry {
        return $this->post($companyId, $amount, $currency, $entryDate, $description, true, $sourceType, $sourceId, $actor);
    }

    /** Firmanın borcu azalır (tahsilat). */
    public function credit(
        int $companyId,
        Money $amount,
        Currency $currency,
        DateTimeInterface|string $entryDate,
        string $description,
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?User $actor = null,
    ): LedgerEntry {
        return $this->post($companyId, $amount, $currency, $entryDate, $description, false, $sourceType, $sourceId, $actor);
    }

    /**
     * Bir hareketin tam tersini üretir.
     *
     * Ters kayıt orijinalin aynı tarihini, tutarını, para birimini ve kaynağını
     * taşır; yalnızca borç/alacak yönü değişir. Böylece iki kayıt her tarih
     * aralığında birbirini götürür.
     *
     * @throws CannotReverseEntry
     */
    public function reverse(LedgerEntry $entry, ?string $description = null, ?User $actor = null): LedgerEntry
    {
        if ($entry->is_reversal) {
            throw CannotReverseEntry::isReversal($entry->id);
        }

        $user = $this->resolveActor($actor);

        return DB::transaction(function () use ($entry, $description, $user): LedgerEntry {
            // Yarış durumunda ikinci ters kaydı veritabanındaki tekil kısıt engeller;
            // buradaki kontrol kullanıcıya anlaşılır hata vermek içindir.
            if ($this->isReversed($entry)) {
                throw CannotReverseEntry::alreadyReversed($entry->id);
            }

            $amount = $entry->amount();

            try {
                $reversal = $this->insert(
                    companyId: $entry->company_id,
                    debit: $entry->isDebit() ? Money::zero() : $amount,
                    credit: $entry->isDebit() ? $amount : Money::zero(),
                    currency: $entry->currency,
                    entryDate: $entry->entry_date,
                    description: $description ?? $this->reversalDescription($entry),
                    sourceType: $entry->source_type,
                    sourceId: $entry->source_id,
                    actor: $user,
                    isReversal: true,
                    reversesEntryId: $entry->id,
                );
            } catch (UniqueConstraintViolationException) {
                throw CannotReverseEntry::alreadyReversed($entry->id);
            }

            $this->auditLogger->log(
                event: AuditEvent::Reversed,
                subject: $entry,
                oldValues: $this->snapshot($entry),
                newValues: $this->snapshot($reversal) + ['reversal_entry_id' => $reversal->id],
                user: $user,
            );

            return $reversal;
        });
    }

    /** Bakiye = SUM(debit) - SUM(credit). Pozitif değer firmanın borcudur. */
    public function balanceForCompany(int $companyId, Currency $currency): Money
    {
        $totals = $this->sumQuery()
            ->where('company_id', $companyId)
            ->where('currency', $currency->value)
            ->first();

        return $this->toMoney($totals?->total_debit)->subtract($this->toMoney($totals?->total_credit));
    }

    /**
     * Para birimi bazında bakiyeler. Farklı para birimleri ASLA toplanmaz
     * (CLAUDE.md §6/L5); yalnızca hareketi olan para birimleri döner.
     *
     * @return array<string, Money>
     */
    public function balancesForCompanyByCurrency(int $companyId): array
    {
        $rows = $this->sumQuery()
            ->addSelect('currency')
            ->where('company_id', $companyId)
            ->groupBy('currency')
            ->orderBy('currency')
            ->get();

        $balances = [];

        foreach ($rows as $row) {
            $balances[$row->currency] = $this->toMoney($row->total_debit)
                ->subtract($this->toMoney($row->total_credit));
        }

        return $balances;
    }

    /**
     * Bir kaynak kaydın (konaklama, tahsilat) hâlâ geçerli olan hareketleri.
     *
     * "Geçerli" = kendisi ters kayıt olmayan VE ters kaydı alınmamış hareket.
     * Değişmez: bir konaklamanın her an tam olarak bir geçerli borç hareketi olur.
     *
     * @return Collection<int, LedgerEntry>
     */
    public function activeEntriesForSource(string $sourceType, int $sourceId): Collection
    {
        return LedgerEntry::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->where('is_reversal', false)
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('ledger_entries as reversals')
                    ->whereColumn('reversals.reverses_entry_id', 'ledger_entries.id');
            })
            ->orderBy('id')
            ->get();
    }

    /** Kaynak kaydın tek geçerli hareketi; yoksa null. */
    public function activeEntryForSource(string $sourceType, int $sourceId): ?LedgerEntry
    {
        return $this->activeEntriesForSource($sourceType, $sourceId)->first();
    }

    /**
     * Bir kaynağa (konaklama/tahsilat) ait TÜM defter satırı kimlikleri —
     * ters kayıtlar dahil. Yalnızca çöp kutusundan kalıcı silme hazırlığı içindir.
     *
     * @return array<int, int>
     */
    public function entryIdsForSource(string $sourceType, int $sourceId): array
    {
        return LedgerEntry::withoutGlobalScope('visible')
            ->where('source_type', $sourceType)->where('source_id', $sourceId)
            ->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }

    /**
     * Verilen satırların her para biriminde NET SIFIR olup olmadığı. Kalıcı silme
     * yalnızca borcu zaten ters kayıtla sıfırlanmış kaynaklarda bakiyeyi korur.
     *
     * @param  array<int, int>  $ids
     */
    public function entriesNetToZero(array $ids): bool
    {
        if ($ids === []) {
            return true;
        }

        $rows = DB::table('ledger_entries')->whereIn('id', $ids)->groupBy('company_id', 'currency')
            ->selectRaw('company_id, currency, SUM(debit) as total_debit, SUM(credit) as total_credit')->get();

        foreach ($rows as $row) {
            if (Money::fromDecimal((string) $row->total_debit)->minorUnits !== Money::fromDecimal((string) $row->total_credit)->minorUnits) {
                return false;
            }
        }

        return true;
    }

    /**
     * Çöp kutusundan kalıcı silme: verilen defter satırlarını SİLER. Veritabanı
     * tetikleyicisi yalnızca `Core\Trash\FinancialPurgeGate` açıkken ve yalnızca
     * kapıya sayılan kimlikler için izin verir; kapı yoksa SQL reddedilir.
     * Ters kayıtlar (daha yüksek kimlik) önce silinir — kendine FK RESTRICT.
     *
     * @param  array<int, int>  $ids
     */
    public function purgeEntries(array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        return DB::table('ledger_entries')->whereIn('id', $ids)->orderByDesc('id')->delete();
    }

    public function hasEntriesForCompany(int $companyId): bool
    {
        // Çöptekiler dahil: firmaya bağlı fiziksel defter satırı varsa firma kalıcı silinemez.
        return LedgerEntry::withoutGlobalScope('visible')->where('company_id', $companyId)->exists();
    }

    public function isReversed(LedgerEntry $entry): bool
    {
        return LedgerEntry::query()->where('reverses_entry_id', $entry->id)->exists();
    }

    // ------------------------------------------------------------------ iç

    private function post(
        int $companyId,
        Money $amount,
        Currency $currency,
        DateTimeInterface|string $entryDate,
        string $description,
        bool $isDebit,
        ?string $sourceType,
        ?int $sourceId,
        ?User $actor,
    ): LedgerEntry {
        if (! $amount->isPositive()) {
            throw InvalidLedgerEntry::amountNotPositive();
        }

        $user = $this->resolveActor($actor);

        return DB::transaction(function () use (
            $companyId, $amount, $currency, $entryDate, $description, $isDebit, $sourceType, $sourceId, $user
        ): LedgerEntry {
            $entry = $this->insert(
                companyId: $companyId,
                debit: $isDebit ? $amount : Money::zero(),
                credit: $isDebit ? Money::zero() : $amount,
                currency: $currency,
                entryDate: $entryDate,
                description: $description,
                sourceType: $sourceType,
                sourceId: $sourceId,
                actor: $user,
            );

            $this->auditLogger->log(
                event: AuditEvent::Created,
                subject: $entry,
                newValues: $this->snapshot($entry),
                user: $user,
            );

            return $entry;
        });
    }

    private function insert(
        int $companyId,
        Money $debit,
        Money $credit,
        Currency $currency,
        DateTimeInterface|string $entryDate,
        string $description,
        ?string $sourceType,
        ?int $sourceId,
        User $actor,
        bool $isReversal = false,
        ?int $reversesEntryId = null,
    ): LedgerEntry {
        return LedgerEntry::query()->create([
            'company_id' => $companyId,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'entry_date' => Carbon::parse($entryDate)->toDateString(),
            'description' => mb_substr($description, 0, 255),
            'debit' => $debit->toDecimal(),
            'credit' => $credit->toDecimal(),
            'currency' => $currency->value,
            'is_reversal' => $isReversal,
            'reverses_entry_id' => $reversesEntryId,
            'created_by' => $actor->getKey(),
        ]);
    }

    /** @return Builder */
    private function sumQuery()
    {
        // CAST ... AS CHAR: toplamlar her zaman metin olarak döner, sürücü
        // kaynaklı kayan nokta dönüşümü olasılığı ortadan kalkar.
        return LedgerVisibility::apply(DB::table('ledger_entries'))->selectRaw(
            'CAST(COALESCE(SUM(debit), 0) AS CHAR) AS total_debit, '.
            'CAST(COALESCE(SUM(credit), 0) AS CHAR) AS total_credit'
        );
    }

    private function toMoney(mixed $value): Money
    {
        return Money::fromDecimal((string) ($value ?? '0'));
    }

    private function resolveActor(?User $actor): User
    {
        $user = $actor ?? (Auth::check() ? Auth::user() : null);

        if ($user === null) {
            throw InvalidLedgerEntry::missingActor();
        }

        return $user;
    }

    private function reversalDescription(LedgerEntry $entry): string
    {
        return '[İPTAL] '.$entry->description;
    }

    /**
     * Denetim kaydı için hareket özeti.
     *
     * Tutarlar maskelenmez ve kısaltılmaz — finansal denetim için görünür kalmalıdır.
     *
     * @return array<string, mixed>
     */
    private function snapshot(LedgerEntry $entry): array
    {
        return [
            'ledger_entry_id' => $entry->id,
            'company_id' => $entry->company_id,
            'entry_date' => $entry->entry_date->toDateString(),
            'description' => $entry->description,
            'debit' => $entry->debit,
            'credit' => $entry->credit,
            'currency' => $entry->currency->value,
            'source_type' => $entry->source_type,
            'source_id' => $entry->source_id,
            'is_reversal' => $entry->is_reversal,
        ];
    }
}
