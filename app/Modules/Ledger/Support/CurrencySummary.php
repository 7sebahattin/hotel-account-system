<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Support;

use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;

/**
 * Bir para biriminin ekstre özeti.
 *
 * `carryForward` seçilen tarih aralığından ÖNCEKİ net bakiyedir (devreden);
 * tarih filtresi yoksa sıfırdır. `closing` = carryForward + borç − alacak.
 */
final readonly class CurrencySummary
{
    public function __construct(
        public Currency $currency,
        public Money $carryForward,
        public Money $totalDebit,
        public Money $totalCredit,
        public Money $closing,
    ) {}

    /** Firma bize borçlu mu, biz mi borçluyuz — metinle de ifade edilir. */
    public function balanceLabel(): string
    {
        return match (true) {
            $this->closing->isPositive() => 'Firma bize borçlu',
            $this->closing->isNegative() => 'Firma alacaklı (fazla tahsilat)',
            default => 'Kapalı',
        };
    }

    /** Tasarım sisteminin durum tonu: renk TEK BAŞINA anlam taşımaz. */
    public function balanceTone(): string
    {
        return match (true) {
            $this->closing->isPositive() => 'danger',
            $this->closing->isNegative() => 'success',
            default => 'neutral',
        };
    }

    public function hasCarryForward(): bool
    {
        return ! $this->carryForward->isZero();
    }
}
