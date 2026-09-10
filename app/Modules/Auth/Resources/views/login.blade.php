@extends('layouts.guest')

@section('title', 'Giriş — '.config('app.name'))

@section('content')
    <h2 class="text-base font-medium text-tx">Giriş yap</h2>
    <p class="mt-1 text-sm text-mut">Devam etmek için hesabınızla giriş yapın.</p>

    @if ($errors->any())
        <x-ui.alert tone="danger" class="mt-4">{{ $errors->first() }}</x-ui.alert>
    @endif

    <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
        @csrf

        <x-ui.input label="E-posta" name="email" type="email" required
                    inputmode="email" autocomplete="username" autofocus />

        <x-ui.input label="Şifre" name="password" type="password" required
                    autocomplete="current-password" />

        <x-ui.checkbox label="Beni hatırla" name="remember" :checked="old('remember')" />

        <x-ui.button type="submit" block>Giriş yap</x-ui.button>
    </form>
@endsection
