<?php

declare(strict_types=1);

namespace App\Modules\Companies\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Filters\CompanyFilter;
use App\Modules\Companies\Models\Company;
use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Filters\QueryFilter;
use App\Modules\Core\Support\Money;
use App\Modules\Ledger\Enums\LedgerEntryType;
use App\Modules\Ledger\Services\LedgerStatementService;
use App\Modules\Ledger\Support\StatementCriteria;
use App\Modules\Ledger\Support\StatementRow;
use App\Modules\Payments\Services\PaymentService;
use App\Modules\Stays\Services\StayService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Cari ekstre ekranları.
 *
 * Companies modülünde durur, Ledger modülünde DEĞİL: `Ledger` hiçbir modülü
 * tanımaz (CLAUDE.md §4.3/3) ve firma adını okuyabilmek için Companies'e
 * bağımlı olamaz. Ekstre hesabının tamamı `LedgerStatementService`'tedir;
 * o servis firmayı yalnızca `int $companyId` olarak bilir.
 *
 * v2.1.1-D §3: yabancı para birimi TL karşılığı hesapları da AYNI gerekçeyle
 * burada yapılır — `LedgerStatementService`'in üzerine, StayService/
 * PaymentService'in kur anlık görüntüsü sorgularıyla birleştirilerek.
 */
class StatementController extends Controller
{
    public function __construct(
        private readonly LedgerStatementService $statements,
        private readonly StayService $stays,
        private readonly PaymentService $payments,
    ) {}

