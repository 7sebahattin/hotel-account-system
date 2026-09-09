<?php

declare(strict_types=1);

namespace App\Modules\Payments\Support;

use App\Modules\CashBook\Models\CashSession;
use App\Modules\CashBook\Support\CashSessionTotals;
use App\Modules\Core\Support\Money;

/**
 * Bir vardiyanın tahsilat KANALI bazında özeti (v2.1.1-A §11).
 *
 * Para birimleri asla birleştirilmez (CLAUDE.md §6/L5): her alan
 * `array<string, Money>` — para birimi koduyla anahtarlanmıştır.
 *
 * `cashTotals` kasa tarafının TAM dökümüdür (açılış, gider, kasa çıkışı dahil)
 * — `CashBookService::totalsFor()`'un aynı çıktısıdır, tekrar sorgulanmaz.
 * `cashCollections` bunun İÇİNDEN yalnızca nakit kanallı tahsilat (`company_
 * collection`) satırıdır; kart/banka ile aynı düzeyde karşılaştırılabilsin
 * diye ayrıca çıkarılmıştır.
 */
final readonly class ShiftCollectionSummary
{
    /**
     * @param  array<string, Money>  $cashCollections
     * @param  array<string, Money>  $creditCardCollections
     * @param  array<string, Money>  $bankTransferCollections
     * @param  array<string, CashSessionTotals>  $cashTotals
     */
    public function __construct(
        public CashSession $session,
        public array $cashCollections,
        public array $creditCardCollections,
        public array $bankTransferCollections,
        public array $cashTotals,
    ) {}
}
