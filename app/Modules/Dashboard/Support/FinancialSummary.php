<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Support;

use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
use App\Modules\Ledger\Models\LedgerEntry;
use App\Modules\Payments\Models\Payment;
use App\Modules\Stays\Models\Stay;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Finans panosunun tüm verisi (Admin/Patron).
 *
 * 23 Eylül 2026 yeni tasarımı: KPI kartları, grafik, en yüksek bakiyeli
 * hesaplar ve "Son Hareketler" tablosu SEÇİLİ para birimini gösterir;
 * "Para Birimi Bazında Durum" tüm para birimlerini ayrı satırlarda listeler.
 * Hiçbir tutar başka bir para birimiyle birleştirilmez (CLAUDE.md §6/L5).
 */
final readonly class FinancialSummary
{
    public const array TABS = [
        'hareketler' => 'Son Hareketler',
        'konaklamalar' => 'Konaklamalar',
        'tahsilatlar' => 'Tahsilatlar',
        'borc-alacak' => 'Borç / Alacak',
    ];

    public const array CHART_MONTHS = [6, 12];

    /**
     * @param  array<int, CurrencyTotals>  $currencies
     * @param  array<string, array<int, CompanyBalanceRow>>  $topCompanies
     * @param  Collection<int, Payment>  $recentPayments
     * @param  Collection<int, Stay>  $upcomingStays
     * @param  array<int, string>  $companyNames
     * @param  ?LengthAwarePaginator<int, LedgerEntry>  $ledgerEntries
     * @param  array<int, Money>  $ledgerBalances
     * @param  array<int, CompanyBalanceRow>  $accountBalances
     * @param  ?array<int, ActivityItem>  $activity  `null` = görme yetkisi yok
     */
    public function __construct(
        public array $currencies,
        public int $activeCompanyCount,
        public array $topCompanies,
        public Collection $recentPayments,
        public Collection $upcomingStays,
        public array $companyNames,
        public ?Currency $selectedCurrency = null,
        public int $passiveCompanyCount = 0,
        public int $totalStayCount = 0,
        public int $totalNightCount = 0,
        public ?Money $lifetimeStays = null,
        public ?Money $lifetimePayments = null,
        public int $lifetimePaymentCount = 0,
        public ?Money $averageNightly = null,
        public ?MonthlyChart $chart = null,
        public int $chartMonths = 6,
        public string $tab = 'hareketler',
        public ?LengthAwarePaginator $ledgerEntries = null,
        public array $ledgerBalances = [],
        public array $accountBalances = [],
        public ?array $activity = null,
    ) {}

    /** Sistemde hiç finansal hareket var mı? Boş kurulumda empty-state gösterilir. */
    public function isEmpty(): bool
    {
        return $this->currencies === []
            && $this->recentPayments->isEmpty()
            && $this->upcomingStays->isEmpty();
    }

    /** Seçili para biriminin özet satırı. */
    public function selected(): ?CurrencyTotals
    {
        foreach ($this->currencies as $totals) {
            if ($totals->currency === $this->selectedCurrency) {
                return $totals;
            }
        }

        return null;
    }

    public function companyName(int $companyId): string
    {
        return $this->companyNames[$companyId] ?? 'Firma bulunamadı';
    }
}
