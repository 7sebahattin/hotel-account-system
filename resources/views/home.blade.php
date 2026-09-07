@extends('layouts.app')
@section('title', 'Ana sayfa — '.config('app.name'))

@section('content')
    <x-ui.page-header title="Ana sayfa" :subtitle="'Hoş geldiniz, '.auth()->user()->name" />

    <x-ui.card class="mt-6 p-8 text-center">
        <p class="text-sm text-mut">
            Bu ekran Dashboard modülü ile doldurulacaktır (CLAUDE.md §14, Gün 9).
        </p>
    </x-ui.card>
@endsection
