<?php

declare(strict_types=1);

namespace App\Modules\CashBook\Support;

use App\Modules\CashBook\Enums\CashMovementType;
use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;

/**
 * Tek para birimi için vardiya toplamları.
 *
 * Her alan İŞARETLİ NET tutardır: giriş pozitif, çıkış negatiftir. Gider ve
 * kasa çıkışı bu yüzden negatif görünür — devir raporundaki "-750 TRY" ve
 * "-10.000 TRY" satırları doğrudan bu değerlerdir. Ters kayıtlar aynı toplama
 * ters yönle katıldığı için ayrıca düşülmeleri gerekmez.
 *
 * `expected` bu netlerin toplamıdır (açılış dahil). Para birimleri ASLA
 * birbirine eklenmez; bu nesne yalnızca TEK bir para birimini temsil eder.
 */
final readonly class CashSessionTotals
{
    public function __construct(
        public Currency $currency,
        public Money $opening,
        public Money $roomIncome,
        public Money $extraIncome,
        public Money $expense,
        public Money $cashOut,
        public Money $cashIn,
    ) {}

    /** Beklenen fiziksel kasa = tüm hareketlerin işaretli toplamı. */
    public function expected(): Money
    {
        return $this->opening
            ->add($this->roomIncome)
            ->add($this->extraIncome)
            ->add($this->expense)
            ->add($this->cashOut)
            ->add($this->cashIn);
    }

    /** Gelir toplamı — kasa girişi ve açılış bakiyesi GELİR DEĞİLDİR. */
    public function income(): Money
    {
        return $this->roomIncome->add($this->extraIncome);
    }

    /**
     * Bir tipin işaretli neti.
     *
     * Ekranlar tip listesi üzerinde döndüğü için isimle değil enum ile erişim
     * gerekir; böylece yeni bir tip eklendiğinde Blade'de dallanma olmaz.
     */
    public function forType(CashMovementType $type): Money
    {
        return match ($type) {
            CashMovementType::Opening => $this->opening,
            CashMovementType::RoomIncome => $this->roomIncome,
            CashMovementType::ExtraIncome => $this->extraIncome,
            CashMovementType::Expense => $this->expense,
            CashMovementType::CashOut => $this->cashOut,
            CashMovementType::CashIn => $this->cashIn,
        };
    }

    /** Hiç hareket görmemiş para birimi kartını gizlemek için. */
    public function isEmpty(): bool
    {
        foreach (CashMovementType::cases() as $type) {
            if (! $this->forType($type)->isZero()) {
                return false;
            }
        }

        return true;
    }
}
