<?php

declare(strict_types=1);

namespace App\Modules\Stays\Enums;

/**
 * Konaklama ücretinin nasıl girildiği.
 *
 * Total: kullanıcı nihai toplamı girer, daily_rate boştur.
 * Daily: kullanıcı gecelik ücreti girer, toplam SUNUCUDA hesaplanır
 *        (amount = nights × daily_rate) ve formdan gelen amount yok sayılır.
 *
 * Her iki modda da `stays.amount` nihai toplam tutardır ve cari hareketine
 * yazılan değer odur.
 */
enum StayPricingMode: string
{
    case Total = 'total';
    case Daily = 'daily';

    public function label(): string
    {
        return match ($this) {
            self::Total => 'Toplam Tutar',
            self::Daily => 'Günlük Ücret',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
