<?php

declare(strict_types=1);

namespace App\Modules\Companies\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Authorization\Enums\Permission;
use App\Modules\Companies\Exceptions\CompanyHasLedgerEntries;
use App\Modules\Companies\Filters\CompanyFilter;
use App\Modules\Companies\Http\Requests\CompanyRequest;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Services\CompanyService;
use App\Modules\Core\Filters\QueryFilter;
use App\Modules\Core\Support\Money;
use App\Modules\Ledger\Services\LedgerService;
use App\Modules\Payments\Services\PaymentService;
use App\Modules\Stays\Services\StayService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function __construct(
        private readonly CompanyService $companies,
        private readonly StayService $stays,
        private readonly PaymentService $payments,
        private readonly LedgerService $ledger,
    ) {}

    public function index(Request $request, CompanyFilter $filter): View
    {
        $this->authorize('viewAny', Company::class);

        $showTrashed = $request->query('trashed') === '1'
            && $request->user()->can('viewTrashed', Company::class);

        $query = Company::query()->with('creator');

        if ($showTrashed) {
            $query->onlyTrashed();
        }

        $companies = $filter->apply($query)
            ->paginate($filter->perPage())
            ->withQueryString();

        return view('companies::index', [
            'companies' => $companies,
            'filter' => $filter,
            'showTrashed' => $showTrashed,
            'activeCount' => $showTrashed ? null : $this->companies->activeCount(),
            'cities' => $this->cityOptions(),
            'perPageOptions' => QueryFilter::PER_PAGE_OPTIONS,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Company::class);

        return view('companies::create');
    }

    public function store(CompanyRequest $request): RedirectResponse
    {
        $company = $this->companies->create($request->validated());

        return redirect()->route('companies.show', $company)
            ->with('success', 'Firma oluşturuldu.');
    }

    public function show(Request $request, Company $company): View
    {
        $this->authorize('view', $company);

        // Konaklamalar ve tahsilatlar ilgili modüllerin SERVİSİNDEN alınır;
        // Companies modülü Stay/Payment modelini doğrudan kullanmaz
        // (CLAUDE.md §4.3/1). Servisler ilişkileri eager-load eder — N+1 yok.
        return view('companies::show', [
            'company' => $company->load(['creator', 'updater']),
            'recentStays' => $this->stays->recentForCompany($company->getKey()),
            'stayCount' => $this->stays->countForCompany($company->getKey()),
            'recentPayments' => $this->payments->recentForCompany($company->getKey(), 5),
            'paymentCount' => $this->payments->countForCompany($company->getKey()),

            // Bakiye para birimi bazındadır ve TEK sorguda gelir. Personel
            // bakiye göremez (CLAUDE.md §7.4), bu yüzden sorgu bile atılmaz —
            // gizlenmez, hiç yapılmaz.
            'balances' => $request->user()?->can(Permission::LedgerView->value) === true
                ? $this->ledger->balancesForCompanyByCurrency($company->getKey())
                : [],

            // Bakiyenin TL karşılığı (v2.1.1-D §3) — AYNI yetki koşulu,
            // Firma Bakiye Listesi'ndeki `companyFxTotals()` ile AYNI
            // per-firma sorgu deseni (Stays debit + Payments credit).
            'balancesFx' => $request->user()?->can(Permission::LedgerView->value) === true
                ? $this->companyFx($company->getKey())
                : [],
        ]);
    }

    public function edit(Company $company): View
    {
        $this->authorize('update', $company);

        return view('companies::edit', ['company' => $company]);
    }

    public function update(CompanyRequest $request, Company $company): RedirectResponse
    {
        $this->companies->update($company, $request->validated());

        return redirect()->route('companies.show', $company)
            ->with('success', 'Firma güncellendi.');
    }

    public function destroy(Company $company): RedirectResponse
    {
        $this->authorize('delete', $company);

        try {
            $this->companies->delete($company);
        } catch (CompanyHasLedgerEntries $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('companies.index')
            ->with('success', 'Firma çöp kutusuna taşındı. Çöp Kutusu\'ndan geri alınabilir.');
    }

    public function restore(Company $company): RedirectResponse
    {
        $this->authorize('restore', $company);

        $this->companies->restore($company);

        return redirect()->route('companies.show', $company)
            ->with('success', 'Firma geri alındı.');
    }

    /**
     * Tek firmanın para birimi bazında TL karşılığı — `StatementController::
     * companyFxTotals()` ile AYNI hesap, tek firma için.
     *
     * @return array<string, array{net: Money, complete: bool}>
     */
    private function companyFx(int $companyId): array
    {
        $debit = $this->stays->exchangeRateDebitTotalsForCompanies([$companyId])[$companyId] ?? [];
        $credit = $this->payments->exchangeRateCreditTotalsForCompanies([$companyId])[$companyId] ?? [];

        $codes = array_unique([...array_keys($debit), ...array_keys($credit)]);
        $result = [];

        foreach ($codes as $code) {
            $debitEntry = $debit[$code] ?? ['total' => Money::zero(), 'complete' => true];
            $creditEntry = $credit[$code] ?? ['total' => Money::zero(), 'complete' => true];

            $result[$code] = [
                'net' => $debitEntry['total']->subtract($creditEntry['total']),
                'complete' => $debitEntry['complete'] && $creditEntry['complete'],
            ];
        }

        return $result;
    }

    /**
     * Şehir filtresi seçenekleri. Kayıtlı şehirlerden türetilir; ayrı bir
     * referans tablosu açılmaz (CLAUDE.md §3.3).
     *
     * @return array<int, string>
     */
    private function cityOptions(): array
    {
        return Company::query()
            ->whereNotNull('city')
            ->where('city', '!=', '')
            ->distinct()
            ->orderBy('city')
            ->pluck('city')
            ->all();
    }
}
