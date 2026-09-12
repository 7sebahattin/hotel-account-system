<?php

declare(strict_types=1);

namespace App\Modules\Companies\Enums;

enum AccountType: string
{
    case Company = 'company';
    case Individual = 'individual';

    public function label(): string
    {
        return $this === self::Company ? 'Firma' : 'Bireysel';
    }
}
