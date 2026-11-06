<?php

declare(strict_types=1);

namespace App\Modules\Core\Trash;

use Carbon\CarbonInterface;

/** Çöp kutusu ekranında tek bir satır. Modüllerin kendi modelinden bağımsız, salt okunur özet. */
final readonly class TrashItem
{
    public function __construct(
        public int $id,
        public string $title,
        public string $detail,
        public ?CarbonInterface $trashedAt,
        public ?string $trashedBy = null,
        public ?string $url = null,
    ) {}
}
