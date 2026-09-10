@extends('layouts.app')
@section('title', 'Oda düzenle')

@section('content')
    <x-ui.page-header :title="'Oda düzenle — '.$room->room_no" />

    <form method="POST" action="{{ route('rooms.update', $room) }}" class="mt-6 max-w-lg">
        @method('PUT')
        <x-ui.card class="p-6">
            @include('rooms::_form')
        </x-ui.card>

        <div class="mt-4 flex flex-wrap gap-3">
            <x-ui.button type="submit">Kaydet</x-ui.button>
            <x-ui.button variant="ghost" :href="route('rooms.index')">Vazgeç</x-ui.button>
        </div>
    </form>
@endsection
