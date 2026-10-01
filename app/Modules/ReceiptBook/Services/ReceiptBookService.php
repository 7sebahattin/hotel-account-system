<?php

declare(strict_types=1);

namespace App\Modules\ReceiptBook\Services;

use App\Models\User;
use App\Modules\AuditLog\Enums\AuditEvent;
use App\Modules\AuditLog\Services\AuditLogger;
use App\Modules\Companies\Services\CompanyService;
use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
use App\Modules\ReceiptBook\Models\ReceiptBookEntry;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Tahsilat Makbuzu Defteri'nin dışa açık tek kapısı (CLAUDE.md §4.3).
 *
 * KRİTİK (v2.1.1-C §4): bu servis `Ledger\Services\LedgerService`,
 * `Payments\Services\PaymentService`'i
 * HİÇ import ETMEZ ve ÇAĞIRMAZ. Bir makbuzun kaydedilmesi:
 *   - firma cari bakiyesini DEĞİŞTİRMEZ,
 *   - bir `Payment` kaydı OLUŞTURMAZ,
 *   - fiziksel kasaya bir `CashMovement` YAZMAZ.
 * Yalnızca dokümantasyon/yazdırma amaçlı bir satırdır.
 */
class ReceiptBookService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CompanyService $companies,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?User $actor = null): ReceiptBookEntry
    {
        $user = $this->resolveActor($actor);

        return DB::transaction(function () use ($data, $user): ReceiptBookEntry {
            if (isset($data['company_id'])) {
                $name = $this->companies->activeName((int) $data['company_id']);
                if ($name === null) {
                    throw ValidationException::withMessages(['company_id' => 'Geçerli, aktif bir cari seçin.']);
                }
                $data['customer_name'] = $name;
            }

            $entry = new ReceiptBookEntry;
            $entry->fill($this->fillable($data));
            $entry->created_by = $user->getKey();
            $entry->updated_by = $user->getKey();
            $this->applyExchangeRate($entry, $data);
            $entry->save();

            // Payment::receiptNumberFor() ile AYNI desen: id atandıktan hemen
            // sonra, create() akışının kendi tamamlanması olarak yazılır.
            $entry->receipt_no = ReceiptBookEntry::receiptNumberFor($entry->getKey(), $entry->receipt_date);
            $entry->saveQuietly();

            return $entry;
        });
    }

    /**
     * Makbuzu düzenler (yalnızca Admin, 27 Eylül 2026). Makbuz numarası
     * DEĞİŞMEZ — basılmış belgeyle bağı korunur. Eski/yeni değerler
     * `Auditable` ile denetim kaydına yazılır.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(ReceiptBookEntry $entry, array $data, ?User $actor = null): ReceiptBookEntry
    {
        $user = $this->resolveActor($actor);

        return DB::transaction(function () use ($entry, $data, $user): ReceiptBookEntry {
            $entry->fill($this->fillable($data));
            $entry->updated_by = $user->getKey();
            $this->applyExchangeRate($entry, $data);
            $entry->save();

            return $entry;
        });
    }

    /**
     * Makbuzu kalıcı olarak siler (yalnızca Admin, 27 Eylül 2026). Makbuz
     * muhasebe kaydı olmadığı için cari/kasa etkisi yoktur. Genel `Auditable`
     * silme kaydı eski değerleri taşımadığından olay burada AÇIKÇA, silinen
     * satırın tüm değerleriyle yazılır; kayıt denetim izinde okunabilir kalır.
     */
    public function delete(ReceiptBookEntry $entry): void
    {
        DB::transaction(function () use ($entry): void {
            $this->audit->log(
                AuditEvent::Deleted,
                $entry,
                oldValues: $entry->auditableValues($entry->getAttributes()),
            );

            $entry->deleteQuietly();
        });
    }

    public function paginate(int $perPage = 25): LengthAwarePaginator
    {
        return ReceiptBookEntry::query()
            ->with('creator')
            ->orderByDesc('receipt_date')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    // ------------------------------------------------------------------ iç

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function fillable(array $data): array
    {
        return array_intersect_key($data, array_flip([
            'receipt_date', 'customer_name', 'amount', 'currency', 'payment_method', 'description',
        ]));
    }

    private function applyExchangeRate(ReceiptBookEntry $entry, array $data): void
    {
        if ($entry->currency === Currency::TRY) {
            $entry->exchange_rate_to_try = null;
            $entry->try_equivalent = null;

            return;
        }

        if (! array_key_exists('exchange_rate_to_try', $data) || $data['exchange_rate_to_try'] === null) {
            return;
        }

        $rate = Money::normalizeInput((string) $data['exchange_rate_to_try']);
        $entry->exchange_rate_to_try = $rate;
        $entry->try_equivalent = $entry->amountMoney()->multiplyByRate($rate)->toDecimal();
    }

    private function resolveActor(?User $actor): User
    {
        $user = $actor ?? (Auth::check() ? Auth::user() : null);

        if ($user === null) {
            throw new \RuntimeException('Makbuz işlemi için işlemi yapan kullanıcı belirtilmelidir.');
        }

        return $user;
    }
}
