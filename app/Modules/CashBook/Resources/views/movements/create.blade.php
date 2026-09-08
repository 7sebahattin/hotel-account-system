@extends('layouts.app')
@section('title', 'Kasa hareketi ekle')

@section('content')
    <x-ui.page-header title="Hareket ekle"
                      :subtitle="$session->openedBy?->name.' vardiyası'" />

    <x-ui.card class="mt-4">
        <form method="POST" action="{{ route('cashbook.movements.store') }}" class="space-y-4">
            @csrf
            <x-ui.errors />

            {{--
                Önce BÜYÜK tür seçenekleri (kullanıcı talimatı §12): mobilde tek
                elle, birkaç saniyede kayıt girilebilmeli. Radyo düğmeleri
                görsel olarak kart gibi durur ama gerçek <input type="radio">
                oldukları için klavye ve ekran okuyucu ile çalışır — JS gerekmez.
            --}}
            <fieldset>
                <legend class="block text-sm text-mut">
                    Hareket türü
                    <span class="text-acc-tx" aria-hidden="true">*</span>
                    <span class="sr-only">(zorunlu)</span>
                </legend>

                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                    @foreach ($types as $type)
                        @php $checked = old('type', $selectedType) === $type->value; @endphp

                        <label @class([
                            'flex min-h-11 cursor-pointer items-start gap-2.5 rounded-lg border p-3 transition-colors',
                            'border-acc-tx bg-acc-bg' => $checked,
                            'border-field hover:bg-well' => ! $checked,
                        ])>
                            <input type="radio" name="type" value="{{ $type->value }}"
                                   class="mt-0.5 size-4 shrink-0 accent-acc-tx"
                                   @checked($checked) required>

                            <span class="min-w-0">
                                <span class="block text-sm font-medium text-tx">{{ $type->label() }}</span>
                                <span class="block text-xs text-mut">{{ $type->hint() }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>

                @error('type')
                    <p class="mt-1 text-sm text-danger">{{ $message }}</p>
                @enderror
            </fieldset>

            <div class="grid gap-4 sm:grid-cols-2">
                {{-- inputmode=decimal: mobilde sayı klavyesi açılır. --}}
                <x-ui.input label="Tutar" name="amount" inputmode="decimal" required
                            placeholder="2.500,00" :value="old('amount')" class="tabular-nums" />

                <x-ui.select label="Para birimi" name="currency" required>
                    @foreach ($currencies as $currency)
                        <option value="{{ $currency->value }}" @selected(old('currency', 'TRY') === $currency->value)>
                            {{ $currency->value }} — {{ $currency->label() }}
                        </option>
                    @endforeach
                </x-ui.select>
            </div>

            <x-ui.input label="Açıklama" name="description" maxlength="255" :value="old('description')"
                        hint="Örn. 204 numaralı oda, mini bar, market alışverişi" />

            <x-ui.input label="Teslim alan" name="counterparty" maxlength="150" :value="old('counterparty')"
                        hint="Kasa çıkışında parayı teslim alan: Patron, ara kasa, banka…" />

            <div class="flex flex-wrap gap-2">
                <x-ui.button type="submit">Kaydet</x-ui.button>
                <x-ui.button variant="ghost" :href="route('cashbook.index')">Vazgeç</x-ui.button>
            </div>
        </form>
    </x-ui.card>
@endsection
