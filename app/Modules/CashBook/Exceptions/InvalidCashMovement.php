<?php

declare(strict_types=1);

namespace App\Modules\CashBook\Exceptions;

class InvalidCashMovement extends CashBookException
{
    public static function amountNotPositive(): self
    {
        return new self('Kasa hareketi tutarı sıfırdan büyük olmalıdır.');
    }

    public static function missingActor(): self
    {
        return new self('Kasa işlemi için işlemi yapan kullanıcı belirtilmelidir.');
    }
}
