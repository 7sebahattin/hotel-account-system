<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Support;

use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;

/** Pano "en yüksek bakiyeli firmalar" listesinin bir satırı. */
final readonly class CompanyBalanceRow
{
    public function __construct(
        public int $companyId,
        public string $companyName,
        public Money $balance,
        public Currency $currency,
        // v2.1.1-D §3: TL karşılığı — katkı sağlayan bir kaynağın kuru
        // eksikse `null` kalır (dürüstlük ilkesi).
        public ?Money $balanceTl = null,
    ) {}
}
