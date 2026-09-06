@extends('layouts.app')

@section('title', 'Ana sayfa — '.config('app.name'))

@section('content')
    <h1 class="text-lg font-semibold text-slate-900">Hoş geldiniz, {{ auth()->user()->name }}</h1>

    <div class="mt-6 rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center">
        <p class="text-sm text-slate-600">
            Bu ekran Dashboard modülü ile doldurulacaktır (CLAUDE.md §14, Gün 9).
        </p>
    </div>
@endsection
