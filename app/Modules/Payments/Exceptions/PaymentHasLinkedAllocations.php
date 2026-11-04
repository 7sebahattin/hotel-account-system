<?php

declare(strict_types=1);

namespace App\Modules\Payments\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Tahsilata hiç ödeme dağılımı uygulanmışsa (serbest bırakılmış olsa bile)
 * `payment_allocations` append-only olduğu için kalıcı silme reddedilir.
 */
class PaymentHasLinkedAllocations extends RuntimeException
{
    public static function make(int $paymentId, ?Throwable $previous = null): self
    {
        return new self(
            "#{$paymentId} numaralı tahsilata konaklama dağılımı uygulanmış olduğundan kalıcı olarak silinemez; "
            .'dağılım kayıtları değiştirilemez. Kayıt çöp kutusunda kalır.',
            0,
            $previous,
        );
    }
}
