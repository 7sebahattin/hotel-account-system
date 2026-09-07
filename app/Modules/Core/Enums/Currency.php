<?php

declare(strict_types=1);

namespace App\Modules\Core\Enums;

/**
 * V1'de desteklenen para birimleri.
 *
 * Bu enum tek doğruluk kaynağıdır; config/hotel.php listesi buradan türetilir.
 * CLAUDE.md §6/L5: farklı para birimleri asla tek toplamda birleştirilmez ve
 * V1'de kur çevrimi yoktur.
 */
enum Currency: string
{
    case TRY = 'TRY';
    case EUR = 'EUR';
    case GBP = 'GBP';

    public function symbol(): string
    {
        return match ($this) {
            self::TRY => '₺',
            self::EUR => '€',
            self::GBP => '£',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::TRY => 'Türk Lirası',
            self::EUR => 'Euro',
            self::GBP => 'İngiliz Sterlini',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
