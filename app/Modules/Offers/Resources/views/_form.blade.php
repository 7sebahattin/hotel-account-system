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
<form method="POST" action="{{ $offer ? route('offers.update', $offer) : route('offers.store') }}" class="offer-form mt-6 space-y-5">
    @csrf
    @if ($offer) @method('PUT') @endif
    <x-ui.errors />

    <section class="rounded-xl border border-line bg-card p-5 sm:p-6" aria-labelledby="offer-general-heading">
        <div class="mb-6 border-b border-line pb-4">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-acc-tx">01 / Genel bilgiler</p>
            <h2 id="offer-general-heading" class="mt-1 text-xl font-semibold text-tx">Teklifin temeli</h2>
            <p class="mt-1 text-sm text-mut">Müşteri, tarih ve belgenin başlığı.</p>
        </div>
        <div class="grid gap-4 md:grid-cols-2">
            <label class="block text-sm font-medium">Cari / müşteri
                <select name="company_id" class="mt-1 w-full rounded-lg border border-line bg-bg px-3 py-2.5">
                    <option value="">Serbest müşteri adı kullan</option>
                    @foreach ($companies as $id => $name)
                        <option value="{{ $id }}" @selected((string) $value('company_id') === (string) $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block text-sm font-medium">Müşteri adı <span class="font-normal text-mut">(cari seçiliyse boş bırakılabilir)</span>
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
        <div class="my-6 border-t border-line pt-5">
            <h3 class="font-semibold text-tx">Belge başlığı</h3>
            <p class="mt-1 text-sm text-mut">Bu metin müşterinin göreceği teklifin üst bölümünde yer alır.</p>
        </div>
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

    <section class="rounded-xl border border-acc/40 bg-card p-5 sm:p-6" aria-labelledby="offer-matrix-heading">
        <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
            <div><p class="text-xs font-semibold uppercase tracking-[0.16em] text-acc-tx">02 / Fiyat tablosu</p><h2 id="offer-matrix-heading" class="mt-1 text-xl font-semibold text-tx">Dönem × oda tipi</h2></div>
            <p class="max-w-xs text-sm text-mut">Dönemler sütun, oda tipleri satırdır. Boş fiyatlar belgede “—” görünür.</p>
        </div>
        <div data-offer-matrix data-initial='@json($matrix)' class="space-y-5">
            <div class="grid gap-5 lg:grid-cols-2">
                <div>
                    <div class="mb-2 flex flex-wrap items-center justify-between gap-2"><h3 class="font-semibold text-tx">Dönemler</h3><button type="button" data-add-period class="min-h-11 rounded-lg border border-field px-3 text-sm font-semibold text-acc-tx hover:bg-acc-bg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-acc-tx">+ Dönem ekle</button></div>
                    <div data-period-list class="space-y-2"></div>
                </div>
                <div>
                    <div class="mb-2 flex flex-wrap items-center justify-between gap-2"><h3 class="font-semibold text-tx">Oda tipleri</h3><button type="button" data-add-room class="min-h-11 rounded-lg border border-field px-3 text-sm font-semibold text-acc-tx hover:bg-acc-bg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-acc-tx">+ Oda tipi ekle</button></div>
                    <div data-room-list class="space-y-2"></div>
                </div>
            </div>
            <div class="min-w-0">
                <h3 class="mb-2 font-semibold text-tx">Fiyat matrisi</h3>
                <p class="mb-3 text-xs text-mut">Fiyatlar yukarıda seçilen para birimindedir. Dar ekranda yalnız tablo yatay kayar.</p>
                <div class="max-w-full overflow-x-auto rounded-lg border border-line"><table class="min-w-full text-sm"><thead data-matrix-head></thead><tbody data-matrix-body></tbody></table></div>
            </div>
        </div>
    </section>

    <section class="rounded-xl border border-line bg-card p-5 sm:p-6" aria-labelledby="offer-terms-heading">
        <div class="border-b border-line pb-4"><p class="text-xs font-semibold uppercase tracking-[0.16em] text-acc-tx">03 / Koşullar ve açıklamalar</p><h2 id="offer-terms-heading" class="mt-1 text-xl font-semibold text-tx">Teklif koşulları</h2></div>
        <div class="mt-5 grid gap-4 md:grid-cols-2">
            @foreach (['general_notes' => 'Genel açıklamalar', 'cancellation_terms' => 'İptal / esnek fiyat notları', 'footer_notes' => 'Kapanış / teşekkür notu'] as $key => $label)
                <label class="block text-sm font-medium {{ $key === 'footer_notes' ? 'md:col-span-2' : '' }}">{{ $label }}
                    <textarea name="{{ $key }}" rows="3" class="mt-1 w-full rounded-lg border border-line bg-bg px-3 py-2.5">{{ $value($key) }}</textarea>
                </label>
            @endforeach
        </div>
    </section>

    <section class="rounded-xl border border-line bg-card p-5 sm:p-6" aria-labelledby="offer-company-heading">
        <div class="border-b border-line pb-4"><p class="text-xs font-semibold uppercase tracking-[0.16em] text-acc-tx">04 / Firma, banka ve alt bilgiler</p><h2 id="offer-company-heading" class="mt-1 text-xl font-semibold text-tx">Kurumsal bilgiler</h2><p class="mt-1 text-sm text-mut">Bu bilgiler teklifte saklanır; sonraki değişiklikler belgeyi etkilemez.</p></div>
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
    </section>
    <div class="flex flex-wrap items-center gap-3 border-t border-line pb-6 pt-5">
        <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-acc px-6 text-sm font-semibold text-on-acc hover:bg-acc-strong focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-acc-tx">Teklifi kaydet</button>
        <a href="{{ $offer ? route('offers.show', $offer) : route('offers.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-field px-5 text-sm font-semibold text-tx hover:bg-well focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-acc-tx">Vazgeç</a>
    </div>
</form>
