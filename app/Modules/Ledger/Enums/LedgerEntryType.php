<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Enums;

use App\Modules\Ledger\Models\LedgerEntry;

/**
 * Ekstrede gösterilen işlem türü.
 *
 * `ledger_entries.source_type` polimorfiktir ve ileride yeni belge tipleri
 * alabilir (CLAUDE.md §3.3). Bu enum yalnızca GÖSTERİM içindir: tanımadığı
 * bir kaynak tipini `Other` olarak etiketler, veri yolunu kısıtlamaz.
 * Ham `source_type` değeri ana tabloda gösterilmez.
 */
enum LedgerEntryType: string
{
    case Stay = 'stay';
    case Payment = 'payment';
    case PaymentAllocation = 'payment_allocation';
    case Reversal = 'reversal';
    case Opening = 'opening';
    case Other = 'other';

    /** Ters kayıt, kaynağı ne olursa olsun "İptal" olarak etiketlenir. */
    public static function fromEntry(LedgerEntry $entry): self
    {
        if ($entry->is_reversal) {
            return self::Reversal;
        }

        return self::tryFrom((string) $entry->source_type) ?? self::Other;
    }

    public function label(): string
    {
        return match ($this) {
            self::Stay => 'Konaklama',
            self::Payment => 'Tahsilat',
            self::PaymentAllocation => 'Dövizle Borç Kapama',
            self::Reversal => 'İptal / Ters Kayıt',
            self::Opening => 'Açılış Bakiyesi',
            self::Other => 'Diğer',
        };
    }

    /** Filtre kutusunda seçilebilen türler. */
    public static function filterable(): array
    {
        return [self::Stay, self::Payment, self::PaymentAllocation, self::Reversal];
    }
}
