<?php

declare(strict_types=1);

namespace App\Modules\Payments\Exceptions;

use RuntimeException;

/** İptal edilmiş tahsilat düzenlenemez — metadata dahil. */
class CannotUpdateCancelledPayment extends RuntimeException
{
    public static function make(int $paymentId): self
    {
        return new self("#{$paymentId} numaralı tahsilat iptal edilmiş; düzenlenemez.");
    }
}
