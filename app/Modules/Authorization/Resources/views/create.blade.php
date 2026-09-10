@extends('layouts.app')
@section('title', 'Yeni rol')

@section('content')
    <x-ui.page-header title="Yeni rol" />

    <form method="POST" action="{{ route('roles.store') }}" class="mt-6 max-w-2xl">
        <x-ui.card class="p-6">
            @include('authorization::_form', ['assigned' => []])
        </x-ui.card>

        <div class="mt-4 flex flex-wrap gap-3">
            <x-ui.button type="submit">Kaydet</x-ui.button>
            <x-ui.button variant="ghost" :href="route('roles.index')">Vazgeç</x-ui.button>
        </div>
    </form>
@endsection
