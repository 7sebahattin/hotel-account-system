<!DOCTYPE html>
<html lang="tr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0f172a">
    <title>@yield('title', config('app.name'))</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-bg text-tx antialiased">
    <a href="#icerik"
       class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-acc focus:px-4 focus:py-2 focus:text-sm focus:font-bold focus:text-[#111]">
        İçeriğe atla
    </a>

    {{-- Mobil çekmece arka planı --}}
    <div data-sidebar-overlay class="fixed inset-0 z-30 hidden bg-black/60 md:hidden" aria-hidden="true"></div>

    <aside id="kenar-cubugu" data-sidebar
           class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col border-r border-line bg-card transition-transform duration-200 md:translate-x-0">
        <div class="flex h-14 items-center justify-between border-b border-line px-4">
            <a href="{{ route('home') }}" class="truncate text-sm font-medium text-tx">
                {{ config('app.name') }}
            </a>

            <button type="button" data-sidebar-close
                    class="-mr-2 flex size-11 items-center justify-center rounded-lg text-mut hover:text-tx md:hidden"
                    aria-label="Menüyü kapat">
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" class="size-5" aria-hidden="true">
                    <path d="M5 5l10 10M15 5L5 15" stroke-linecap="round"/>
                </svg>
            </button>
        </div>

        @include('partials.sidebar')
    </aside>

    <div class="md:pl-64">
        <header class="sticky top-0 z-20 flex h-14 items-center gap-2 border-b border-line bg-bg px-2 md:hidden">
            <button type="button" data-sidebar-toggle
                    class="flex size-11 items-center justify-center rounded-lg text-mut hover:text-tx"
                    aria-label="Menüyü aç" aria-expanded="false" aria-controls="kenar-cubugu">
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" class="size-5" aria-hidden="true">
                    <path d="M3 5h14M3 10h14M3 15h14" stroke-linecap="round"/>
                </svg>
            </button>

            <span class="truncate text-sm font-medium">{{ config('app.name') }}</span>
        </header>

        <main id="icerik" class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6">
            @include('partials.flash')
            @yield('content')
        </main>
    </div>
</body>
</html>
