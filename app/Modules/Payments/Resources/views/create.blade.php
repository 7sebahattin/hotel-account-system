@extends('layouts.app')
@section('title', 'Yeni tahsilat')

@section('content')
    <x-ui.page-header title="Yeni tahsilat" />

    <form method="POST" action="{{ route('payments.store') }}" class="mt-6 max-w-3xl">
        @csrf

        <x-ui.card class="p-6">
            <x-ui.errors />

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <x-ui.select label="Firma" name="company_id" required>
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

                <x-ui.select label="Ödeme yöntemi" name="payment_method">
                    <option value="">Belirtilmedi</option>
                    @foreach ($methods as $method)
                        <option value="{{ $method->value }}" @selected(old('payment_method') === $method->value)>
                            {{ $method->label() }}
                        </option>
                    @endforeach
                </x-ui.select>

                <x-ui.input label="Tutar" name="amount" required inputmode="decimal"
                            placeholder="5000,00" class="tabular-nums" />

                <x-ui.select label="Para birimi" name="currency" required>
                    @foreach ($currencies as $currency)
                        <option value="{{ $currency->value }}" @selected(old('currency', 'TRY') === $currency->value)>
                            {{ $currency->value }} — {{ $currency->label() }}
                        </option>
                    @endforeach
                </x-ui.select>

                <x-ui.input label="Referans no" name="reference_no" maxlength="50"
                            hint="Dekont / slip numarası." />

                <div class="sm:col-span-2">
                    <x-ui.input label="Açıklama" name="description" maxlength="255" />
                </div>
            </div>

            <x-ui.alert tone="info" class="mt-4">
                Kaydedildiğinde firmanın carisine alacak hareketi düşer. Tutar, tarih, firma,
                para birimi ve ödeme yöntemi <strong>sonradan değiştirilemez</strong>; düzeltme için
                tahsilat iptal edilip yeniden girilir.
            </x-ui.alert>
        </x-ui.card>

        <div class="mt-4 flex flex-wrap gap-3">
            <x-ui.button type="submit">Kaydet</x-ui.button>
            <x-ui.button variant="ghost" :href="route('payments.index')">Vazgeç</x-ui.button>
        </div>
    </form>
@endsection
