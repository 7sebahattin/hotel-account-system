<?php

declare(strict_types=1);

namespace App\Modules\CashBook\Support;

use App\Modules\CashBook\Models\CashCount;
use App\Modules\CashBook\Models\CashSession;
use App\Modules\CashBook\Models\CashTransfer;
use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
use Illuminate\Support\Collection;

/**
 * Bir vardiyanın ekranda ve raporda gösterilen tüm özeti.
 *
 * Ekranda hiçbir finansal hesap yapılmaz (CLAUDE.md §13): tüm toplamlar burada
 * hazır gelir. `totals` para birimi koduyla anahtarlanmıştır ve para birimleri
 * asla tek toplamda birleştirilmez (§6/L5).
 */
final readonly class CashSessionSummary
{
    /**
     * @param  array<string, CashSessionTotals>  $totals
     * @param  Collection<int, CashCount>  $counts
     */
    public function __construct(
        public CashSession $session,
        public array $totals,
        public int $movementCount,
        public Collection $counts,
        public ?CashTransfer $outgoingTransfer = null,
        public ?CashTransfer $incomingTransfer = null,
    ) {}

    /**
     * Beklenen fiziksel kasa, para birimi bazında.
     *
     * @return array<string, Money>
     */
    public function expected(): array
    {
        return array_map(
            static fn (CashSessionTotals $totals): Money => $totals->expected(),
            $this->totals,
        );
    }

    /** Hiç hareket görmemiş vardiya — boş durum metni için. */
    public function hasMovements(): bool
    {
        return $this->movementCount > 0;
    }

    /** Kapanış sayımında herhangi bir para biriminde fark var mı? */
    public function hasCountDifference(): bool
    {
        return $this->counts->contains(static fn (CashCount $count): bool => $count->hasDifference());
    }

    public function countFor(Currency $currency): ?CashCount
    {
        return $this->counts->first(
            static fn (CashCount $count): bool => $count->currency === $currency
        );
    }

    /** Devir kabul bekliyorsa vardiyaya yeni hareket girilemez. */
    public function isAwaitingHandover(): bool
    {
        return $this->outgoingTransfer !== null && $this->outgoingTransfer->isPending();
    }
}
