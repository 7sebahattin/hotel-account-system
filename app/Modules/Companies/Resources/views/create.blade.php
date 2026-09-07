@extends('layouts.app')
@section('title', 'Yeni firma')

@section('content')
    <x-ui.page-header title="Yeni firma" />

    <form method="POST" action="{{ route('companies.store') }}" class="mt-6 max-w-3xl">
        <x-ui.card class="p-6">
            @include('companies::_form')
        </x-ui.card>

        <div class="mt-4 flex flex-wrap gap-3">
            <x-ui.button type="submit">Kaydet</x-ui.button>
            <x-ui.button variant="ghost" :href="route('companies.index')">Vazgeç</x-ui.button>
        </div>
    </form>
@endsection
