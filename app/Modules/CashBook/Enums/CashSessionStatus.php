<?php

declare(strict_types=1);

namespace App\Modules\CashBook\Enums;

enum CashSessionStatus: string
{
    case Open = 'open';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Açık',
            self::Closed => 'Kapalı',
        };
    }
}
