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
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
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
     * Firmaların son hareket tarihleri — TEK sorguda. Para birimi verilirse
     * yalnızca o para birimindeki hareketler dikkate alınır.
     *
     * @param  array<int, int>  $companyIds
     * @return array<int, string>
     */
    public function lastEntryDates(array $companyIds, ?Currency $currency = null): array
    {
        if ($companyIds === []) {
            return [];
        }

        return DB::table('ledger_entries')
            ->selectRaw('company_id, MAX(entry_date) as last_date')
            ->whereIn('company_id', $companyIds)
            ->when($currency !== null, fn ($query) => $query->where('currency', $currency?->value))
            ->groupBy('company_id')
            ->pluck('last_date', 'company_id')
            ->map(fn ($date) => (string) $date)
            ->all();
    }

    /**
     * Tüm firmaların açık bakiyelerinin para birimi bazında özeti.
     *
     * "Açık alacak" ve "açık borç" firma bazında NET edildikten SONRA toplanır:
     * bir firmanın 100 borcu ile başka bir firmanın 100 alacağı birbirini
     * götürmez, ikisi ayrı satırdır. Bu yüzden alt sorgu firma+para birimi
     * kırılımında bakiyeyi bulur, dış sorgu yönüne göre ayırır.
     *
     * TEK sorgu; sonuç en fazla para birimi sayısı kadar satırdır (CLAUDE.md §6/L5:
     * para birimleri asla tek toplamda birleşmez).
     *
     * @return array<string, array{receivable: Money, payable: Money, net: Money}>
     */
    public function openBalanceTotalsByCurrency(): array
    {
        $inner = DB::table('ledger_entries')
            ->selectRaw('company_id, currency, (COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0)) as balance')
            ->groupBy('company_id', 'currency');

        $rows = DB::query()
            ->fromSub($inner, 'company_balances')
            ->selectRaw('currency')
            ->selectRaw('CAST(SUM(CASE WHEN balance > 0 THEN balance ELSE 0 END) AS CHAR) as receivable')
            ->selectRaw('CAST(SUM(CASE WHEN balance < 0 THEN -balance ELSE 0 END) AS CHAR) as payable')
            ->groupBy('currency')
            ->orderBy('currency')
            ->get();

        $totals = [];

        foreach ($rows as $row) {
            $receivable = Money::fromDecimal((string) $row->receivable);
            $payable = Money::fromDecimal((string) $row->payable);

            $totals[(string) $row->currency] = [
                'receivable' => $receivable,
                'payable' => $payable,
                'net' => $receivable->subtract($payable),
            ];
        }

        return $totals;
    }

    /**
     * Bir para biriminde en yüksek bakiyeli firmalar.
     *
     * Para birimi başına ayrı çağrılır (en fazla Currency::cases() kadar, yani
     * sabit sayıda sorgu) — firma başına sorgu YOKTUR. Yalnızca bize borçlu
     * olanlar listelenir; sıfır ve negatif bakiye "en yüksek alacak" değildir.
     *
     * @return array<int, array{company_id: int, balance: Money}>
     */
    public function topDebtorCompanies(Currency $currency, int $limit = 10): array
    {
        $rows = DB::table('ledger_entries')
            ->selectRaw('company_id')
            ->selectRaw('CAST((COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0)) AS CHAR) as balance')
            ->where('currency', $currency->value)
            ->groupBy('company_id')
            ->havingRaw('(COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0)) > 0')
            ->orderByRaw('(COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0)) DESC')
            ->limit($limit)
            ->get();

        return $rows
            ->map(fn ($row): array => [
                'company_id' => (int) $row->company_id,
                'balance' => Money::fromDecimal((string) $row->balance),
            ])
            ->all();
    }

    /**
     * Son cari hareketler — kaynak kayıt sorgusu YAPILMADAN.
     *
     * Firma adları Ledger'ın işi değildir (CLAUDE.md §4.3/3): çağıran taraf
     * `company_id` listesini Companies servisine verip adları toplu çözer.
     *
     * @return Collection<int, LedgerEntry>
     */
    public function recentEntries(int $limit = 10): Collection
    {
        return LedgerEntry::query()
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Tek para biriminde aylık net değişim ve aralık öncesi (devreden) net.
     *
     * Sistem genelidir (tüm hesaplar): bir ayın sonundaki net bakiye
     * `opening + o aya kadarki aylık değişimlerin toplamı`dır. TEK sorgu;
     * hareketsiz aylar `months` dizisinde YOKTUR.
     *
     * @return array{opening: Money, months: array<string, Money>}
     */
    public function monthlyNetChanges(Currency $currency, string $from, string $to): array
    {
        $rows = DB::table('ledger_entries')
            ->selectRaw("CASE WHEN entry_date < ? THEN 'opening' ELSE DATE_FORMAT(entry_date, '%Y-%m') END as bucket", [$from])
            ->selectRaw('CAST(COALESCE(SUM(debit), 0) AS CHAR) as total_debit')
            ->selectRaw('CAST(COALESCE(SUM(credit), 0) AS CHAR) as total_credit')
            ->where('currency', $currency->value)
            ->whereDate('entry_date', '<=', $to)
            ->groupBy('bucket')
            ->get();

        $opening = Money::zero();
        $months = [];

        foreach ($rows as $row) {
            $net = Money::fromDecimal((string) $row->total_debit)
                ->subtract(Money::fromDecimal((string) $row->total_credit));

            if ($row->bucket === 'opening') {
                $opening = $net;
            } else {
                $months[(string) $row->bucket] = $net;
            }
        }

        return ['opening' => $opening, 'months' => $months];
    }

    /**
     * Tek para biriminde en yeni cari hareketler (sayfalı) ve her satırdan
     * SONRAKİ sistem geneli net bakiye.
     *
     * Sıra `entry_date DESC, id DESC`'tir. Bir satırın bakiyesi, o satır ve
     * ondan eski tüm satırların netidir: sayfanın ilk satırı için
     * `toplam net − (daha yeni satırların neti)`, sonrakiler için bir önceki
     * satırın bakiyesinden o satırın neti düşülerek bulunur. Sorgular:
     * toplam net (1) + sayfalama (2) + daha yeni satırlar (yalnızca 2. sayfadan
     * itibaren, 1). Çağıran aynı para biriminin tüm defterden hesaplanan netini
     * zaten okuduysa currentNet ile geçebilir; toplam net sorgusu tekrarlanmaz.
     * Kayıt sayısıyla BÜYÜMEZ.
     *
     * @return array{entries: LengthAwarePaginator<int, LedgerEntry>, balances: array<int, Money>}
     */
    public function recentEntriesWithBalance(Currency $currency, int $perPage, string $pageName = 'page', ?Money $currentNet = null): array
    {
        $ordered = fn () => LedgerEntry::query()
            ->where('currency', $currency->value)
            ->orderByDesc('entry_date')
            ->orderByDesc('id');

        $running = $currentNet;
        if ($running === null) {
            $totals = DB::table('ledger_entries')
                ->selectRaw('CAST(COALESCE(SUM(debit), 0) AS CHAR) as total_debit')
                ->selectRaw('CAST(COALESCE(SUM(credit), 0) AS CHAR) as total_credit')
                ->where('currency', $currency->value)
                ->first();

            $running = Money::fromDecimal((string) ($totals->total_debit ?? '0'))
                ->subtract(Money::fromDecimal((string) ($totals->total_credit ?? '0')));
        }

        $entries = $ordered()->paginate($perPage, ['*'], $pageName);
        $offset = ($entries->currentPage() - 1) * $entries->perPage();

        if ($offset > 0) {
            $newer = DB::query()
                ->fromSub($ordered()->toBase()->select(['debit', 'credit'])->limit($offset), 'newer')
                ->selectRaw('CAST(COALESCE(SUM(debit), 0) AS CHAR) as total_debit')
                ->selectRaw('CAST(COALESCE(SUM(credit), 0) AS CHAR) as total_credit')
                ->first();

            $running = $running
                ->subtract(Money::fromDecimal((string) ($newer->total_debit ?? '0')))
                ->add(Money::fromDecimal((string) ($newer->total_credit ?? '0')));
        }

        $balances = [];

        foreach ($entries->items() as $entry) {
            $balances[(int) $entry->id] = $running;
            $running = $running->subtract($entry->signedAmount());
        }

        return ['entries' => $entries, 'balances' => $balances];
    }

    /**
     * Tek para biriminde sıfırdan farklı bakiyesi olan hesaplar — mutlak
     * değere göre büyükten küçüğe. Pozitif = hesap bize borçlu, negatif =
     * biz hesaba borçluyuz. TEK sorgu.
     *
     * @return array<int, array{company_id: int, balance: Money}>
     */
    public function nonZeroBalances(Currency $currency, int $limit = 10): array
    {
        $expression = '(COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0))';

        return DB::table('ledger_entries')
            ->selectRaw('company_id')
            ->selectRaw("CAST({$expression} AS CHAR) as balance")
            ->where('currency', $currency->value)
            ->groupBy('company_id')
            ->havingRaw("{$expression} <> 0")
            ->orderByRaw("ABS({$expression}) DESC")
            ->orderBy('company_id')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => [
                'company_id' => (int) $row->company_id,
                'balance' => Money::fromDecimal((string) $row->balance),
            ])
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
