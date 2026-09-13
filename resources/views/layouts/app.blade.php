<!DOCTYPE html>
<html lang="tr" class="h-full">
<head>
    <meta charset="utf-8">
    {{-- viewport-fit=cover: env(safe-area-inset-*) olmadan iPhone/Android çentik
         payı sıfır döner — standalone (PWA) modda alt gezinme çubuğu cihaz ev
         göstergesinin ALTINDA kalırdı (kullanıcı talimatı §3). --}}
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Mobil tarayıcı çubuğu kenar çubuğuyla aynı yüzeyi kullanır. --}}
    <meta name="theme-color" content="#172033">
    @include('partials.pwa-head')
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
        {{--
            Mobil üst çubuk (PWA + mobil temizlik turu). Artık yalnızca marka
            taşır — hamburger ve "+" KALDIRILDI (kullanıcı talimatı §2):
            birincil gezinme alt çubuktur, "Daha Fazla" ikincil gezinmeyi
            zaten karşılar; üstteki hamburger bu yüzden TEKRARDI. "+" (Hızlı
            İşlem) da kaldırıldı — her kısayolun kendi index sayfasında zaten
            aynı "oluştur" butonu vardır (ör. Konaklamalar → "Yeni konaklama"),
            bu yüzden hiçbir yetenek kaybolmaz, yalnızca çubuk sadeleşir.
            Masaüstü Lemon Bar'a (aşağıda) DOKUNULMADI.
        --}}
        <header class="pwa-safe-top sticky top-0 z-20 flex h-12 items-center border-b border-nav-line bg-nav px-4 md:hidden">
            <span class="nav-surface min-w-0">@include('partials.brand', ['compact' => true])</span>
        </header>

        {{--
            Masaüstü Lemon Bar. Sidebar zaten marka + kullanıcı kimliğini
            taşıdığı için burada TEKRAR EDİLMEZ (kullanıcı talimatı §9) —
            yalnızca bağlam (Operasyon/Finans sekmesi, sayfa doldurursa),
            hızlı işlem ve kritik durum vardır. Hiçbiri doldurulmadıysa
            (çoğu ekranda) çubuk boş bir bağlam şeridi olarak kalır.
        --}}
        <header class="sticky top-0 z-20 hidden h-14 items-center gap-4 border-b border-line bg-card px-6 md:flex">
            <div class="flex min-w-0 flex-1 items-center gap-4">
                @yield('lemon-bar-tabs')
                @yield('lemon-bar-status')
            </div>

            @include('partials.quick-action')
        </header>

        <main id="icerik" class="mx-auto w-full max-w-7xl px-4 pb-28 pt-6 sm:px-6 md:pb-6 lg:pb-8 lg:pt-8">
            @include('partials.flash')
            @yield('content')
        </main>
    </div>

    @include('partials.bottom-nav')
</body>
</html>
