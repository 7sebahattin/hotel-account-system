@csrf

<x-ui.errors />

<div class="grid gap-4 sm:grid-cols-2" data-receipt-book-form>
    <x-ui.input label="Tarih" name="receipt_date" type="date" required
                :value="old('receipt_date', now()->toDateString())" />

    <x-ui.input label="Müşteri / Cari adı" name="customer_name" required maxlength="200"
                :value="old('customer_name')" />

    <x-ui.input label="Tutar" name="amount" required inputmode="decimal" data-amount
                placeholder="Örn: 5.000,00" class="tabular-nums"
                :value="old('amount')" hint="Ondalık için virgül kullanabilirsiniz: 1.250,50" />

    <x-ui.select label="Para birimi" name="currency" required data-currency>
        @foreach ($currencies as $currency)
            <option value="{{ $currency->value }}" @selected(old('currency', 'TRY') === $currency->value)>
                {{ $currency->value }} — {{ $currency->label() }}
            </option>
        @endforeach
    </x-ui.select>

    <x-ui.fx-input :currency="old('currency', 'TRY')" />

    <x-ui.select label="Ödeme şekli" name="payment_method">
        <option value="">Belirtilmedi</option>
        @foreach ($methods as $method)
            <option value="{{ $method->value }}" @selected(old('payment_method') === $method->value)>
                {{ $method->label() }}
            </option>
        @endforeach
    </x-ui.select>

    <div class="sm:col-span-2">
        <x-ui.input label="Açıklama" name="description" maxlength="1000" :value="old('description')" />
    </div>
</div>

<x-ui.alert tone="info" class="mt-4">
    Bu makbuz yalnızca dokümantasyon/yazdırma amaçlıdır. <strong>Firma cari hesabını, fiziksel
    kasayı veya herhangi bir muhasebe kaydını ETKİLEMEZ.</strong> Gerçek tahsilat kaydı için
    "Tahsilatlar" (Cari Tahsilat) kullanılır.
</x-ui.alert>

<div class="mt-4 flex flex-wrap gap-3">
    <x-ui.button type="submit">Kaydet</x-ui.button>
    <x-ui.button variant="ghost" :href="route('receipt-book.index')">Vazgeç</x-ui.button>
</div>
