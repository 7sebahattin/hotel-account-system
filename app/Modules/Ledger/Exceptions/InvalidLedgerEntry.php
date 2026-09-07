<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Exceptions;

use InvalidArgumentException;

/** CLAUDE.md §6/L2: her harekette borç ve alacaktan tam olarak biri pozitiftir. */
class InvalidLedgerEntry extends InvalidArgumentException
{
    public static function amountNotPositive(): self
    {
        return new self('Cari hareket tutarı sıfırdan büyük olmalıdır.');
    }

    public static function missingActor(): self
    {
        return new self('Cari hareketi için işlemi yapan kullanıcı belirtilmelidir.');
    }
}
