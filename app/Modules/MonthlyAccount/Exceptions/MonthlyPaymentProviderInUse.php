<?php

declare(strict_types=1);

namespace App\Modules\MonthlyAccount\Exceptions;

use RuntimeException;

class MonthlyPaymentProviderInUse extends RuntimeException
{
    public static function make(string $name): self
    {
        return new self("{$name} geçmiş aylık hesap kayıtlarında kullanıldığı için silinemez. Yeni kayıtlarda görünmemesi için pasifleştirin.");
    }
}
