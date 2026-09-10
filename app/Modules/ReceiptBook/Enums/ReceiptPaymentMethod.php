<?php

declare(strict_types=1);

namespace App\Modules\ReceiptBook\Enums;

/**
 * Makbuz üzerinde gösterilen ödeme şekli işareti — SALT bilgi amaçlıdır.
 *
 * `Payments\Enums\PaymentMethod` ile KASITLI olarak AYRI bir enum'dur: o
 * enum'un `Cash` değeri `CashBookService`'i TETİKLER (v2.1.1-A); bu modül
 * hiçbir muhasebe/kasa etkisi üretmediği için AYNI enum'u paylaşmak yanlış
 * bir bağ ima ederdi (§4.3 modül izolasyonu).
 */
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
