@extends('layouts.app')
@section('title', 'Konaklama düzenle')

@section('content')
    <x-ui.page-header :title="$stay->guest_name" subtitle="Konaklama düzenle" />

    <form method="POST" action="{{ route('stays.update', $stay) }}" class="mt-6 max-w-3xl">
        @method('PUT')

        <x-ui.card class="p-6">
            @include('stays::_form')
        </x-ui.card>

        <div class="mt-4 flex flex-wrap gap-3">
            <x-ui.button type="submit">Güncelle</x-ui.button>
            <x-ui.button variant="ghost" :href="route('stays.show', $stay)">Vazgeç</x-ui.button>
        </div>
    </form>
@endsection
