<?php

declare(strict_types=1);

namespace App\Modules\CashBook\Exceptions;

class CashTransferNotPending extends CashBookException
{
    public static function make(int $transferId): self
    {
        return new self("#{$transferId} numaralı devir zaten sonuçlanmış.");
    }
}
