<?php

declare(strict_types=1);

namespace App\Modules\Companies\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Exceptions\CompanyHasLedgerEntries;
use App\Modules\Companies\Filters\CompanyFilter;
use App\Modules\Companies\Http\Requests\CompanyRequest;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Services\CompanyService;
use App\Modules\Core\Filters\QueryFilter;
use App\Modules\Stays\Services\StayService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function __construct(
        private readonly CompanyService $companies,
        private readonly StayService $stays,
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

    public function show(Company $company): View
    {
        $this->authorize('view', $company);

        // Konaklamalar Stays modülünün servisinden alınır; Companies modülü
        // Stay modelini doğrudan kullanmaz (CLAUDE.md §4.3/1).
        return view('companies::show', [
            'company' => $company->load(['creator', 'updater']),
            'recentStays' => $this->stays->recentForCompany($company->getKey()),
            'stayCount' => $this->stays->countForCompany($company->getKey()),
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
            ->with('success', 'Firma silindi. Kayıt saklanır, geri alınabilir.');
    }

    public function restore(Company $company): RedirectResponse
    {
        $this->authorize('restore', $company);

        $this->companies->restore($company);

        return redirect()->route('companies.show', $company)
            ->with('success', 'Firma geri alındı.');
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
