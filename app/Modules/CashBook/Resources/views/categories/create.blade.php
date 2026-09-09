@extends('layouts.app')
@section('title', 'Yeni kasa hareket türü')

@section('content')
    <x-ui.page-header title="Yeni kasa hareket türü" />

    <form method="POST" action="{{ route('cashbook.categories.store') }}" class="mt-6 max-w-2xl">
        @csrf

        <x-ui.card class="p-6 space-y-4">
            <x-ui.errors />

            <x-ui.input label="Ad" name="name" required maxlength="100" :value="old('name')"
                        hint="Personelin kasa hareketi formunda göreceği ad. Örn: Mini Bar, Personel Yemek." />

            <x-ui.select label="Sistem davranışı" name="system_type" required
                         hint="Oluşturulduktan sonra değiştirilemez.">
                <option value="">Seçiniz…</option>
                @foreach ($systemTypes as $type)
                    <option value="{{ $type->value }}" @selected(old('system_type') === $type->value)>
                        {{ $type->label() }} — {{ $type->hint() }}
                    </option>
                @endforeach
            </x-ui.select>

            <x-ui.checkbox label="Bu tür için teslim alan bilgisi zorunlu olsun"
                           name="requires_recipient" :checked="old('requires_recipient', false)" />

            <x-ui.input label="Sıra" name="sort_order" type="number" min="0" :value="old('sort_order', 0)"
                        hint="Listede/formda gösterilme sırası (küçük önce)." />
        </x-ui.card>

        <div class="mt-4 flex flex-wrap gap-3">
            <x-ui.button type="submit">Kaydet</x-ui.button>
            <x-ui.button variant="ghost" :href="route('cashbook.categories.index')">Vazgeç</x-ui.button>
        </div>
    </form>
@endsection
