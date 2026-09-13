<?php

declare(strict_types=1);

namespace App\Modules\Stays\Exceptions;

use RuntimeException;

/**
 * Aktif (iptal edilmemiş) bir konaklama kalıcı olarak silinemez — borcu
 * deftere zaten işlenmiştir ve ledger append-only olduğu için (CLAUDE.md
 * §6/L1) bu borç hard-delete ile kaldırılamaz. Önce iptal edilmesi gerekir.
 */
class StayNotCancelled extends RuntimeException
{
    public static function make(int $stayId): self
    {
        return new self("#{$stayId} numaralı konaklama kalıcı olarak silinemez: önce iptal edilmelidir.");
    }
}
