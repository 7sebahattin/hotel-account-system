<?php

declare(strict_types=1);

namespace App\Modules\CashBook\Exceptions;

class NoOpenCashSession extends CashBookException
{
    public static function make(): self
    {
        return new self('Açık bir kasa vardiyası yok. Önce kasayı açın.');
    }
}
