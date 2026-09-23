<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Support;

use App\Modules\Core\Support\Money;

/** Pano aylık grafiğinin tek bir ayı — tek para biriminde. */
final readonly class ChartMonth
{
    public function __construct(
        public string $key,
        public string $label,
        public Money $stays,
        public Money $payments,
        // Ay sonundaki sistem geneli net bakiye (tüm hesaplar, aynı para birimi).
        public Money $net,
    ) {}
}
