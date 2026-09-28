<?php

declare(strict_types=1);

namespace App\Modules\Offers\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Services\CompanyService;
use App\Modules\Core\Enums\Currency;
use App\Modules\Offers\Enums\OfferStatus;
use App\Modules\Offers\Http\Requests\SaveOfferRequest;
use App\Modules\Offers\Models\Offer;
use App\Modules\Offers\Services\OfferService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OfferController extends Controller
{
    public function __construct(
        private readonly OfferService $offers,
        private readonly CompanyService $companies,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Offer::class);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(array_column(OfferStatus::cases(), 'value'))],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);
        $query = Offer::query()->with('creator')->latest('offer_date')->latest('id');
        if (filled($filters['q'] ?? null)) {
            $term = trim($filters['q']);
            $query->where(fn ($q) => $q->where('offer_no', 'like', "%{$term}%")
                ->orWhere('customer_name_snapshot', 'like', "%{$term}%")
                ->orWhere('title', 'like', "%{$term}%"));
        }
        foreach (['status' => 'status', 'from' => 'offer_date', 'to' => 'offer_date'] as $key => $column) {
            if (filled($filters[$key] ?? null)) {
                $query->where($column, match ($key) {
                    'from' => '>=', 'to' => '<=', default => '=',
                }, $filters[$key]);
            }
        }

        return view('offers::index', [
            'offers' => $query->paginate(20)->withQueryString(),
            'statuses' => OfferStatus::cases(),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Offer::class);

        return view('offers::create', $this->formData());
    }

    public function store(SaveOfferRequest $request): RedirectResponse
    {
        $offer = $this->offers->save($request->validated());

        return redirect()->route('offers.show', $offer)->with('success', 'Teklif oluşturuldu.');
    }

    public function show(Offer $offer): View
    {
        $this->authorize('view', $offer);

        return view('offers::show', ['offer' => $this->loadOffer($offer)]);
    }

    public function print(Offer $offer): View
    {
        $this->authorize('view', $offer);

        return view('offers::print', ['offer' => $this->loadOffer($offer)]);
    }

    public function edit(Offer $offer): View
    {
        $this->authorize('update', $offer);

        return view('offers::edit', [...$this->formData($offer), 'offer' => $this->loadOffer($offer)]);
    }

    public function update(SaveOfferRequest $request, Offer $offer): RedirectResponse
    {
        $this->authorize('update', $offer);
        $this->offers->save($request->validated(), $offer);

        return redirect()->route('offers.show', $offer)->with('success', 'Teklif güncellendi.');
    }

    private function loadOffer(Offer $offer): Offer
    {
        return $offer->load(['periods', 'roomTypes', 'prices', 'creator']);
    }

    private function formData(?Offer $offer = null): array
    {
        $currencies = Currency::active();
        if ($offer !== null && ! in_array($offer->currency, $currencies, true)) {
            $currencies[] = $offer->currency;
        }

        return [
            'companies' => $this->companies->options(currentId: $offer?->company_id),
            'currencies' => $currencies,
            'statuses' => OfferStatus::cases(),
            'defaults' => [
                'offer_date' => now()->toDateString(),
                'currency' => config('hotel.default_currency', 'TRY'),
                'status' => OfferStatus::Draft->value,
                'document_year' => (int) now()->year,
                'document_heading' => strtoupper((string) config('hotel.company.receipt_brand_name', 'LEMON HOTEL')).' FİYAT TEKLİFİ',
                'hotel_name_snapshot' => config('hotel.company.receipt_brand_name'),
                'legal_name_snapshot' => config('hotel.company.legal_name'),
                'tax_office_snapshot' => config('hotel.company.tax_office'),
                'tax_number_snapshot' => config('hotel.company.tax_number'),
                'address_snapshot' => config('hotel.company.address'),
                'phone_snapshot' => config('hotel.company.phone'),
                'email_snapshot' => config('hotel.company.email'),
            ],
        ];
    }
}
