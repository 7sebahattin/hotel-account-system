@extends('layouts.app')
@section('title', 'Kullanıcı düzenle')

@section('content')
    <h1 class="text-lg font-semibold">{{ $user->name }}</h1>

    <form method="POST" action="{{ route('users.update', $user) }}"
          class="mt-6 max-w-lg rounded-xl border border-slate-200 bg-white p-6">
        @method('PUT')
        @include('users::_form')

        <div class="mt-6 flex gap-3">
            <button type="submit" class="min-h-11 rounded-md bg-slate-900 px-4 text-sm font-medium text-white hover:bg-slate-800">Güncelle</button>
            <a href="{{ route('users.index') }}" class="inline-flex min-h-11 items-center px-4 text-sm text-slate-600 hover:underline">Vazgeç</a>
        </div>
    </form>
@endsection
