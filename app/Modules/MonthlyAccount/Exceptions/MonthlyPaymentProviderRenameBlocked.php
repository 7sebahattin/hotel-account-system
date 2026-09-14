<?php

declare(strict_types=1);

namespace App\Modules\MonthlyAccount\Exceptions;

use RuntimeException;

class MonthlyPaymentProviderRenameBlocked extends RuntimeException
{
    public static function make(string $name): self
    {
        return new self("{$name} daha önce kullanıldığı için adı değiştirilemez. Gerekirse pasifleştirip yeni bir tanım ekleyin.");
    }
}
