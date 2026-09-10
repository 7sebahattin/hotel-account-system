@extends('layouts.app')
@section('title', 'Yeni kullanıcı')

@section('content')
    <x-ui.page-header title="Yeni kullanıcı" />

    <form method="POST" action="{{ route('users.store') }}" class="mt-6 max-w-lg">
        <x-ui.card class="p-6">
            @include('users::_form')
        </x-ui.card>

        <div class="mt-4 flex flex-wrap gap-3">
            <x-ui.button type="submit">Kaydet</x-ui.button>
            <x-ui.button variant="ghost" :href="route('users.index')">Vazgeç</x-ui.button>
        </div>
    </form>
@endsection
