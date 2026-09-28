@php
    $value = fn (string $key, $fallback = null) => old($key, $offer?->{$key} ?? ($defaults[$key] ?? $fallback));
    $periods = old('periods', $offer ? $offer->periods->map(fn ($period) => [
        'label' => $period->label,
        'start_date' => $period->start_date?->format('Y-m-d'),
        'end_date' => $period->end_date?->format('Y-m-d'),
    ])->all() : [['label' => '', 'start_date' => '', 'end_date' => '']]);
    $roomTypes = old('room_types', $offer ? $offer->roomTypes->map(fn ($room) => ['name' => $room->name])->all() : [['name' => 'SINGLE'], ['name' => 'DOUBLE']]);
    $savedPrices = [];
    if ($offer) {
        foreach ($offer->roomTypes as $ri => $room) {
            foreach ($offer->periods as $pi => $period) {
                $price = $offer->prices->first(fn ($item) => $item->offer_room_type_id === $room->id && $item->offer_period_id === $period->id);
                $savedPrices[$ri][$pi] = $price?->price;
            }
        }
    }
    $prices = old('prices', $savedPrices);
    $matrix = ['periods' => array_values($periods), 'rooms' => array_values($roomTypes), 'prices' => $prices];
@endphp
<form method="POST" action="{{ $offer ? route('offers.update', $offer) : route('offers.store') }}" class="mt-6 space-y-6">
    @csrf
    @if ($offer) @method('PUT') @endif
    <x-ui.errors />

    <section class="rounded-2xl border border-line bg-surface p-5 shadow-sm sm:p-6">
        <div class="mb-5"><p class="text-xs font-bold uppercase tracking-widest text-acc">01 / Temel bilgiler</p><h2 class="mt-1 text-xl font-semibold">Teklifin çerçevesi</h2></div>
        <div class="grid gap-4 md:grid-cols-2">
            <label class="block text-sm font-medium">Cari / müşteri
                <select name="company_id" class="mt-1 w-full rounded-lg border border-line bg-bg px-3 py-2.5">
                    <option value="">Serbest müşteri adı kullan</option>
                    @foreach ($companies as $id => $name)
                        <option value="{{ $id }}" @selected((string) $value('company_id') === (string) $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block text-sm font-medium">Müşteri adı <span class="font-normal text-tx-muted">(cari seçiliyse boş bırakılabilir)</span>
                <input name="customer_name_snapshot" value="{{ $value('customer_name_snapshot') }}" maxlength="200" class="mt-1 w-full rounded-lg border border-line bg-bg px-3 py-2.5">
            </label>
            <label class="block text-sm font-medium md:col-span-2">Teklif başlığı
                <input name="title" value="{{ $value('title') }}" required maxlength="200" placeholder="2026 Sonbahar konaklama teklifi" class="mt-1 w-full rounded-lg border border-line bg-bg px-3 py-2.5">
            </label>
            <label class="block text-sm font-medium">Teklif tarihi
                <input type="date" name="offer_date" value="{{ old('offer_date', $offer?->offer_date?->format('Y-m-d') ?? $defaults['offer_date']) }}" required class="mt-1 w-full rounded-lg border border-line bg-bg px-3 py-2.5">
            </label>
            <label class="block text-sm font-medium">Geçerlilik tarihi
                <input type="date" name="valid_until" value="{{ old('valid_until', $offer?->valid_until?->format('Y-m-d')) }}" class="mt-1 w-full rounded-lg border border-line bg-bg px-3 py-2.5">
            </label>
            <label class="block text-sm font-medium">Para birimi
                <select name="currency" class="mt-1 w-full rounded-lg border border-line bg-bg px-3 py-2.5">
                    @foreach ($currencies as $currency)
                        <option value="{{ $currency }}" @selected($value('currency') === $currency)>{{ $currency }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block text-sm font-medium">Durum
                <select name="status" class="mt-1 w-full rounded-lg border border-line bg-bg px-3 py-2.5">
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(($value('status') instanceof \App\Modules\Offers\Enums\OfferStatus ? $value('status')->value : $value('status')) === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </section>

    <section class="rounded-2xl border border-line bg-surface p-5 shadow-sm sm:p-6">
        <div class="mb-5"><p class="text-xs font-bold uppercase tracking-widest text-acc">02 / Belge başlığı</p><h2 class="mt-1 text-xl font-semibold">Müşterinin göreceği alan</h2></div>
        <div class="grid gap-4 md:grid-cols-[2fr_1fr]">
            <label class="block text-sm font-medium">Üst başlık
                <input name="document_heading" value="{{ $value('document_heading') }}" required maxlength="200" class="mt-1 w-full rounded-lg border border-line bg-bg px-3 py-2.5">
            </label>
            <label class="block text-sm font-medium">Yıl
                <input type="number" name="document_year" value="{{ $value('document_year') }}" min="2000" max="2100" required class="mt-1 w-full rounded-lg border border-line bg-bg px-3 py-2.5">
            </label>
            <label class="block text-sm font-medium md:col-span-2">Giriş metni
                <textarea name="intro_text" rows="2" class="mt-1 w-full rounded-lg border border-line bg-bg px-3 py-2.5">{{ $value('intro_text') }}</textarea>
            </label>
        </div>
    </section>

    <section class="rounded-2xl border border-line bg-surface p-5 shadow-sm sm:p-6">
        <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
            <div><p class="text-xs font-bold uppercase tracking-widest text-acc">03 / Fiyat tablosu</p><h2 class="mt-1 text-xl font-semibold">Dönem × oda tipi</h2></div>
            <p class="text-sm text-tx-muted">Boş hücreler belgeye “—” olarak yansır. Fiyatlar seçilen para birimindedir.</p>
        </div>
        <div data-offer-matrix data-initial='@json($matrix)' class="space-y-5">
            <div class="grid gap-5 lg:grid-cols-2">
                <div>
                    <div class="mb-2 flex items-center justify-between"><h3 class="font-semibold">Dönemler</h3><button type="button" data-add-period class="rounded-lg border border-line px-3 py-2 text-sm font-semibold hover:bg-bg">+ Dönem ekle</button></div>
                    <div data-period-list class="space-y-2"></div>
                </div>
                <div>
                    <div class="mb-2 flex items-center justify-between"><h3 class="font-semibold">Oda tipleri</h3><button type="button" data-add-room class="rounded-lg border border-line px-3 py-2 text-sm font-semibold hover:bg-bg">+ Oda tipi ekle</button></div>
                    <div data-room-list class="space-y-2"></div>
                </div>
            </div>
            <div class="overflow-x-auto"><table class="min-w-full text-sm"><thead data-matrix-head></thead><tbody data-matrix-body></tbody></table></div>
        </div>
    </section>

    <details class="rounded-2xl border border-line bg-surface p-5 shadow-sm sm:p-6" open>
        <summary class="cursor-pointer text-xl font-semibold">Açıklamalar ve koşullar</summary>
        <div class="mt-5 grid gap-4 md:grid-cols-2">
            @foreach (['general_notes' => 'Genel açıklamalar', 'cancellation_terms' => 'İptal / esnek fiyat notları', 'footer_notes' => 'Kapanış / teşekkür notu'] as $key => $label)
                <label class="block text-sm font-medium {{ $key === 'footer_notes' ? 'md:col-span-2' : '' }}">{{ $label }}
                    <textarea name="{{ $key }}" rows="3" class="mt-1 w-full rounded-lg border border-line bg-bg px-3 py-2.5">{{ $value($key) }}</textarea>
                </label>
            @endforeach
        </div>
    </details>

    <details class="rounded-2xl border border-line bg-surface p-5 shadow-sm sm:p-6">
        <summary class="cursor-pointer text-xl font-semibold">Firma ve banka bilgileri</summary>
        <p class="mt-2 text-sm text-tx-muted">Bu bilgiler teklif üzerinde saklanır; daha sonra marka bilgileri değişse de bu belge aynı kalır.</p>
        <div class="mt-5 grid gap-4 md:grid-cols-2">
            @foreach ([
                'hotel_name_snapshot' => 'Otel / marka adı', 'legal_name_snapshot' => 'Şirket unvanı',
                'tax_office_snapshot' => 'Vergi dairesi', 'tax_number_snapshot' => 'Vergi numarası',
                'phone_snapshot' => 'Telefon', 'email_snapshot' => 'E-posta',
                'bank_account_holder' => 'Hesap sahibi', 'bank_iban_try' => 'TL IBAN', 'bank_iban_eur' => 'EUR IBAN',
            ] as $key => $label)
                <label class="block text-sm font-medium">{{ $label }}
                    <input name="{{ $key }}" value="{{ $value($key) }}" class="mt-1 w-full rounded-lg border border-line bg-bg px-3 py-2.5">
                </label>
            @endforeach
            <label class="block text-sm font-medium md:col-span-2">Adres / fatura alt bilgisi
                <textarea name="address_snapshot" rows="2" class="mt-1 w-full rounded-lg border border-line bg-bg px-3 py-2.5">{{ $value('address_snapshot') }}</textarea>
            </label>
        </div>
    </details>
    <div class="flex flex-wrap gap-3 pb-6">
        <button type="submit" class="rounded-xl bg-acc px-6 py-3 font-semibold text-on-acc shadow-sm">Teklifi kaydet</button>
        <a href="{{ $offer ? route('offers.show', $offer) : route('offers.index') }}" class="rounded-xl border border-line px-6 py-3 font-semibold">Vazgeç</a>
    </div>
</form>
