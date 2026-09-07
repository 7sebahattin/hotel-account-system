<?php

declare(strict_types=1);

namespace App\Modules\Companies\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Filters\CompanyFilter;
use App\Modules\Companies\Models\Company;
use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Filters\QueryFilter;
use App\Modules\Ledger\Enums\LedgerEntryType;
use App\Modules\Ledger\Services\LedgerStatementService;
use App\Modules\Ledger\Support\StatementCriteria;
use App\Modules\Ledger\Support\StatementRow;
use App\Modules\Payments\Services\PaymentService;
use App\Modules\Stays\Services\StayService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

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
     */
    public function index(CompanyFilter $filter): View
    {
        $companies = $filter->apply(Company::query())
            ->paginate($filter->perPage())
            ->withQueryString();

        /** @var array<int, int> $ids */
        $ids = $companies->getCollection()->pluck('id')->all();

        return view('companies::statements.index', [
            'companies' => $companies,
            'filter' => $filter,
            // İki toplu sorgu; firma başına sorgu atılmaz.
            'balances' => $this->statements->balancesForCompanies($ids),
            'lastDates' => $this->statements->lastEntryDates($ids),
            'perPageOptions' => QueryFilter::PER_PAGE_OPTIONS,
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
