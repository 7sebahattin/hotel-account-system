{{--
    PWA kurulabilirlik meta'ları — her iki layout (app/guest) tarafından
    paylaşılır, kopyalanmaz (CLAUDE.md §4.2 ilkesiyle AYNI, görünüm katmanına
    uygulanmış).
--}}
<link rel="manifest" href="{{ asset('manifest.json') }}">
<link rel="icon" href="{{ asset('icons/icon-192.png') }}">
<link rel="apple-touch-icon" href="{{ asset('icons/icon-192.png') }}">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Lemon">
