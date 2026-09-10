<?php

declare(strict_types=1);

namespace App\Modules\CashBook\Exceptions;

/**
 * Kasa hareketi append-only'dir (kullanıcı talimatı §3).
 */
class CashMovementIsImmutable extends CashBookException
{
    public static function cannotUpdate(): self
    {
        return new self('Kasa hareketi değiştirilemez; düzeltme ters hareket ile yapılır.');
    }

    public static function cannotDelete(): self
    {
        return new self('Kasa hareketi silinemez; düzeltme ters hareket ile yapılır.');
    }
}
