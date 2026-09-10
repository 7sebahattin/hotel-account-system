<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Support;

use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;

/**
 * Tek bir para biriminin pano özeti.
 *
 * Bu nesne BİLEREK tek para birimlidir: farklı para birimlerini toplayan bir
 * yapı olmadığı için, kur çevrimi yapmak isteyen bir kod yazılamaz (CLAUDE.md §6/L5).
 */
final readonly class CurrencyTotals
{
    public function __construct(
        public Currency $currency,
        public Money $receivable,
        public Money $payable,
        public Money $net,
        public Money $staysThisMonth,
        public Money $paymentsThisMonth,
        // v2.1.1-D §3: yabancı para biriminin TL karşılığı — yalnızca
        // TRY DIŞI para birimlerinde dolu. Katkı sağlayan herhangi bir kaynak
        // kaydın kuru eksikse `null` kalır (dürüstlük ilkesi, `fx-note`
        // bileşeniyle AYNI karar) — sessizce yanlış bir rakam üretilmez.
        public ?Money $netTl = null,
    ) {}

    /** Bu para biriminde gösterilecek bir şey var mı? */
    public function hasActivity(): bool
    {
        return ! $this->receivable->isZero()
            || ! $this->payable->isZero()
            || ! $this->staysThisMonth->isZero()
            || ! $this->paymentsThisMonth->isZero();
    }
}
