<?php

declare(strict_types=1);

namespace App\Modules\Stays\Exceptions;

use RuntimeException;

/** Bir konaklama yalnızca bir kez iptal edilebilir. */
class StayAlreadyCancelled extends RuntimeException
{
    public static function make(int $stayId): self
    {
        return new self("#{$stayId} numaralı konaklama zaten iptal edilmiş.");
    }
}
