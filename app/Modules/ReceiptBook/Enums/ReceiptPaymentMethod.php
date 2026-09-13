<?php

declare(strict_types=1);

namespace App\Modules\ReceiptBook\Enums;

/** Makbuz üzerindeki ödeme şeklidir; muhasebe tahsilatından bağımsızdır. */
enum ReceiptPaymentMethod: string
{
    case Cash = 'cash';
    case CreditCard = 'credit_card';
    case BankTransfer = 'bank_transfer';
    case Cheque = 'cheque';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Nakit',
            self::CreditCard => 'Kredi Kartı',
            self::BankTransfer => 'Havale / EFT',
            self::Cheque => 'Çek / Senet',
        };
    }
}
