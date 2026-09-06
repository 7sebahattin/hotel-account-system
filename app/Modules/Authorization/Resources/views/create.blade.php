@extends('layouts.app')
@section('title', 'Yeni rol')

@section('content')
    <h1 class="text-lg font-semibold">Yeni rol</h1>

    <form method="POST" action="{{ route('roles.store') }}"
          class="mt-6 max-w-2xl rounded-xl border border-slate-200 bg-white p-6">
        @include('authorization::_form', ['assigned' => []])

        <div class="mt-6 flex gap-3">
            <button type="submit" class="min-h-11 rounded-md bg-slate-900 px-4 text-sm font-medium text-white hover:bg-slate-800">Kaydet</button>
            <a href="{{ route('roles.index') }}" class="inline-flex min-h-11 items-center px-4 text-sm text-slate-600 hover:underline">Vazgeç</a>
        </div>
    </form>
@endsection