    /**
     * Firma bakiye listesi ve ekstre seçim ekranı (`ledger.view_all`).
     *
     * Para birimi / bakiye filtresi ya da bakiye sıralaması İSTENMEDİYSE
     * mevcut hızlı yol değişmeden çalışır: firmalar SQL'de sayfalanır, bakiye
     * yalnızca o sayfanın firmaları için tek toplu sorguda çekilir.
     *
     * İstendiğinde ise "bakiye" bir `companies` kolonu OLMADIĞI için
     * (CLAUDE.md §6/L4 — önbelleklenmiş `balance` kolonu yoktur) filtreye
     * uyan TÜM firmaların bakiyesi tek toplu sorguda hesaplanır, süzülür/
     * sıralanır, ancak GÖRÜNTÜLENECEK sayfa kadarı için firma modeli çekilir.
     * V1 veri hacminde (firma sayısı düşük-orta) bu PHP-taraflı adım
     * pragmatiktir; kayıt sayısı arttıkça yeniden değerlendirilmelidir.
     */
    public function index(Request $request, CompanyFilter $filter): View
    {
        $currency = Currency::tryFrom((string) $request->query('currency', ''));
        $hasBalance = $request->query('has_balance') === '1';
        $balanceSortRaw = (string) $request->query('balance_sort', '');
        $balanceSort = in_array($balanceSortRaw, ['highest', 'lowest'], true) ? $balanceSortRaw : null;

        // Para birimi seçilmeden bakiye sıralaması ANLAMSIZDIR (§6/L5): para
        // birimleri asla birleştirilmez, "en yüksek bakiye" tek bir sayı
        // olamaz. Bu durumda sıralama sessizce isim sırasına düşer.
        if ($balanceSort !== null && $currency === null) {
            $balanceSort = null;
        }

        $balanceModeActive = $currency !== null || $hasBalance || $balanceSort !== null;

        if (! $balanceModeActive) {
            $companies = $filter->apply(Company::query())
                ->paginate($filter->perPage())
                ->withQueryString();

            /** @var array<int, int> $ids */
            $ids = $companies->getCollection()->pluck('id')->all();

            return view('companies::statements.index', [
                'companies' => $companies,
                'filter' => $filter,
                'balances' => $this->statements->balancesForCompanies($ids),
                'fxTotals' => $this->companyFxTotals($ids),
                'lastDates' => $this->statements->lastEntryDates($ids),
                'perPageOptions' => QueryFilter::PER_PAGE_OPTIONS,
                'balanceCurrency' => $currency,
                'hasBalanceOnly' => $hasBalance,
                'balanceSort' => $balanceSort,
            ]);
        }

        /** @var Collection<int, Company> $matching */
        $matching = $filter->apply(Company::query())->get(['id', 'name']);

        /** @var array<int, int> $matchingIds */
        $matchingIds = $matching->pluck('id')->all();
        $allBalances = $this->statements->balancesForCompanies($matchingIds);

        $rows = $matching->map(fn (Company $company): array => [
            'id' => $company->getKey(),
            'sortBalance' => $currency !== null
                ? ($allBalances[$company->getKey()][$currency->value] ?? Money::zero())
                : null,
            'hasAnyBalance' => $currency !== null
                ? ! ($allBalances[$company->getKey()][$currency->value] ?? Money::zero())->isZero()
                : collect($allBalances[$company->getKey()] ?? [])->contains(fn (Money $m): bool => ! $m->isZero()),
        ]);

        if ($hasBalance) {
            $rows = $rows->filter(fn (array $row): bool => $row['hasAnyBalance'])->values();
        }

        if ($balanceSort !== null) {
            $rows = $balanceSort === 'highest'
                ? $rows->sortByDesc(fn (array $row) => $row['sortBalance']->minorUnits)->values()
                : $rows->sortBy(fn (array $row) => $row['sortBalance']->minorUnits)->values();
        }

        $orderedIds = $rows->pluck('id')->all();
        $perPage = $filter->perPage();
        $page = max(1, (int) $request->query('page', 1));
        $pageIds = array_slice($orderedIds, ($page - 1) * $perPage, $perPage);

        $companiesById = Company::query()->whereIn('id', $pageIds)->get()->keyBy('id');
        $pageCompanies = collect($pageIds)
            ->map(fn (int $id) => $companiesById->get($id))
            ->filter()
            ->values();

        $paginated = new LengthAwarePaginator(
            $pageCompanies,
            count($orderedIds),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return view('companies::statements.index', [
            'companies' => $paginated,
            'filter' => $filter,
            // Sayfadaki firmalar için alt küme — tam liste zaten $allBalances'ta hesaplı.
            'balances' => array_intersect_key($allBalances, array_flip($pageIds)),
            'fxTotals' => $this->companyFxTotals($pageIds),
            'lastDates' => $this->statements->lastEntryDates($pageIds),
            'perPageOptions' => QueryFilter::PER_PAGE_OPTIONS,
            'balanceCurrency' => $currency,
            'hasBalanceOnly' => $hasBalance,
            'balanceSort' => $balanceSort,
        ]);
    }

    /**
     * Firma bakiye listesinin yazdırma görünümü (`ledger.view_all`).
     *
     * Sayfalama bilinçli olarak uygulanmaz: yazdırma çıktısı aktif arama,
     * para birimi, bakiye ve sıralama filtrelerine uyan TÜM firmaları kapsar.
     */
    public function printIndex(Request $request, CompanyFilter $filter): View
    {
        $currency = Currency::tryFrom((string) $request->query('currency', ''));
        $hasBalance = $request->query('has_balance') === '1';
        $balanceSortRaw = (string) $request->query('balance_sort', '');
        $balanceSort = in_array($balanceSortRaw, ['highest', 'lowest'], true) ? $balanceSortRaw : null;

        if ($balanceSort !== null && $currency === null) {
            $balanceSort = null;
        }

        /** @var Collection<int, Company> $matching */
        $matching = $filter->apply(Company::query())->get(['id', 'name']);

        /** @var array<int, int> $matchingIds */
        $matchingIds = $matching->pluck('id')->all();
        $allBalances = $this->statements->balancesForCompanies($matchingIds);

        $rows = $matching->map(fn (Company $company): array => [
            'id' => $company->getKey(),
            'sortBalance' => $currency !== null
                ? ($allBalances[$company->getKey()][$currency->value] ?? Money::zero())
                : null,
            'hasAnyBalance' => $currency !== null
                ? ! ($allBalances[$company->getKey()][$currency->value] ?? Money::zero())->isZero()
                : collect($allBalances[$company->getKey()] ?? [])->contains(fn (Money $money): bool => ! $money->isZero()),
        ]);

        if ($hasBalance) {
            $rows = $rows->filter(fn (array $row): bool => $row['hasAnyBalance'])->values();
        }

        if ($balanceSort !== null) {
            $rows = $balanceSort === 'highest'
                ? $rows->sortByDesc(fn (array $row) => $row['sortBalance']->minorUnits)->values()
                : $rows->sortBy(fn (array $row) => $row['sortBalance']->minorUnits)->values();
        }

        /** @var array<int, int> $orderedIds */
        $orderedIds = $rows->pluck('id')->all();
        $companiesById = Company::query()->whereIn('id', $orderedIds)->get()->keyBy('id');
        $companies = collect($orderedIds)
            ->map(fn (int $id) => $companiesById->get($id))
            ->filter()
            ->values();

        return view('companies::statements.index-print', [
            'companies' => $companies,
            'filter' => $filter,
            'balances' => array_intersect_key($allBalances, array_flip($orderedIds)),
            'fxTotals' => $this->companyFxTotals($orderedIds),
            'lastDates' => $this->statements->lastEntryDates($orderedIds),
            'balanceCurrency' => $currency,
            'hasBalanceOnly' => $hasBalance,
            'balanceSort' => $balanceSort,
            'generatedAt' => now(),
        ]);
    }

    /** Tek firmanın cari ekstresi (`ledger.view`). */
    public function show(Request $request, Company $company): View
    {
        return view('companies::statements.show', [
            ...$this->statementData($request, $company),
            'activeFilterCount' => QueryFilter::countActiveFilters($request),
        ]);
    }

    /** Yazdırma görünümü — koyu tema uygulanmaz, beyaz zemin. */
    public function print(Request $request, Company $company): View
    {
        return view('companies::statements.print', $this->statementData($request, $company));
    }

    /**
     * @return array<string, mixed>
     */
    private function statementData(Request $request, Company $company): array
    {
        $criteria = StatementCriteria::fromRequest($request);
        $statement = $this->statements->forCompany($company->getKey(), $criteria);

        return [
            'company' => $company,
            'statement' => $statement,
            'criteria' => $criteria,
            'currencies' => Currency::cases(),
            'types' => LedgerEntryType::filterable(),
            'existingSources' => $this->existingSources($statement->rows),
            'fx' => $this->tlEquivalents($company->getKey(), $criteria),
        ];
    }

    /**
     * Hangi kaynak kayıtların hâlâ var olduğu — kaynak tipi başına TEK sorgu.
     *
     * Ledger'ın doğruluğu kaynak kayda bağlı değildir: kayıt silinmiş olsa
     * bile ekstre satırı geçerlidir. Bu yüzden bağlantı vermeden önce varlığı
     * toplu olarak sorulur; satır başına sorgu (N+1) atılmaz.
     *
     * @param  array<int, StatementRow>  $rows
     * @return array<string, array<int, int>>
     */
    private function existingSources(array $rows): array
    {
        $ids = ['stay' => [], 'payment' => []];

        foreach ($rows as $row) {
            $type = (string) $row->entry->source_type;

            if (isset($ids[$type]) && $row->entry->source_id !== null) {
                $ids[$type][] = $row->entry->source_id;
            }
        }

        return [
            'stay' => $this->stays->existingIds(array_values(array_unique($ids['stay']))),
            'payment' => $this->payments->existingIds(array_values(array_unique($ids['payment']))),
        ];
    }

    /**
     * Kur anlık görüntüsü — kaynak tipi başına TEK sorgu (v2.1.1-D §3).
     *
     * @param  array<int, StatementRow>  $rows
     * @return array<string, array<int, array{rate: ?string, equivalent: ?string}>>
     */
    private function exchangeRateSnapshots(array $rows): array
    {
        $ids = ['stay' => [], 'payment' => []];

        foreach ($rows as $row) {
            $type = (string) $row->entry->source_type;

            if (isset($ids[$type]) && $row->entry->source_id !== null) {
                $ids[$type][] = $row->entry->source_id;
            }
        }

        return [
            'stay' => $this->stays->exchangeRateSnapshotsFor(array_values(array_unique($ids['stay']))),
            'payment' => $this->payments->exchangeRateSnapshotsFor(array_values(array_unique($ids['payment']))),
        ];
    }

    /**
     * Her hareketin TL karşılığı VE o para biriminin o anki yürüyen TL
     * bakiyesi — ekstredeki "Bakiye" kolonunun altında küçük yazıyla
     * gösterilir (kullanıcı talimatı, v2.1.1-D §3).
     *
     * KRİTİK: bu zincir GÖRÜNTÜLENEN filtreden (tarih/tür/arama) TAMAMEN
     * BAĞIMSIZDIR — tıpkı `runningBalance`'ın kendisi gibi, defterin
     * TAMAMINDAN (tarih filtresi olmadan) hesaplanır; aksi hâlde bir tarih
     * filtresi uygulandığında devreden bakiyenin TL karşılığı hiç
     * bilinemezdi. `LedgerStatementService::forCompany()` bu yüzden BURADA
     * ikinci kez, filtresiz ve kronolojik sırada çağrılır — Ledger modülüne
     * hiçbir yeni metot eklenmeden.
     *
     * Bir para biriminde katkı sağlayan HERHANGİ bir kaynak kaydın kuru
     * eksikse (bu turdan önceki, düzeltilmemiş kayıt), o para biriminin TL
     * zinciri o noktadan itibaren "eksik" işaretlenir — sessizce yanlış bir
     * rakam ASLA üretilmez (CLAUDE.md §13 dürüstlük ilkesi, `Payment::
     * amountInWords()`'teki AYNI karar).
     *
     * Dönüş şekli:
     *   - `byEntry`: `ledger_entries.id` → satırın kendi TL karşılığı + o ana
     *     kadarki yürüyen TL bakiyesi ("Bakiye" kolonunun altında gösterilir).
     *   - `closing`: para birimi kodu → EN SON hareketin TL bakiyesi (özet
     *     kartındaki "Bakiye" satırı).
     *   - `carryForward`: para birimi kodu → `criteria->dateFrom`'dan HEMEN
     *     ÖNCEKİ TL bakiyesi ("Devreden bakiye" satırı); tarih filtresi
     *     yoksa boştur.
     *
     * @return array{
     *     byEntry: array<int, array{equivalent: ?Money, runningTl: ?Money, complete: bool}>,
     *     closing: array<string, array{value: ?Money, complete: bool}>,
     *     carryForward: array<string, array{value: ?Money, complete: bool}>,
     * }
     */
    private function tlEquivalents(int $companyId, StatementCriteria $criteria): array
    {
        $baseline = $this->statements->forCompany($companyId, new StatementCriteria);
        $snapshots = $this->exchangeRateSnapshots($baseline->rows);

        $running = [];
        $complete = [];
        $result = [];
        $closing = [];
        $carryForward = [];

        foreach ($baseline->rows as $row) {
            $code = $row->currency()->value;

            if ($code === Currency::TRY->value) {
                continue;
            }

            // dateFrom'dan ÖNCEKİ son satırdaysak, devreden anlık görüntüsünü
            // şimdi (bir sonraki satıra geçmeden önce) yakala.
            if (
                $criteria->dateFrom !== null
                && $row->entry->entry_date->lt($criteria->dateFrom)
            ) {
                $carryForward[$code] = ['value' => $running[$code] ?? Money::zero(), 'complete' => $complete[$code] ?? true];
            }

            $running[$code] ??= Money::zero();
            $complete[$code] ??= true;

            $type = (string) $row->entry->source_type;
            $sourceId = $row->entry->source_id;
            $snapshot = ($sourceId !== null ? ($snapshots[$type][$sourceId] ?? null) : null);
            $equivalent = $snapshot['equivalent'] ?? null;

            if ($equivalent === null) {
                $complete[$code] = false;
            } else {
                $signed = Money::fromDecimal($equivalent);
                $running[$code] = $running[$code]->add($row->entry->isDebit() ? $signed : $signed->negate());
            }

            $result[$row->entry->id] = [
                'equivalent' => $equivalent === null ? null : Money::fromDecimal($equivalent),
                'runningTl' => $complete[$code] ? $running[$code] : null,
                'complete' => $complete[$code],
            ];

            $closing[$code] = ['value' => $complete[$code] ? $running[$code] : null, 'complete' => $complete[$code]];
        }

        return ['byEntry' => $result, 'closing' => $closing, 'carryForward' => $carryForward];
    }

    /**
     * Firmaların TÜM zamanlar TL karşılığı bakiyesi (borç − alacak), para
     * birimi bazında — Firma Bakiye Listesi içindir (v2.1.1-D §3). İki bulk
     * sorgunun (Stays + Payments) birleşimidir; firma/kayıt sayısı kadar
     * sorgu ATILMAZ.
     *
     * @param  array<int, int>  $companyIds
     * @return array<int, array<string, array{net: Money, complete: bool}>>
     */
    private function companyFxTotals(array $companyIds): array
    {
        if ($companyIds === []) {
            return [];
        }

        $debit = $this->stays->exchangeRateDebitTotalsForCompanies($companyIds);
        $credit = $this->payments->exchangeRateCreditTotalsForCompanies($companyIds);

        $result = [];

        foreach ($companyIds as $companyId) {
            $codes = array_unique([
                ...array_keys($debit[$companyId] ?? []),
                ...array_keys($credit[$companyId] ?? []),
            ]);

            foreach ($codes as $code) {
                $debitEntry = $debit[$companyId][$code] ?? ['total' => Money::zero(), 'complete' => true];
                $creditEntry = $credit[$companyId][$code] ?? ['total' => Money::zero(), 'complete' => true];

                $result[$companyId][$code] = [
                    'net' => $debitEntry['total']->subtract($creditEntry['total']),
                    'complete' => $debitEntry['complete'] && $creditEntry['complete'],
                ];
            }
        }

        return $result;
    }
}
