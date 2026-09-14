<?php

declare(strict_types=1);

namespace App\Modules\MonthlyAccount\Enums;

enum ConversionMode: string
{
    case Rate = 'rate';
    case Total = 'total';
}
