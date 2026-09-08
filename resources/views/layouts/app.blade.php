<!DOCTYPE html>
<html lang="tr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Mobil tarayıcı çubuğu kenar çubuğuyla aynı yüzeyi kullanır. --}}
    <meta name="theme-color" content="#172033">
    <title>@yield('title', config('app.name'))</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-bg text-tx antialiased">
    <a href="#icerik"
       class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-acc focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-on-acc">
        İçeriğe atla
    </a>

    {{-- Mobil çekmece arka planı --}}
    <div data-sidebar-overlay class="fixed inset-0 z-30 hidden bg-nav/60 md:hidden" aria-hidden="true"></div>

    <aside id="kenar-cubugu" data-sidebar
           class="nav-surface fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col bg-nav transition-transform duration-200 md:translate-x-0">
        <div class="flex h-16 items-center justify-between gap-2 border-b border-nav-line px-4">
            <a href="{{ route('home') }}" class="flex min-h-11 min-w-0 items-center rounded-md">
                @include('partials.brand')
            </a>

            <button type="button" data-sidebar-close
                    class="-mr-2 flex size-11 shrink-0 items-center justify-center rounded-lg text-nav-mut hover:text-nav-tx md:hidden"
                    aria-label="Menüyü kapat">
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" class="size-5" aria-hidden="true">
                    <path d="M5 5l10 10M15 5L5 15" stroke-linecap="round"/>
                </svg>
            </button>
        </div>

        @include('partials.sidebar')
    </aside>

    <div class="md:pl-64">
        <header class="sticky top-0 z-20 flex h-14 items-center gap-2 border-b border-nav-line bg-nav px-2 md:hidden">
            <button type="button" data-sidebar-toggle
                    class="flex size-11 items-center justify-center rounded-lg text-nav-mut hover:text-nav-tx"
                    aria-label="Menüyü aç" aria-expanded="false" aria-controls="kenar-cubugu">
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" class="size-5" aria-hidden="true">
                    <path d="M3 5h14M3 10h14M3 15h14" stroke-linecap="round"/>
                </svg>
            </button>

            <span class="nav-surface min-w-0">@include('partials.brand', ['compact' => true])</span>
        </header>

        <main id="icerik" class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:py-8">
            @include('partials.flash')
            @yield('content')
        </main>
    </div>
</body>
</html>
