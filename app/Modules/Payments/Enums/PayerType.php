<?php

declare(strict_types=1);

namespace App\Modules\Payments\Enums;

enum PayerType: string
{
    case AccountHolder = 'account_holder';
    case Individual = 'individual';

    public function label(): string
    {
        return $this === self::Individual ? 'Şahsi ödeme' : 'Hesap sahibi';
    }
}
