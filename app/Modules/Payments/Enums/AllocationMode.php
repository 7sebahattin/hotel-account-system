<?php

declare(strict_types=1);

namespace App\Modules\Payments\Enums;

enum AllocationMode: string
{
    case OldestFirst = 'oldest_first';
    case Selected = 'selected';
}
