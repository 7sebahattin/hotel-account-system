<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Support;

use App\Modules\CashBook\Models\CashMovement;
use App\Modules\CashBook\Models\CashSession;
use App\Modules\CashBook\Models\CashTransfer;
use App\Modules\CashBook\Support\CashSessionSummary;
use App\Modules\Payments\Models\Payment;
use App\Modules\Stays\Models\Stay;
use Illuminate\Support\Collection;

/**
 * Personel panosu.
 *
 * Bu nesnede firma cari bakiyesi, toplam alacak ya da başka bir FİRMA parası
 * (Ledger) alanı hiç yoktur (CLAUDE.md §7.4) — kısıt yapısaldır, Blade'de
 * gizlenerek değil.
 *
 * Gün 10'da kurulan istisna geçerlidir: kasa (fiziksel para) personelin
 * ELİNDEKİ paradır ve ayrı bir kategori olduğu için `mySummary`/`myRecentMovements`
 * Money taşıyabilir — bu, personel firma bakiyesini görmez kuralıyla çelişmez.
 */
final readonly class PersonnelSummary
{
    /**
     * @param  Collection<int, Stay>  $recentStays
     * @param  Collection<int, Payment>  $recentPayments
     * @param  Collection<int, CashMovement>  $myRecentMovements
     */
    public function __construct(
        public int $staysToday,
        public int $staysThisWeek,
        public int $paymentsToday,
        public int $paymentsThisWeek,
        public Collection $recentStays,
        public Collection $recentPayments,
        /** İşletmedeki tek açık vardiya (sahibi kim olursa olsun) — varlık/sahip görünürlüğü herkese açıktır. */
        public ?CashSession $activeSession,
        public bool $ownsActiveSession,
        /** Yalnızca `ownsActiveSession` iken doludur: tutarları yalnızca sahibi görür. */
        public ?CashSessionSummary $mySummary,
        public Collection $myRecentMovements,
        public bool $canOpenSession,
        public ?CashTransfer $pendingIncomingTransfer,
    ) {}

    public function hasRecords(): bool
    {
        return $this->recentStays->isNotEmpty() || $this->recentPayments->isNotEmpty();
    }

    /** Açık vardiya var ama başkasında — "kasa şu an X'te" bilgi satırı için. */
    public function hasSomeoneElsesSession(): bool
    {
        return $this->activeSession !== null && ! $this->ownsActiveSession;
    }
}
