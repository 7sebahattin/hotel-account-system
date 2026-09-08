<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Support;

use App\Modules\Payments\Models\Payment;
use App\Modules\Stays\Models\Stay;
use Illuminate\Support\Collection;

/**
 * Personel panosu — TUTAR TAŞIMAZ.
 *
 * Bu nesnede bilerek hiç `Money` alanı yoktur: personel finansal toplamları
 * göremez (CLAUDE.md §7.4) ve bu kısıt veri yapısının kendisinde uygulanır,
 * yalnızca Blade'de gizlenerek değil.
 */
final readonly class PersonnelSummary
{
    /**
     * @param  Collection<int, Stay>  $recentStays
     * @param  Collection<int, Payment>  $recentPayments
     */
    public function __construct(
        public int $staysToday,
        public int $staysThisWeek,
        public int $paymentsToday,
        public int $paymentsThisWeek,
        public Collection $recentStays,
        public Collection $recentPayments,
    ) {}

    public function hasRecords(): bool
    {
        return $this->recentStays->isNotEmpty() || $this->recentPayments->isNotEmpty();
    }
}
