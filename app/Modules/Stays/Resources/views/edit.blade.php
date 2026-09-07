@extends('layouts.app')
@section('title', 'Konaklama düzenle')

@section('content')
    <h1 class="text-lg font-semibold">{{ $stay->guest_name }}</h1>

    <form method="POST" action="{{ route('stays.update', $stay) }}"
          class="mt-6 max-w-3xl rounded-xl border border-slate-200 bg-white p-6">
        @method('PUT')
        @include('stays::_form')

        <div class="mt-6 flex gap-3">
            <button type="submit" class="min-h-11 rounded-md bg-slate-900 px-4 text-sm font-medium text-white hover:bg-slate-800">Güncelle</button>
            <a href="{{ route('stays.show', $stay) }}" class="inline-flex min-h-11 items-center px-4 text-sm text-slate-600 hover:underline">Vazgeç</a>
        </div>
    </form>
@endsection
