<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Support;

use App\Modules\Ledger\Models\LedgerEntry;
use App\Modules\Payments\Models\Payment;
use App\Modules\Stays\Models\Stay;
use Illuminate\Support\Collection;

/**
 * Admin/Patron panosunun tüm verisi.
 *
 * @phpstan-type TopCompanies array<string, array<int, CompanyBalanceRow>>
 */
final readonly class FinancialSummary
{
    /**
     * @param  array<int, CurrencyTotals>  $currencies
     * @param  array<string, array<int, CompanyBalanceRow>>  $topCompanies
     * @param  Collection<int, LedgerEntry>  $recentEntries
     * @param  Collection<int, Payment>  $recentPayments
     * @param  Collection<int, Stay>  $upcomingStays
     * @param  array<int, string>  $companyNames
     */
    public function __construct(
        public array $currencies,
        public int $activeCompanyCount,
        public array $topCompanies,
        public Collection $recentEntries,
        public Collection $recentPayments,
        public Collection $upcomingStays,
        public array $companyNames,
    ) {}

    /** Sistemde hiç finansal hareket var mı? Boş kurulumda empty-state gösterilir. */
    public function isEmpty(): bool
    {
        return $this->currencies === []
            && $this->recentEntries->isEmpty()
            && $this->recentPayments->isEmpty()
            && $this->upcomingStays->isEmpty();
    }

    public function companyName(int $companyId): string
    {
        return $this->companyNames[$companyId] ?? 'Firma bulunamadı';
    }
}
