<?php

declare(strict_types=1);

namespace App\Modules\Payments\Exceptions;

use RuntimeException;

/** Yalnızca iptal edilmiş tahsilat çöp kutusuna taşınabilir. */
class PaymentNotCancelled extends RuntimeException
{
    public static function make(int $paymentId): self
    {
        return new self("#{$paymentId} numaralı tahsilat iptal edilmemiş; yalnızca iptal edilmiş tahsilat çöp kutusuna taşınabilir.");
    }
}
