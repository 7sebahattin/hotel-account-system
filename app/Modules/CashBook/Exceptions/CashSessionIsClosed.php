<?php

declare(strict_types=1);

namespace App\Modules\CashBook\Exceptions;

class CashSessionIsClosed extends CashBookException
{
    public static function make(int $sessionId): self
    {
        return new self("#{$sessionId} numaralı vardiya kapanmıştır; geçmişi değiştirilemez.");
    }
}
