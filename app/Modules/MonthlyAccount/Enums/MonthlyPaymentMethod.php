<?php

declare(strict_types=1);

namespace App\Modules\MonthlyAccount\Enums;

enum MonthlyPaymentMethod: string
{
    case Cash = 'cash';
    case CreditCard = 'credit_card';
    case Link = 'link';
    case BankTransfer = 'bank_transfer';
    case Extra = 'extra';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Nakit',
            self::CreditCard => 'Kredi kartı',
            self::Link => 'Link',
            self::BankTransfer => 'Havale',
            self::Extra => 'Ekstra',
        };
    }

    public function requiresBank(): bool
    {
        return in_array($this, [self::CreditCard, self::Link, self::BankTransfer], true);
    }
}
