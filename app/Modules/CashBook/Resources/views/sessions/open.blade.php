@extends('layouts.app')
@section('title', 'Kasayı aç')

@section('content')
    <x-ui.page-header title="Kasayı aç"
                      subtitle="Vardiya başındaki parayı para birimi bazında girin" />

    <x-ui.card class="mt-4">
        <form method="POST" action="{{ route('cashbook.sessions.store') }}" class="space-y-4">
            @csrf
            <x-ui.errors />

            @include('cashbook::_amount-fields', [
                'currencies' => $currencies,
                'legend' => 'Açılış bakiyesi',
                'hint' => 'Kasada hiç para yoksa boş bırakabilirsiniz. Girilen tutarlar açılış hareketi olarak deftere yazılır.',
            ])

            <x-ui.input label="Not" name="opening_note" maxlength="255" :value="old('opening_note')" />

            <div class="flex flex-wrap gap-2">
                <x-ui.button type="submit">Kasayı aç</x-ui.button>
                <x-ui.button variant="ghost" :href="route('cashbook.index')">Vazgeç</x-ui.button>
            </div>
        </form>
    </x-ui.card>
@endsection
