<?php

declare(strict_types=1);

namespace App\Modules\Stays\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Enums\AccountType;
use App\Modules\Companies\Services\CompanyService;
use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Filters\QueryFilter;
use App\Modules\Payments\Services\PaymentAllocationService;
use App\Modules\Rooms\Services\RoomService;
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
    public function __construct(
        private readonly StayService $stays,
        private readonly RoomService $rooms,
        private readonly CompanyService $accounts,
    ) {}

    public function index(StayFilter $filter): View
    {
        $this->authorize('viewAny', Stay::class);

        $query = $filter->apply(Stay::query()->with(['company', 'room', 'creator']));
        $totals = $this->stays->totalsFor($query);

        $stays = $query->paginate($filter->perPage())->withQueryString();

        return view('stays::index', [
            'paymentSummaries' => app(PaymentAllocationService::class)->summaries($stays->getCollection()->modelKeys()),
            'stays' => $stays,
            'totals' => $totals,
            'filter' => $filter,
            'companies' => $this->companyOptions(),
            'currencies' => Currency::cases(),
            'statuses' => StayStatus::cases(),
            'perPageOptions' => QueryFilter::PER_PAGE_OPTIONS,
        ]);
    }

    /**
     * Filtrelenmiş konaklama listesinin yazdırma görünümü.
     *
     * Ekstre/devir raporlarıyla AYNI ilke (§11): sayfalanmaz, filtrelenen
     * kayıtların TAMAMI tek seferde gösterilir — yazdırma bir "sayfa" değil,
     * dönemin bütün dökümüdür.
     */
    public function print(StayFilter $filter): View
    {
        $this->authorize('viewAny', Stay::class);

        $query = $filter->apply(Stay::query()->with(['company', 'room']));
        $totals = $this->stays->totalsFor($query);

        return view('stays::print', [
            'stays' => $query->get(),
            'totals' => $totals,
            'filter' => $filter,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Stay::class);

        return view('stays::create', [
            'companies' => $this->accounts->options(AccountType::Company),
            'individuals' => $this->accounts->options(AccountType::Individual),
            'rooms' => $this->rooms->optionsForForm(),
            'currencies' => Currency::active(),
            'pricingModes' => StayPricingMode::cases(),
            'selectedCompanyId' => $this->accounts->isIndividual($request->integer('company_id')) ? null : ($request->integer('company_id') ?: null),
            'selectedIndividualId' => $this->accounts->isIndividual($request->integer('company_id')) ? $request->integer('company_id') : null,
        ]);
    }

    public function store(StayRequest $request): RedirectResponse
    {
        $stay = $this->stays->create($request->validated(), $request->user());

        return redirect()->route('stays.show', $stay)
            ->with('success', 'Konaklama kaydedildi ve cari hesaba işlendi.');
    }

    public function show(Stay $stay): View
    {
        $this->authorize('view', $stay);

        return view('stays::show', [
            'paymentSummary' => app(PaymentAllocationService::class)->summaries([$stay->id])[$stay->id],
            'stay' => $stay->load(['company', 'room', 'creator', 'updater']),
        ]);
    }

    public function edit(Stay $stay): View
    {
        $this->authorize('update', $stay);

        return view('stays::edit', [
            'stay' => $stay,
            'companies' => $this->accounts->options(AccountType::Company, $stay->company_id),
            'individuals' => $this->accounts->options(AccountType::Individual, $stay->company_id),
            'selectedCompanyId' => $this->accounts->isIndividual($stay->company_id) ? null : $stay->company_id,
            'selectedIndividualId' => $this->accounts->isIndividual($stay->company_id) ? $stay->company_id : null,
            'rooms' => $this->rooms->optionsForForm($stay->room_id),
            'currencies' => Currency::active(),
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

    /** Fatura kesildi / kesilmedi işareti (finansal alan değildir). */
    public function toggleInvoice(Request $request, Stay $stay): RedirectResponse
    {
        $this->authorize('toggleInvoice', $stay);
        $data = $request->validate(['invoiced' => ['required', 'boolean']]);

        try {
            $this->stays->setInvoiced($stay, (bool) $data['invoiced'], $request->user());
        } catch (CannotUpdateCancelledStay $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $data['invoiced']
            ? 'Konaklama "fatura kesildi" olarak işaretlendi.'
            : 'Konaklama "fatura kesilmedi" olarak işaretlendi.');
    }

    /** "Sil" = ortak Çöp Kutusu'na taşı (aktifse önce iptal edilir). */
    public function moveToTrash(Request $request, Stay $stay): RedirectResponse
    {
        $this->authorize('trash', $stay);

        $wasActive = ! $stay->isCancelled();
        $this->stays->trash($stay, $request->user());

        return redirect()->route('stays.index')->with('success', $wasActive
            ? 'Konaklama iptal edildi (cari hareketi ters kaydedildi) ve çöp kutusuna taşındı.'
            : 'Konaklama çöp kutusuna taşındı.');
    }

    /**
     * Firma seçenekleri. Silinmiş firmalar listelenmez.
     *
     * @return array<int, string>
     */
    private function companyOptions(): array
    {
        return $this->accounts->options();
    }
}
