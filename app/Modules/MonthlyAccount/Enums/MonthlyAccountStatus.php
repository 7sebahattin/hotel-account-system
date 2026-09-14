<?php

declare(strict_types=1);

namespace App\Modules\MonthlyAccount\Enums;

enum MonthlyAccountStatus: string
{
    case Active = 'active';
    case Deleted = 'deleted';
}
