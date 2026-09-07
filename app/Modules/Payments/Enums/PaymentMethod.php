<?php

declare(strict_types=1);

namespace App\Modules\Payments\Enums;

/**
 * Tahsilatın alınma biçimi.
 *
 * V1'de bu alan yalnızca bilgi amaçlıdır: kasa, banka hesabı, POS hesabı ve
 * muhasebe entegrasyonu KAPSAM DIŞIDIR (CLAUDE.md §3.2). Alan sade tutulur ki
 * ileride bu modüller geldiğinde doğal biçimde bağlanabilsin.
 */
enum PaymentMethod: string
{
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case CreditCard = 'credit_card';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Nakit',
            self::BankTransfer => 'Havale / EFT',
            self::CreditCard => 'Kredi Kartı',
            self::Other => 'Diğer',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
