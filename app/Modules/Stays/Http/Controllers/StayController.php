<?php

declare(strict_types=1);

namespace App\Modules\Stays\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Models\Company;
use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Filters\QueryFilter;
use App\Modules\Stays\Enums\StayPricingMode;
use App\Modules\Stays\Enums\StayStatus;
use App\Modules\Stays\Exceptions\CannotUpdateCancelledStay;
use App\Modules\Stays\Exceptions\StayAlreadyCancelled;
use App\Modules\Stays\Filters\StayFilter;
use App\Modules\Stays\Http\Requests\StayRequest;
use App\Modules\Stays\Models\Stay;
use App\Modules\Stays\Services\StayService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StayController extends Controller
{
    public function __construct(private readonly StayService $stays) {}

    public function index(StayFilter $filter): View
    {
        $this->authorize('viewAny', Stay::class);

        $stays = $filter->apply(Stay::query()->with(['company', 'creator']))
            ->paginate($filter->perPage())
            ->withQueryString();

        return view('stays::index', [
            'stays' => $stays,
            'filter' => $filter,
            'companies' => $this->companyOptions(),
            'currencies' => Currency::cases(),
            'statuses' => StayStatus::cases(),
            'perPageOptions' => QueryFilter::PER_PAGE_OPTIONS,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Stay::class);

        return view('stays::create', [
            'companies' => $this->companyOptions(),
            'currencies' => Currency::cases(),
            'pricingModes' => StayPricingMode::cases(),
            'selectedCompanyId' => $request->integer('company_id') ?: null,
        ]);
    }

    public function store(StayRequest $request): RedirectResponse
    {
        $stay = $this->stays->create($request->validated(), $request->user());

        return redirect()->route('stays.show', $stay)
            ->with('success', 'Konaklama kaydedildi ve firma carisine işlendi.');
    }

    public function show(Stay $stay): View
    {
        $this->authorize('view', $stay);

        return view('stays::show', [
            'stay' => $stay->load(['company', 'creator', 'updater']),
        ]);
    }

    public function edit(Stay $stay): View
    {
        $this->authorize('update', $stay);

        return view('stays::edit', [
            'stay' => $stay,
            'companies' => $this->companyOptions(),
            'currencies' => Currency::cases(),
            'pricingModes' => StayPricingMode::cases(),
        ]);
    }

    public function update(StayRequest $request, Stay $stay): RedirectResponse
    {
        try {
            $this->stays->update($stay, $request->validated(), $request->user());
        } catch (CannotUpdateCancelledStay $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('stays.show', $stay)
            ->with('success', 'Konaklama güncellendi. Finansal değişiklik varsa cari hareketi yeniden oluşturuldu.');
    }

    public function cancel(Request $request, Stay $stay): RedirectResponse
    {
        $this->authorize('cancel', $stay);

        try {
            $this->stays->cancel($stay, $request->user());
        } catch (StayAlreadyCancelled $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('stays.show', $stay)
            ->with('success', 'Konaklama iptal edildi ve cari hareketi ters kaydedildi.');
    }

    /**
     * Firma seçenekleri. Silinmiş firmalar listelenmez.
     *
     * @return array<int, string>
     */
    private function companyOptions(): array
    {
        return Company::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
