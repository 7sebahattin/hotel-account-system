<?php

declare(strict_types=1);

namespace App\Modules\CashBook\Enums;

/**
 * Kasa hareketi türü.
 *
 * KRİTİK AYRIM (kullanıcı talimatı §6): `CashOut` ve `CashIn` fiziksel kasayı
 * değiştirir ama GELİR-GİDER RAPORUNU ETKİLEMEZ. Patrona teslim edilen
 * 12.000 TRY bir gider değildir; sadece paranın kasadan çıkışıdır. Bu ayrım
 * `affectsIncomeStatement()` ile tek yerde tanımlıdır.
 */
enum CashMovementType: string
{
    /** Vardiya açılış bakiyesi — devralınan ya da ilk sayılan para. */
    case Opening = 'opening';

    case RoomIncome = 'room_income';
    case ExtraIncome = 'extra_income';
    case Expense = 'expense';

    /** Ara kasa / patron / banka teslimi — gider DEĞİLDİR. */
    case CashOut = 'cash_out';

    /** Dışarıdan kasaya para konması (bozukluk, patron takviyesi) — gelir DEĞİLDİR. */
    case CashIn = 'cash_in';

    public function label(): string
    {
        return match ($this) {
            self::Opening => 'Açılış Bakiyesi',
            self::RoomIncome => 'Oda Geliri',
            self::ExtraIncome => 'Ek Gelir',
            self::Expense => 'Gider',
            self::CashOut => 'Kasa Çıkışı',
            self::CashIn => 'Kasa Girişi',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Opening => 'Vardiya başındaki kasa',
            self::RoomIncome => 'Konaklama için alınan nakit',
            self::ExtraIncome => 'Mini bar, transfer, ekstra yatak',
            self::Expense => 'Gerçek işletme gideri',
            self::CashOut => 'Ara kasa / patron / banka teslimi',
            self::CashIn => 'Kasaya dışarıdan konan para',
        };
    }

    /** Tipin doğal yönü. Ters kayıtta bu yön tersine döner. */
    public function direction(): CashMovementDirection
    {
        return match ($this) {
            self::Opening, self::RoomIncome, self::ExtraIncome, self::CashIn => CashMovementDirection::In,
            self::Expense, self::CashOut => CashMovementDirection::Out,
        };
    }

    /**
     * Gelir-gider raporuna girer mi?
     *
     * Açılış bakiyesi, kasa çıkışı ve kasa girişi para TAŞIR, gelir/gider
     * ÜRETMEZ. Bu, ara kasa tesliminin gider olarak sayılmamasının tek kaynağıdır.
     */
    public function affectsIncomeStatement(): bool
    {
        return match ($this) {
            self::RoomIncome, self::ExtraIncome, self::Expense => true,
            self::Opening, self::CashOut, self::CashIn => false,
        };
    }

    public function isIncome(): bool
    {
        return $this === self::RoomIncome || $this === self::ExtraIncome;
    }

    /** Kullanıcının elle girebileceği türler — açılış bakiyesi servis tarafından üretilir. */
    public static function selectable(): array
    {
        return [self::RoomIncome, self::ExtraIncome, self::Expense, self::CashOut, self::CashIn];
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
