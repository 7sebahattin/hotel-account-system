<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Services;

use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
use App\Modules\Ledger\Models\LedgerEntry;
use App\Modules\Ledger\Support\CurrencySummary;
use App\Modules\Ledger\Support\Statement;
use App\Modules\Ledger\Support\StatementCriteria;
use App\Modules\Ledger\Support\StatementRow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Cari ekstre üretimi (CLAUDE.md §8).
 *
 * Ledger tek doğruluk kaynağıdır: burada hiçbir tutar saklanmaz, hepsi
 * `ledger_entries` üzerinden hesaplanır. Tüm aritmetik `Core\Support\Money`
 * ile tamsayı kuruş üzerindedir — float kullanılmaz.
 *
 * Bu servis Companies veya Payments modüllerini TANIMAZ; firma kimliğini
 * `int $companyId` olarak alır (CLAUDE.md §4.3/3).
 */
class LedgerStatementService
{
    /**
     * Firmanın ekstresi.
     *
     * Yürüyen bakiye SQL'de değil PHP'de, kronolojik sırada ve para birimi
     * bazında hesaplanır; her satır yalnızca KENDİ para biriminin bakiyesini
     * taşır (CLAUDE.md §6/L5).
     */
    public function forCompany(int $companyId, StatementCriteria $criteria): Statement
    {
        $carryForwards = $this->carryForwards($companyId, $criteria);

        // Bakiye evreni: tarih aralığı + para birimi. Tür/arama filtreleri
        // bilerek UYGULANMAZ; yürüyen bakiye tüm hareketler üzerinden yürür.
        $entries = $this->scopedQuery($companyId, $criteria)
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get();

        $running = $carryForwards;
        $debits = [];
        $credits = [];
        $rows = [];

        foreach ($entries as $entry) {
            $code = $entry->currency->value;

            $running[$code] = ($running[$code] ?? Money::zero())->add($entry->signedAmount());
            $debits[$code] = ($debits[$code] ?? Money::zero())->add($entry->debitMoney());
            $credits[$code] = ($credits[$code] ?? Money::zero())->add($entry->creditMoney());

            $rows[] = new StatementRow($entry, $running[$code]);
        }

        $summaries = $this->summaries($carryForwards, $debits, $credits);

        // Gösterim filtreleri yürüyen bakiye hesaplandıktan SONRA uygulanır.
        $rows = $this->applyDisplayFilters($rows, $criteria);

        if ($criteria->newestFirst) {
            $rows = array_reverse($rows);
        }

        return new Statement(array_values($rows), $summaries);
    }

    /**
     * Birden çok firmanın para birimi bazında bakiyeleri — TEK sorguda.
     *
     * Firma bakiye listesi ekranı için; her firma için ayrı sorgu atılmaz.
     *
     * @param  array<int, int>  $companyIds
     * @return array<int, array<string, Money>>
     */
    public function balancesForCompanies(array $companyIds): array
    {
        if ($companyIds === []) {
            return [];
        }

        // DB::table kullanılır: Eloquent cast'leri toplama sorgusunda `currency`
        // alanını enum'a çevirir ve dizi anahtarı olarak kullanılamaz hâle getirir.
        $rows = DB::table('ledger_entries')
            ->selectRaw('company_id, currency')
            ->selectRaw('CAST(COALESCE(SUM(debit), 0) AS CHAR) as total_debit')
            ->selectRaw('CAST(COALESCE(SUM(credit), 0) AS CHAR) as total_credit')
            ->whereIn('company_id', $companyIds)
            ->groupBy('company_id', 'currency')
            ->orderBy('currency')
            ->get();

        $balances = [];

        foreach ($rows as $row) {
            $balances[(int) $row->company_id][(string) $row->currency] =
                Money::fromDecimal((string) $row->total_debit)
                    ->subtract(Money::fromDecimal((string) $row->total_credit));
        }

        return $balances;
    }

