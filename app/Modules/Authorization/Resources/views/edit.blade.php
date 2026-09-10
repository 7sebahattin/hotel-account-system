@extends('layouts.app')
@section('title', 'Rol düzenle')

@section('content')
    <x-ui.page-header :title="$role->label()" subtitle="Rol yetkilerini düzenle" />

    <form method="POST" action="{{ route('roles.update', $role) }}" class="mt-6 max-w-2xl">
        @method('PUT')

        <x-ui.card class="p-6">
            @include('authorization::_form')
        </x-ui.card>

        <div class="mt-4 flex flex-wrap gap-3">
            <x-ui.button type="submit">Güncelle</x-ui.button>
            <x-ui.button variant="ghost" :href="route('roles.index')">Vazgeç</x-ui.button>
        </div>
    </form>
@endsection
