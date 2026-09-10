<?php

declare(strict_types=1);

namespace App\Modules\Payments\Enums;

/**
 * Tahsilatın alınma biçimi (kanal).
 *
 * v2.1.1-A'dan itibaren kanal salt bilgi amaçlı DEĞİLDİR:
 * - `Cash` → `PaymentService` aynı transaction'da fiziksel kasaya
 *   (`CashBookService`) bir `company_collection` hareketi de yazar; bu
 *   hareket personelin açık vardiyasında sayılır ve devirde taşınır.
 * - `BankTransfer`, `CreditCard`, `Cheque`, `Other` → yalnızca cariyi
 *   etkiler, fiziksel kasaya HİÇ dokunmaz (kasa sayımını, beklenen kasayı ve
 *   personel devrini etkilemez).
 *
 * Banka hesap yönetimi, POS entegrasyonu, muhasebe entegrasyonu ve Z-raporu
 * hâlâ KAPSAM DIŞIDIR (CLAUDE.md §3.2) — burada eklenen yalnızca kanalın
 * KENDİ modülü (CashBook) içindeki doğru temsilidir, dış entegrasyon değildir.
 */
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
