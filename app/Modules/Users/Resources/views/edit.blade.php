@extends('layouts.app')
@section('title', 'Kullanıcı düzenle')

@section('content')
    <x-ui.page-header :title="$user->name" subtitle="Kullanıcı düzenle" />

    <form method="POST" action="{{ route('users.update', $user) }}" class="mt-6 max-w-lg">
        @method('PUT')

        <x-ui.card class="p-6">
            @include('users::_form')
        </x-ui.card>

        <div class="mt-4 flex flex-wrap gap-3">
            <x-ui.button type="submit">Güncelle</x-ui.button>
            <x-ui.button variant="ghost" :href="route('users.index')">Vazgeç</x-ui.button>
        </div>
    </form>
@endsection
