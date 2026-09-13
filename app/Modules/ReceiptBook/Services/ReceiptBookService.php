<?php

declare(strict_types=1);

namespace App\Modules\ReceiptBook\Services;

use App\Models\User;
use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
use App\Modules\ReceiptBook\Models\ReceiptBookEntry;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?User $actor = null): ReceiptBookEntry
    {
        $user = $this->resolveActor($actor);

        return DB::transaction(function () use ($data, $user): ReceiptBookEntry {
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
