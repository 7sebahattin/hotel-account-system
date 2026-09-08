<?php

declare(strict_types=1);

namespace App\Modules\CashBook\Exceptions;

class CashSessionAlreadyOpen extends CashBookException
{
    public static function make(): self
    {
        return new self('Zaten açık bir kasa vardiyası var. Yeni vardiya açmadan önce mevcut vardiya kapatılmalıdır.');
    }
}
