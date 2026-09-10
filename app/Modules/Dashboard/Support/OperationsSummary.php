<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Support;

use App\Modules\CashBook\Models\CashMovement;
use App\Modules\CashBook\Models\CashTransfer;
use App\Modules\CashBook\Support\CashSessionTotals;
use App\Modules\CashBook\Support\CashSnapshot;
use Illuminate\Support\Collection;

/**
 * Admin/Patron panosunun "Operasyon" görünümü — "Otelde şu anda ne oluyor?"
 *
 * Firma/cari veri burada YOKTUR; onun yeri Finans görünümüdür
 * (`FinancialSummary`). İkisi ayrı sorgu bütçesi taşır: Operasyon seçiliyken
 * cari sorguları, Finans seçiliyken kasa sorguları hiç ÇALIŞMAZ (kullanıcı
 * talimatı §17) — bunu sağlayan `DashboardService::operations()`/`finance()`
 * ayrımıdır, controller yalnızca seçilen tarafı çağırır.
 */
final readonly class OperationsSummary
{
    /**
     * @param  Collection<int, CashMovement>  $recentMovements
     * @param  array<string, CashSessionTotals>  $todayTotals  para birimi => bugünkü gelir/gider tipi kırılımı
     */
    public function __construct(
        public ?CashSnapshot $cash,
        public Collection $recentMovements,
        public ?CashTransfer $pendingOutgoingTransfer,
        public array $todayTotals,
        public bool $hasCountDifferenceToday,
    ) {}

    public function hasActiveSession(): bool
    {
        return $this->cash !== null;
    }

    /** D) Dikkat gerekenler bölümü hiç mi boş — hiçbir gerçek sorun yoksa büyük bir "her şey yolunda" kartı gösterilmez. */
    public function needsAttention(): bool
    {
        return $this->pendingOutgoingTransfer !== null || $this->hasCountDifferenceToday;
    }

    public function hasTodayActivity(): bool
    {
        return $this->todayTotals !== [];
    }
}
