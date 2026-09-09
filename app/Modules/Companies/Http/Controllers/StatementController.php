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
            'lastDates' => $this->statements->lastEntryDates($pageIds),
            'perPageOptions' => QueryFilter::PER_PAGE_OPTIONS,
            'balanceCurrency' => $currency,
            'hasBalanceOnly' => $hasBalance,
            'balanceSort' => $balanceSort,
        ]);
    }

    /** Tek firmanın cari ekstresi (`ledger.view`). */
    public function show(Request $request, Company $company): View
    {
        return view('companies::statements.show', $this->statementData($request, $company));
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
}
