<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Exceptions;

use RuntimeException;

class CannotReverseEntry extends RuntimeException
{
    public static function alreadyReversed(int $entryId): self
    {
        return new self("#{$entryId} numaralı cari hareket zaten ters kaydedilmiş.");
    }

    /** Ters kaydın tersi alınamaz; aksi halde zincir oluşur (CLAUDE.md §6/L8). */
    public static function isReversal(int $entryId): self
    {
        return new self("#{$entryId} zaten bir ters kayıttır; ters kaydın tersi alınamaz.");
    }
}
