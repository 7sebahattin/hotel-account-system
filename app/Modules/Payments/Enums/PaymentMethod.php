<?php

declare(strict_types=1);

namespace App\Modules\Payments\Enums;

/** Tahsilat kanalıdır; hiçbir kanal fiziksel kasa hareketi oluşturmaz. */
enum PaymentMethod: string
{
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case CreditCard = 'credit_card';
    case Cheque = 'cheque';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Nakit',
            self::BankTransfer => 'Havale / EFT',
            self::CreditCard => 'Kredi Kartı',
            self::Cheque => 'Çek / Senet',
            self::Other => 'Diğer',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Cari Tahsilat formunda gösterilen dört ana kanal (v2.1.1-B §9).
     *
     * `Other` burada YOKTUR — arayüzü karmaşıklaştırmaz (kullanıcı talimatı:
     * "do not clutter user UI unless actually required") ama teknik olarak
     * hâlâ geçerlidir (`Rule::enum` bunu reddetmez); raporlama/filtre
     * ekranları hâlâ `cases()` kullanır, geçmiş `other` kayıtları gizlenmez.
     *
     * @return array<int, self>
     */
    public static function primary(): array
    {
        return [self::Cash, self::CreditCard, self::BankTransfer, self::Cheque];
    }
}
