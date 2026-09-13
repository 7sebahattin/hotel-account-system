<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Support;

use App\Modules\Payments\Models\Payment;
use App\Modules\Stays\Models\Stay;
use Illuminate\Support\Collection;

/**
 * Personel panosu.
 *
 * Bu nesnede firma cari bakiyesi, toplam alacak ya da başka bir FİRMA parası
 * (Ledger) alanı hiç yoktur (CLAUDE.md §7.4) — kısıt yapısaldır, Blade'de
 * gizlenerek değil.
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
