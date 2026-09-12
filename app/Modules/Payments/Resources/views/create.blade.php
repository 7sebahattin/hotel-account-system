@extends('layouts.app')
@section('title', 'Cari Tahsilat')

@section('content')
    <x-ui.page-header title="Cari Tahsilat" />

    <form method="POST" action="{{ route('payments.store') }}" class="mt-6 max-w-3xl"
          data-payment-allocation data-options-url="{{ route('payments.allocation-options') }}"
          data-selected-stays="{{ json_encode(old('stay_ids', [])) }}">
        @csrf

        <x-ui.card class="p-6">
            <x-ui.errors />

            <div class="grid gap-4 sm:grid-cols-2" data-payment-exchange-rate>
                <div class="sm:col-span-2">
                    <x-ui.select label="Cari hesap (firma / kişi)" name="company_id" required>
                        <option value="">Seçiniz…</option>
                        @foreach ($companies as $id => $name)
                            <option value="{{ $id }}"
                                @selected((string) old('company_id', $selectedCompanyId ?? '') === (string) $id)>
                                {{ $name }}
                            </option>
                        @endforeach
                    </x-ui.select>
                </div>

                <x-ui.input label="Tahsilat tarihi" name="payment_date" type="date" required
                            :value="now()->toDateString()" />

                <x-ui.select label="Ödemeyi yapan" name="payer_type" required>
                    <option value="account_holder" @selected(old('payer_type', 'account_holder') === 'account_holder')>Hesap sahibi (firma / kişi)</option>
                    <option value="individual" @selected(old('payer_type') === 'individual')>Kişi kendi ödüyor — şahsi ödeme</option>
                </x-ui.select>
                <div class="sm:col-span-2" data-payer-name>
                    <x-ui.input label="Ödeyen kişinin adı" name="payer_name" maxlength="200"
                                hint="Borç seçilen cari hesapta kalır; bu alan yalnızca ödemeyi kimin yaptığını belirtir." />
                </div>

                <x-ui.select label="Ödeme yöntemi" name="payment_method">
                    <option value="">Belirtilmedi</option>
                    @foreach ($methods as $method)
                        <option value="{{ $method->value }}" @selected(old('payment_method') === $method->value)>
                            {{ $method->label() }}
                        </option>
                    @endforeach
                </x-ui.select>

                <x-ui.input label="Tutar" name="amount" required inputmode="decimal" data-amount
                            placeholder="Örn: 5.000,00" class="tabular-nums"
                            hint="Ondalık için virgül kullanabilirsiniz: 1.250,50" />

                <x-ui.select label="Para birimi" name="currency" required data-currency>
                    @foreach ($currencies as $currency)
                        <option value="{{ $currency->value }}" @selected(old('currency', 'TRY') === $currency->value)>
                            {{ $currency->value }} — {{ $currency->label() }}
                        </option>
                    @endforeach
                </x-ui.select>

                {{--
                    Kur yalnızca YENİ tahsilatta istenir (v2.1.1-C §2) —
                    tahsilat zaten tamamen değişmez olduğu için düzenleme
                    formunda bu alan hiç YOKTUR.
                --}}
                <div data-exchange-rate-group @if (old('currency', 'TRY') === 'TRY') hidden @endif>
                    <x-ui.input label="Kur (1 birim → TL)" name="exchange_rate_to_try" inputmode="decimal"
                                data-exchange-rate placeholder="51,20" class="tabular-nums"
                                :value="old('exchange_rate_to_try')"
                                hint="TL dışı para biriminde zorunludur; işlem anındaki kur olarak SAKLANIR, sonradan güncellenmez." />
                    <p class="mt-1 text-xs text-mut" data-exchange-rate-preview aria-live="polite"></p>
                </div>

                <x-ui.input label="Referans no" name="reference_no" maxlength="50"
                            hint="Dekont / slip numarası." />

                <div class="sm:col-span-2">
                    <x-ui.input label="Açıklama" name="description" maxlength="255" />
                </div>

                <div class="sm:col-span-2 border-t border-line pt-4">
                    <x-ui.select label="Ödeme dağıtımı" name="allocation_mode" required>
                        <option value="oldest_first" @selected(old('allocation_mode', 'oldest_first') === 'oldest_first')>En eski konaklama borcundan başla</option>
                        <option value="selected" @selected(old('allocation_mode') === 'selected')>Belirli konaklamaları seç</option>
                    </x-ui.select>
                    <p class="mt-2 text-xs text-mut">Aynı para birimindeki borçlara uygulanır. Ödeme yetmezse kalan borç açık kalır; fazla ödeme seçilen cari hesapta avans olarak tutulur.</p>
                    <fieldset data-allocation-selection class="mt-3 space-y-2" hidden disabled>
                        <legend class="text-sm text-mut">Ödenecek konaklamalar</legend>
                        <p data-allocation-message class="text-sm text-mut" role="status"></p>
                        <div data-allocation-options class="max-h-80 space-y-2 overflow-y-auto"></div>
                        <button type="button" data-allocation-retry class="min-h-11 text-sm text-acc-tx underline">Listeyi yenile</button>
                    </fieldset>
                </div>
            </div>

            <x-ui.alert tone="info" class="mt-4">
                Kaydedildiğinde seçilen cari hesaba ödeme işlenir. Tutar, tarih, cari hesap,
                para birimi, kur ve ödeme yöntemi <strong>sonradan değiştirilemez</strong>; düzeltme için
                tahsilat iptal edilip yeniden girilir.
            </x-ui.alert>
        </x-ui.card>

        <div class="mt-4 flex flex-wrap gap-3">
            <x-ui.button type="submit">Kaydet</x-ui.button>
            <x-ui.button variant="ghost" :href="route('payments.index')">Vazgeç</x-ui.button>
        </div>
    </form>
@endsection
