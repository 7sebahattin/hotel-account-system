<?php

declare(strict_types=1);

namespace App\Modules\CashBook\Enums;

/**
 * Hareketin fiziksel kasaya etkisi.
 *
 * Tutar her zaman POZİTİFtir; işaret buradan gelir. Ledger'daki debit/credit
 * ayrımının kasa karşılığıdır: satırın anlamı tutarın işaretine değil, ayrı
 * bir kolona bakılarak bulunur.
 */
enum CashMovementDirection: string
{
    case In = 'in';
    case Out = 'out';

    public function label(): string
    {
        return match ($this) {
            self::In => 'Giriş',
            self::Out => 'Çıkış',
        };
    }

    /** Beklenen kasa toplamındaki işaret. */
    public function sign(): int
    {
        return $this === self::In ? 1 : -1;
    }

    public function opposite(): self
    {
        return $this === self::In ? self::Out : self::In;
    }
}
