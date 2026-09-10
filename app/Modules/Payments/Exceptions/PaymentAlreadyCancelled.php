<?php

declare(strict_types=1);

namespace App\Modules\Payments\Exceptions;

use RuntimeException;

/** Bir tahsilat yalnızca bir kez iptal edilebilir. */
class PaymentAlreadyCancelled extends RuntimeException
{
    public static function make(int $paymentId): self
    {
        return new self("#{$paymentId} numaralı tahsilat zaten iptal edilmiş.");
    }
}
