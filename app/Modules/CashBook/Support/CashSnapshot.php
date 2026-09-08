<?php

declare(strict_types=1);

namespace App\Modules\CashBook\Support;

use App\Modules\CashBook\Models\CashSession;
use App\Modules\CashBook\Models\CashTransfer;
use App\Modules\Core\Support\Money;

/**
 * Panodaki "Kasada Şu An" kartının tüm verisi.
 *
 * Gün 9.6'da mimarî olarak boş bırakılan slot, Gün 10'da bu nesneyle doldu.
 * Ekranda hiçbir hesap yapılmaz; tutarlar para birimi bazında hazır gelir ve
 * ASLA tek toplamda birleştirilmez (CLAUDE.md §6/L5).
 */
final readonly class CashSnapshot
{
    /**
     * @param  array<string, Money>  $expected  para birimi kodu => beklenen fiziksel kasa
     */
    public function __construct(
        public CashSession $session,
        public array $expected,
        public int $movementCount,
        public ?CashTransfer $lastTransfer = null,
    ) {}

    public function hasExpectedCash(): bool
    {
        return $this->expected !== [];
    }
}
