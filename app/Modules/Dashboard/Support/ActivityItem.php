<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Support;

use Illuminate\Support\Carbon;

/** Pano "Son Güncellemeler" satırı — denetim kaydından türetilir. */
final readonly class ActivityItem
{
    public function __construct(
        public string $title,
        public ?string $userName,
        public ?Carbon $at,
        // NavIcons anahtarı ve KPI ton adı (amber/green/blue/purple/rose).
        public string $icon,
        public string $tone,
    ) {}
}
