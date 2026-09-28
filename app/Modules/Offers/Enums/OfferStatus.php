<?php

declare(strict_types=1);

namespace App\Modules\Offers\Enums;

enum OfferStatus: string
{
    case Draft = 'draft';
    case Ready = 'ready';
    case Sent = 'sent';
    case Approved = 'approved';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Taslak',
            self::Ready => 'Hazır',
            self::Sent => 'Gönderildi',
            self::Approved => 'Onaylandı',
            self::Cancelled => 'İptal',
        };
    }
}
