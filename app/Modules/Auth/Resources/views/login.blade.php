@extends('layouts.guest')

@section('title', 'Giriş — '.config('app.name'))

@section('content')
    <h2 class="text-base font-semibold text-slate-900">Giriş yap</h2>
    <p class="mt-1 text-sm text-slate-600">Devam etmek için hesabınızla giriş yapın.</p>

    @if ($errors->any())
        <div class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
        @csrf

        <div>
            <label for="email" class="block text-sm font-medium text-slate-700">E-posta</label>
            <input id="email" name="email" type="email" inputmode="email" autocomplete="username"
                   value="{{ old('email') }}" required autofocus
                   class="mt-1 block min-h-11 w-full rounded-md border border-slate-300 px-3 text-sm shadow-sm
                          focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-slate-700">Şifre</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required
                   class="mt-1 block min-h-11 w-full rounded-md border border-slate-300 px-3 text-sm shadow-sm
                          focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
        </div>

        <label for="remember" class="flex items-center gap-2 text-sm text-slate-600">
            <input id="remember" name="remember" type="checkbox"
                   class="size-4 rounded border-slate-300 text-slate-900 focus:ring-slate-500">
            Beni hatırla
        </label>

        <button type="submit"
                class="min-h-11 w-full rounded-md bg-slate-900 px-4 text-sm font-medium text-white
                       hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2">
            Giriş yap
        </button>
    </form>
@endsection
