<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Support;

use App\Modules\Payments\Models\Payment;
use App\Modules\Stays\Models\Stay;
use Illuminate\Support\Collection;

/**
 * Finans görünümünün tüm verisi (Gün 11 — Admin/Patron "Finans" sekmesi).
 *
 * Cari hesap ve firma verisini taşır.
 *
 * @phpstan-type TopCompanies array<string, array<int, CompanyBalanceRow>>
 */
final readonly class FinancialSummary
{
    /**
     * @param  array<int, CurrencyTotals>  $currencies
     * @param  array<string, array<int, CompanyBalanceRow>>  $topCompanies
     * @param  Collection<int, Payment>  $recentPayments
     * @param  Collection<int, Stay>  $upcomingStays
     * @param  array<int, string>  $companyNames
     */
    public function __construct(
        public array $currencies,
        public int $activeCompanyCount,
        public array $topCompanies,
        public Collection $recentPayments,
        public Collection $upcomingStays,
        public array $companyNames,
    ) {}

    /** Sistemde hiç finansal hareket var mı? Boş kurulumda empty-state gösterilir. */
    public function isEmpty(): bool
    {
        return $this->currencies === []
            && $this->recentPayments->isEmpty()
            && $this->upcomingStays->isEmpty();
    }

    public function companyName(int $companyId): string
    {
        return $this->companyNames[$companyId] ?? 'Firma bulunamadı';
    }
}
