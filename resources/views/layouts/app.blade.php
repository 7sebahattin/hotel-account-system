<!DOCTYPE html>
<html lang="tr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex h-full flex-col bg-slate-100 text-slate-900 antialiased">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex h-14 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6">
            <a href="{{ route('home') }}" class="font-semibold tracking-tight">
                {{ config('app.name') }}
            </a>

            @auth
                <div class="hidden md:block">
                    @include('partials.nav')
                </div>

                <div class="flex items-center gap-3">
                    <span class="hidden text-sm text-slate-600 sm:inline">
                        {{ auth()->user()->name }}
                    </span>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                                class="min-h-11 rounded-md px-3 text-sm font-medium text-slate-700 hover:bg-slate-100">
                            Çıkış
                        </button>
                    </form>
                </div>
            @endauth
        </div>
    </header>

    @auth
        <div class="border-b border-slate-200 bg-white px-4 pb-2 md:hidden sm:px-6">
            @include('partials.nav')
        </div>
    @endauth

    <main class="mx-auto w-full max-w-7xl flex-1 px-4 py-6 sm:px-6">
        @include('partials.flash')
        @yield('content')
    </main>
</body>
</html>
