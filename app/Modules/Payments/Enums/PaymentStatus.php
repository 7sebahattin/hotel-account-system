<?php

declare(strict_types=1);

namespace App\Modules\Payments\Enums;

/**
 * V1'de yalnızca iki durum vardır.
 *
 * Tahsilat SİLİNMEZ; yanlış tahsilat iptal edilir ve ters cari kaydı üretir.
 * İptal geri alınamaz — `restore` akışı yoktur.
 */
enum PaymentStatus: string
{
    case Active = 'active';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Cancelled => 'İptal',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
