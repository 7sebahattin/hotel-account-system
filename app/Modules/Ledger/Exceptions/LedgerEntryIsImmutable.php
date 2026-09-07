<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Exceptions;

use RuntimeException;

/** CLAUDE.md §6/L1: cari hareketleri değiştirilemez ve silinemez. */
class LedgerEntryIsImmutable extends RuntimeException
{
    public static function cannotUpdate(): self
    {
        return new self('Cari hareketi değiştirilemez; düzeltme yalnızca ters kayıt ile yapılır.');
    }

    public static function cannotDelete(): self
    {
        return new self('Cari hareketi silinemez; düzeltme yalnızca ters kayıt ile yapılır.');
    }
}
