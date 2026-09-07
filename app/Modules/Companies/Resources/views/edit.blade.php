@extends('layouts.app')
@section('title', 'Firma düzenle')

@section('content')
    <x-ui.page-header :title="$company->name" subtitle="Firma bilgilerini düzenle" />

    <form method="POST" action="{{ route('companies.update', $company) }}" class="mt-6 max-w-3xl">
        @method('PUT')

        <x-ui.card class="p-6">
            @include('companies::_form')
        </x-ui.card>

        <div class="mt-4 flex flex-wrap gap-3">
            <x-ui.button type="submit">Güncelle</x-ui.button>
            <x-ui.button variant="ghost" :href="route('companies.show', $company)">Vazgeç</x-ui.button>
        </div>
    </form>
@endsection
