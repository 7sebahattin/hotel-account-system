@extends('layouts.app')
@section('title', 'Kasa Hareketleri')

@section('content')
    <x-ui.page-header title="Kasa Hareketleri" :count="$movements->total()"
                      :subtitle="$canViewAll ? 'Tüm personelin kasa hareketleri' : 'Kendi kasa hareketleriniz'">
        <x-slot:actions>
            <x-ui.button variant="ghost" :href="route('cashbook.index')">Kasa Defteri</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    {{--
        Hızlı filtreler (kullanıcı talimatı Gün 12 §9): normal URL filtrelerinin
        KISAYOLUDUR, yeni bir sorgu mantığı YAZILMAZ — her chip aynı `q`/`type`/
        `date_from` parametrelerine giden sade bir bağlantıdır.
    --}}
    @php
        // Pill görünümü kasıtlı olarak <x-ui.button>'dan AYRIDIR: butonun
        // kendi `min-h-11` temel sınıfını dışarıdan küçültmeye çalışmak aynı
        // CSS özelliği için iki temel yardımcı sınıfı çakıştırırdı (bkz.
        // Gün 11 K-092'deki `hidden`/`inline-flex` hatası) — burada
        // bağımsız, kendi sınıflarını taşıyan sade bir bağlantı kullanılır.
        $chipClass = 'inline-flex min-h-9 items-center rounded-full border border-field bg-card px-3 text-xs text-tx hover:bg-well';
    @endphp

    <div class="mt-4 flex flex-wrap gap-2">
        <a class="{{ $chipClass }}"
           href="{{ route('cashbook.movements.index', ['date_from' => now()->toDateString(), 'date_to' => now()->toDateString()]) }}">
            Bugün
        </a>
        <a class="{{ $chipClass }}" href="{{ route('cashbook.movements.index', ['type' => 'expense']) }}">Giderler</a>
        <a class="{{ $chipClass }}"
           href="{{ route('cashbook.movements.index', ['type' => ['room_income', 'extra_income']]) }}">
            Gelirler
        </a>
        <a class="{{ $chipClass }}" href="{{ route('cashbook.movements.index', ['type' => 'cash_out']) }}">Ara Kasa</a>

        @if ($canViewAll)
            <a class="{{ $chipClass }}" href="{{ route('cashbook.movements.index', ['user_id' => auth()->id()]) }}">
                Benim Vardiyam
            </a>
        @endif
    </div>

    <x-ui.filter-panel :count="$filter->activeFilterCount()"
                       :clear-href="route('cashbook.movements.index')"
                       :per-page-options="$perPageOptions">
        <div class="sm:col-span-2">
            <x-ui.input label="Ara" name="q" type="search" :value="request('q')"
                        placeholder="Açıklama, alıcı/verici" />
        </div>

        <x-ui.select label="Tür" name="type">
            <option value="">Tümü</option>
            @foreach ($types as $type)
                <option value="{{ $type->value }}" @selected(request('type') === $type->value)>{{ $type->label() }}</option>
            @endforeach
        </x-ui.select>

        <x-ui.select label="Para birimi" name="currency">
            <option value="">Tümü</option>
            @foreach ($currencies as $currency)
                <option value="{{ $currency->value }}" @selected(request('currency') === $currency->value)>{{ $currency->value }}</option>
            @endforeach
        </x-ui.select>

        <x-ui.input label="Tarih (baş.)" name="date_from" type="date" :value="request('date_from')" />
        <x-ui.input label="Tarih (bit.)" name="date_to" type="date" :value="request('date_to')" />

        <x-ui.select label="Yön" name="direction_filter">
            <option value="">Tümü</option>
            <option value="in" @selected(request('direction_filter') === 'in')>Giriş</option>
            <option value="out" @selected(request('direction_filter') === 'out')>Çıkış</option>
        </x-ui.select>

        @if ($canViewAll)
            <x-ui.select label="Personel" name="user_id">
                <option value="">Tümü</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}" @selected((string) request('user_id') === (string) $user->id)>{{ $user->name }}</option>
                @endforeach
            </x-ui.select>
        @endif
    </x-ui.filter-panel>

    <x-ui.panel title="Hareketler" class="mt-4">
        @forelse ($movements as $movement)
            @include('cashbook::movements._row', ['movement' => $movement, 'canReverse' => false, 'showOwner' => $canViewAll])
        @empty
            <x-ui.empty class="m-4 border-0 bg-transparent py-8" :filtered="$filter->hasActiveFilters()"
                        :clear-href="route('cashbook.movements.index')" />
        @endforelse
    </x-ui.panel>

    <div class="mt-4">{{ $movements->links() }}</div>
@endsection
