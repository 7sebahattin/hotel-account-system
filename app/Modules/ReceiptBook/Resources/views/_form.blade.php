@csrf

<x-ui.errors />

@php
    // Düzenleme ekranında mevcut değerler; oluşturmada null.
    $e = $entry ?? null;
    $currencyValue = old('currency', $e?->currency?->value ?? 'TRY');
@endphp

<div class="grid gap-4 sm:grid-cols-2" data-receipt-book-form>
    <x-ui.input label="Tarih" name="receipt_date" type="date" required
                :value="old('receipt_date', $e?->receipt_date?->toDateString() ?? now()->toDateString())" />

    @if ($e)
        <x-ui.input label="Müşteri / Cari adı" name="customer_name" required maxlength="200"
                    :value="old('customer_name', $e->customer_name)" />
    @else
        <div class="relative" data-receipt-customer-search
             data-search-url="{{ route('receipt-book.customer-search') }}"
             data-create-url="{{ route('receipt-book.customer-create') }}">
            <x-ui.input label="Müşteri / Cari adı" name="customer_name" required maxlength="200"
                        autocomplete="off" role="combobox" aria-autocomplete="list"
                        aria-controls="receipt-customer-results" aria-expanded="false"
                        data-receipt-customer-input :value="old('customer_name')" />
            <input type="hidden" name="company_id" value="{{ old('company_id') }}" data-receipt-customer-id>
            <div id="receipt-customer-results" role="listbox" hidden
                 class="absolute z-20 mt-1 max-h-56 w-full overflow-y-auto rounded-lg border border-line bg-card"
                 data-receipt-customer-results></div>
            @can(\App\Modules\Authorization\Enums\Permission::CompaniesCreate->value)
                <div hidden data-receipt-customer-create-panel class="mt-2 flex flex-wrap items-center gap-2">
                    <select aria-label="Yeni cari türü" data-receipt-customer-type
                            class="min-h-11 rounded-lg border border-field bg-card px-3 text-tx">
                        <option value="individual">Bireysel hesap</option>
                        <option value="company">Firma cari hesabı</option>
                    </select>
                    <button type="button" data-receipt-customer-create
                            class="min-h-11 rounded-lg border border-field bg-card px-3 text-tx"></button>
                </div>
            @endcan
            <p class="mt-1 text-xs text-mut" role="status" aria-live="polite" data-receipt-customer-status></p>
            @error('company_id')
                <p class="mt-1 text-xs text-danger">{{ $message }}</p>
            @enderror
        </div>
    @endif

    <x-ui.input label="Tutar" name="amount" required inputmode="decimal" data-amount
                placeholder="Örn: 5.000,00" class="tabular-nums"
                :value="old('amount', $e ? str_replace('.', ',', $e->amount) : null)" hint="Ondalık için virgül kullanabilirsiniz: 1.250,50" />

    <x-ui.select label="Para birimi" name="currency" required data-currency>
        @foreach ($currencies as $currency)
            <option value="{{ $currency->value }}" @selected($currencyValue === $currency->value)>
                {{ $currency->value }} — {{ $currency->label() }}
            </option>
        @endforeach
    </x-ui.select>

    <div data-exchange-rate-group @if ($currencyValue === 'TRY') hidden @endif>
        <x-ui.input label="Kur (1 birim → TL)" name="exchange_rate_to_try" inputmode="decimal"
                    data-exchange-rate placeholder="51,20" class="tabular-nums"
                    :value="old('exchange_rate_to_try', $e?->exchange_rate_to_try !== null ? str_replace('.', ',', $e->exchange_rate_to_try) : null)"
                    hint="TL dışı para biriminde zorunludur." />
        <p class="mt-1 text-xs text-mut" data-exchange-rate-preview aria-live="polite"></p>
    </div>

    <x-ui.select label="Ödeme şekli" name="payment_method">
        <option value="">Belirtilmedi</option>
        @foreach ($methods as $method)
            <option value="{{ $method->value }}" @selected(old('payment_method', $e?->payment_method?->value) === $method->value)>
                {{ $method->label() }}
            </option>
        @endforeach
    </x-ui.select>

    <div class="sm:col-span-2">
        <x-ui.input label="Açıklama" name="description" maxlength="1000" :value="old('description', $e?->description)" />
    </div>
</div>

<x-ui.alert tone="info" class="mt-4">
    Bu makbuz yalnızca dokümantasyon/yazdırma amaçlıdır. <strong>Firma cari hesabını, fiziksel
    kasayı veya herhangi bir muhasebe kaydını ETKİLEMEZ.</strong> Gerçek tahsilat kaydı için
    "Tahsilatlar" (Cari Tahsilat) kullanılır.
</x-ui.alert>

<div class="mt-4 flex flex-wrap gap-3">
    <x-ui.button type="submit">{{ $e ? 'Güncelle' : 'Kaydet' }}</x-ui.button>
    <x-ui.button variant="ghost" :href="$e ? route('receipt-book.show', $e) : route('receipt-book.index')">Vazgeç</x-ui.button>
</div>