    /**
     * Firmaların son hareket tarihleri — TEK sorguda.
     *
     * @param  array<int, int>  $companyIds
     * @return array<int, string>
     */
    public function lastEntryDates(array $companyIds): array
    {
        if ($companyIds === []) {
            return [];
        }

        return DB::table('ledger_entries')
            ->selectRaw('company_id, MAX(entry_date) as last_date')
            ->whereIn('company_id', $companyIds)
            ->groupBy('company_id')
            ->pluck('last_date', 'company_id')
            ->map(fn ($date) => (string) $date)
            ->all();
    }

    // ------------------------------------------------------------------ iç

    /**
     * Tarih aralığından ÖNCEKİ net bakiye (devreden), para birimi bazında.
     *
     * Tarih filtresi yoksa devreden yoktur: ekstre defterin başından başlar.
     *
     * @return array<string, Money>
     */
    private function carryForwards(int $companyId, StatementCriteria $criteria): array
    {
        if ($criteria->dateFrom === null) {
            return [];
        }

        $query = DB::table('ledger_entries')
            ->selectRaw('currency')
            ->selectRaw('CAST(COALESCE(SUM(debit), 0) AS CHAR) as total_debit')
            ->selectRaw('CAST(COALESCE(SUM(credit), 0) AS CHAR) as total_credit')
            ->where('company_id', $companyId)
            ->whereDate('entry_date', '<', $criteria->dateFrom)
            ->groupBy('currency');

        if ($criteria->currency !== null) {
            $query->where('currency', $criteria->currency->value);
        }

        $carried = [];

        foreach ($query->get() as $row) {
            $balance = Money::fromDecimal((string) $row->total_debit)
                ->subtract(Money::fromDecimal((string) $row->total_credit));

            if (! $balance->isZero()) {
                $carried[(string) $row->currency] = $balance;
            }
        }

        return $carried;
    }

    /**
     * Bakiye evrenini belirleyen sorgu: yalnızca tarih ve para birimi.
     *
     * @return Builder<LedgerEntry>
     */
    private function scopedQuery(int $companyId, StatementCriteria $criteria): Builder
    {
        $query = LedgerEntry::query()
            ->with('creator')
            ->where('company_id', $companyId);

        if ($criteria->dateFrom !== null) {
            $query->whereDate('entry_date', '>=', $criteria->dateFrom);
        }

        if ($criteria->dateTo !== null) {
            $query->whereDate('entry_date', '<=', $criteria->dateTo);
        }

        if ($criteria->currency !== null) {
            $query->where('currency', $criteria->currency->value);
        }

        return $query;
    }

    /**
     * @param  array<int, StatementRow>  $rows
     * @return array<int, StatementRow>
     */
    private function applyDisplayFilters(array $rows, StatementCriteria $criteria): array
    {
        if ($criteria->type !== null) {
            $rows = array_filter($rows, fn (StatementRow $row) => $row->type() === $criteria->type);
        }

        if ($criteria->search !== null && $criteria->search !== '') {
            $needle = mb_strtolower($criteria->search, 'UTF-8');

            $rows = array_filter(
                $rows,
                fn (StatementRow $row) => str_contains(
                    mb_strtolower($row->entry->description, 'UTF-8'),
                    $needle,
                ),
            );
        }

        return $rows;
    }

    /**
     * @param  array<string, Money>  $carryForwards
     * @param  array<string, Money>  $debits
     * @param  array<string, Money>  $credits
     * @return array<string, CurrencySummary>
     */
    private function summaries(array $carryForwards, array $debits, array $credits): array
    {
        $codes = array_unique([...array_keys($carryForwards), ...array_keys($debits), ...array_keys($credits)]);
        sort($codes);

        $summaries = [];

        foreach ($codes as $code) {
            $currency = Currency::tryFrom($code);

            if ($currency === null) {
                continue;
            }

            $carry = $carryForwards[$code] ?? Money::zero();
            $debit = $debits[$code] ?? Money::zero();
            $credit = $credits[$code] ?? Money::zero();

            $summaries[$code] = new CurrencySummary(
                currency: $currency,
                carryForward: $carry,
                totalDebit: $debit,
                totalCredit: $credit,
                closing: $carry->add($debit)->subtract($credit),
            );
        }

        return $summaries;
    }
}
