<?php

declare(strict_types=1);

namespace App\Modules\Payments\Exceptions;

use RuntimeException;

/** Yalnızca çöp kutusundaki tahsilat kalıcı silinebilir. */
class PaymentNotTrashed extends RuntimeException
{
    public static function make(int $paymentId): self
    {
        return new self("#{$paymentId} numaralı tahsilat çöp kutusunda değil; kalıcı silme yalnızca çöpteki iptal edilmiş tahsilat için yapılabilir.");
    }
}
