<?php

declare(strict_types=1);

namespace App\Modules\Core\Support;

/**
 * Kenar çubuğu ve alt gezinme çubuğunun ORTAK ikon kaynağı.
 *
 * Blade `@include` ile paylaşmak yerine sınıf sabiti kullanılır: bir include
 * içinde tanımlanan değişken üst kapsama geri sızmaz (Blade view'ları izole
 * render edilir), bu yüzden ikonlar burada, tek yerde tutulur.
 */
final class NavIcons
{
    /** @var array<string, string> */
    public const array PATHS = [
        'home' => 'M3 9l7-6 7 6v8a1 1 0 01-1 1h-4v-5H8v5H4a1 1 0 01-1-1V9z',
        'building' => 'M4 17V4a1 1 0 011-1h7a1 1 0 011 1v13M4 17h13M4 17H2m11 0h5V9a1 1 0 00-1-1h-4M6 6h2M6 9h2M6 12h2',
        'bed' => 'M3 16v-3h14v3M3 13V7m0 6h14M17 13v-2a2 2 0 00-2-2H9v4M6 9.5a1.5 1.5 0 100-3 1.5 1.5 0 000 3z',
        'cash' => 'M2 6h16v8H2V6zm8 5.5a1.5 1.5 0 100-3 1.5 1.5 0 000 3zM5 6v8m10-8v8',
        'safe' => 'M3 4h14a1 1 0 011 1v10a1 1 0 01-1 1H3a1 1 0 01-1-1V5a1 1 0 011-1zm7 6a2.5 2.5 0 100-5 2.5 2.5 0 000 5zm0 0v3M5 16v1m10-1v1',
        'ledger' => 'M5 3h9a1 1 0 011 1v13a1 1 0 01-1 1H5a1 1 0 01-1-1V4a1 1 0 011-1zm0 0v14M8 7h5M8 10h5M8 13h3',
        'chart' => 'M3 17h14M5 14v-3h2v3H5zm4 0V7h2v7H9zm4 0V4h2v10h-2zm4 0V9h2v5h-2z',
        'users' => 'M13 16v-1a3 3 0 00-3-3H6a3 3 0 00-3 3v1M8 9a3 3 0 100-6 3 3 0 000 6zm9 7v-1a3 3 0 00-2-2.8M13 3.2a3 3 0 010 5.6',
        'shield' => 'M10 2l6 2.5V9c0 4-2.6 7.2-6 9-3.4-1.8-6-5-6-9V4.5L10 2z',
        'log' => 'M4 4h12v12H4V4zm3 3h6M7 10h6M7 13h4',
        'more' => 'M3 5h14M3 10h14M3 15h14',
        'plus' => 'M10 4v12M4 10h12',
        'warning' => 'M8.57 3.2a1.65 1.65 0 012.86 0l6.28 10.9A1.65 1.65 0 0116.28 16.6H3.72a1.65 1.65 0 01-1.43-2.5L8.57 3.2zM10 8v3.2M10 13.8h.01',
        'swap' => 'M4 7h11l-3-3M16 13H5l3 3',
        // Pano (Finans) özet kartları ve son güncellemeler.
        'wallet' => 'M3 6a2 2 0 012-2h9v3M3 6v9a1 1 0 001 1h12a1 1 0 001-1V8a1 1 0 00-1-1H5a2 2 0 01-2-1zm11 5h.01',
        'arrow-down' => 'M10 3v13m0 0l-5-5m5 5l5-5',
        'calculator' => 'M5 2h10a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V3a1 1 0 011-1zm2 3h6v3H7V5zm0 6h.01M10 11h.01M13 11h.01M7 14h.01M10 14h.01M13 14h.01',
        'card' => 'M2 5h16v10H2V5zm0 3h16M5 12h3',
        'pencil' => 'M13.5 3.5l3 3L7 16H4v-3l9.5-9.5z',
        'chevron-right' => 'M8 5l5 5-5 5',
        // Liste ekranı (Konaklamalar) başlık, filtre ve satır ikonları.
        'chevron-down' => 'M6 8l4 4 4-4',
        'arrow-right' => 'M4 10h12m0 0l-4-4m4 4l-4 4',
        'printer' => 'M5 8V3h10v5M5 14H3V9a1 1 0 011-1h12a1 1 0 011 1v5h-2M6 12h8v5H6v-5z',
        'calendar' => 'M4 5h12a1 1 0 011 1v10a1 1 0 01-1 1H4a1 1 0 01-1-1V6a1 1 0 011-1zm-1 4h14M7 3v4m6-4v4',
        'search' => 'M9 15a6 6 0 100-12 6 6 0 000 12zm8 2l-3.5-3.5',
        'filter' => 'M3 4h14l-5.5 6.5V16l-3 1.5v-7L3 4z',
        'moon' => 'M16 12.5A7 7 0 017.5 4a7 7 0 108.5 8.5z',
        'sort' => 'M6 4v12m0 0l-3-3m3 3l3-3M14 16V4m0 0l-3 3m3-3l3 3',
        'refresh' => 'M16 10a6 6 0 11-1.8-4.3M16 3v4h-4',
        'dots-vertical' => 'M10 5h.01M10 10h.01M10 15h.01',
        'check-circle' => 'M10 18a8 8 0 100-16 8 8 0 000 16zm-3.5-8l2.5 2.5 4.5-5',
        'clock' => 'M10 18a8 8 0 100-16 8 8 0 000 16zm0-12v4l3 2',
    ];

    public static function path(string $icon): string
    {
        return self::PATHS[$icon] ?? self::PATHS['home'];
    }
}
