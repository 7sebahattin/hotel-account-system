<?php

declare(strict_types=1);

namespace App\Modules\CashBook\Enums;

enum CashTransferStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Kabul bekliyor',
            self::Accepted => 'Kabul edildi',
            self::Cancelled => 'İptal edildi',
        };
    }
}
