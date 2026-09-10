@extends('layouts.app')
@section('title', 'Yeni konaklama')

@section('content')
    <x-ui.page-header title="Yeni konaklama" />

    <form method="POST" action="{{ route('stays.store') }}" class="mt-6 max-w-3xl">
        <x-ui.card class="p-6">
            @include('stays::_form')
        </x-ui.card>

        <div class="mt-4 flex flex-wrap gap-3">
            <x-ui.button type="submit">Kaydet</x-ui.button>
            <x-ui.button variant="ghost" :href="route('stays.index')">Vazgeç</x-ui.button>
        </div>
    </form>
@endsection
