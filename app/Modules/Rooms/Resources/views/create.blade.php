@extends('layouts.app')
@section('title', 'Yeni oda')

@section('content')
    <x-ui.page-header title="Yeni oda" />

    <form method="POST" action="{{ route('rooms.store') }}" class="mt-6 max-w-lg">
        <x-ui.card class="p-6">
            @include('rooms::_form')
        </x-ui.card>

        <div class="mt-4 flex flex-wrap gap-3">
            <x-ui.button type="submit">Kaydet</x-ui.button>
            <x-ui.button variant="ghost" :href="route('rooms.index')">Vazgeç</x-ui.button>
        </div>
    </form>
@endsection
