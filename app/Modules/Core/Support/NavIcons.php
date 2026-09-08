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
        'ledger' => 'M5 3h9a1 1 0 011 1v13a1 1 0 01-1 1H5a1 1 0 01-1-1V4a1 1 0 011-1zm0 0v14M8 7h5M8 10h5M8 13h3',
        'users' => 'M13 16v-1a3 3 0 00-3-3H6a3 3 0 00-3 3v1M8 9a3 3 0 100-6 3 3 0 000 6zm9 7v-1a3 3 0 00-2-2.8M13 3.2a3 3 0 010 5.6',
        'shield' => 'M10 2l6 2.5V9c0 4-2.6 7.2-6 9-3.4-1.8-6-5-6-9V4.5L10 2z',
        'log' => 'M4 4h12v12H4V4zm3 3h6M7 10h6M7 13h4',
        'more' => 'M3 5h14M3 10h14M3 15h14',
    ];

    public static function path(string $icon): string
    {
        return self::PATHS[$icon] ?? self::PATHS['home'];
    }
}
