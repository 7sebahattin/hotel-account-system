<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Services;

use App\Models\User;
use App\Modules\Authorization\Enums\Permission;
use App\Modules\Companies\Services\CompanyService;
use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
use App\Modules\Dashboard\Support\CompanyBalanceRow;
use App\Modules\Dashboard\Support\CurrencyTotals;
use App\Modules\Dashboard\Support\FinancialSummary;
use App\Modules\Dashboard\Support\PersonnelSummary;
use App\Modules\Ledger\Services\LedgerStatementService;
use App\Modules\Payments\Services\PaymentService;
use App\Modules\Stays\Services\StayService;
use Illuminate\Support\Carbon;

/**
 * Pano verisini hazırlar. Ekranda hiçbir finansal hesap yapılmaz.
 *
 * Modüller arası erişim yalnızca Service katmanından geçer (CLAUDE.md §4.3/1):
 * bu sınıf Company, Stay, Payment veya LedgerEntry modeline hiç dokunmaz.
 *
 * PARA BİRİMİ KURALI (CLAUDE.md §6/L5): TRY, EUR ve GBP asla tek toplamda
 * birleştirilmez. Buradaki her toplam para birimi anahtarlı bir dizidir ve
 * hiçbir yerde birbirine çevrilmez — V1'de kur servisi yoktur.
 */
class DashboardService
{
    public function __construct(
        private readonly LedgerStatementService $statements,
        private readonly CompanyService $companies,
        private readonly StayService $stays,
        private readonly PaymentService $payments,
    ) {}

    /**
     * Finansal pano — yalnızca `dashboard.financial` yetkisi olanlara.
     *
     * Sorgu bütçesi sabittir ve kayıt sayısına göre BÜYÜMEZ:
     *   1 açık bakiye özeti + 1 konaklama toplamı + 1 tahsilat toplamı
     *   + 1 firma sayısı + (para birimi adedi) en yüksek bakiye sorgusu
     *   + 1 son hareket + 1 firma adı çözümü + 1 son tahsilat
     *   + 1 tahsilat firma adı çözümü + 1 yaklaşan konaklama
     *   + 1 konaklama firma adı çözümü.
     */
    public function financial(Carbon $today): FinancialSummary
    {
        $monthStart = $today->copy()->startOfMonth()->toDateString();
        $monthEnd = $today->copy()->endOfMonth()->toDateString();

        $balances = $this->statements->openBalanceTotalsByCurrency();
        $stayTotals = $this->stays->totalsByCurrencyBetween($monthStart, $monthEnd);
        $paymentTotals = $this->payments->totalsByCurrencyBetween($monthStart, $monthEnd);

        $recentEntries = $this->statements->recentEntries(8);
        $recentPayments = $this->payments->recent(6);
        $upcomingStays = $this->stays->currentAndUpcoming($today->toDateString(), 6);

        $topByCurrency = [];

        foreach (Currency::cases() as $currency) {
            $rows = $this->statements->topDebtorCompanies($currency, 5);

            if ($rows !== []) {
                $topByCurrency[$currency->value] = $rows;
            }
        }

        // Görünen tüm firma adları TEK sorguda çözülür — N+1 yoktur.
        $companyIds = array_merge(
            $recentEntries->pluck('company_id')->all(),
            $recentPayments->pluck('company_id')->all(),
            $upcomingStays->pluck('company_id')->all(),
            ...array_map(
                static fn (array $rows): array => array_column($rows, 'company_id'),
                array_values($topByCurrency),
            ),
        );

        /** @var array<int, int> $companyIds */
        $companyNames = $this->companies->namesByIds(array_map('intval', $companyIds));

        return new FinancialSummary(
            currencies: $this->currencyTotals($balances, $stayTotals, $paymentTotals),
            activeCompanyCount: $this->companies->activeCount(),
            topCompanies: $this->decorateTopCompanies($topByCurrency, $companyNames),
            recentEntries: $recentEntries,
            recentPayments: $recentPayments,
            upcomingStays: $upcomingStays,
            companyNames: $companyNames,
        );
    }

    /**
     * Personel panosu — TUTAR İÇERMEZ (CLAUDE.md §7.4).
     *
     * Personel yalnızca kendi üretkenliğini görür: kaç kayıt girdi ve son ne girdi.
     * Bakiye, toplam alacak ve finansal kartlar hiç sorgulanmaz.
     */
    public function personnel(User $user, Carbon $today): PersonnelSummary
    {
        $userId = (int) $user->getKey();
        $dayStart = $today->copy()->startOfDay()->toDateTimeString();
        $weekStart = $today->copy()->startOfWeek()->toDateTimeString();

        return new PersonnelSummary(
            staysToday: $this->stays->countCreatedBySince($userId, $dayStart),
            staysThisWeek: $this->stays->countCreatedBySince($userId, $weekStart),
            paymentsToday: $this->payments->countCreatedBySince($userId, $dayStart),
            paymentsThisWeek: $this->payments->countCreatedBySince($userId, $weekStart),
            recentStays: $this->stays->recentCreatedBy($userId, 5),
            recentPayments: $this->payments->recentCreatedBy($userId, 5),
        );
    }

    public function canSeeFinancials(User $user): bool
    {
        return $user->can(Permission::DashboardFinancial->value);
    }

    /**
     * Para birimi bazında birleşik özet.
     *
     * Üç kaynağın (bakiye, konaklama, tahsilat) anahtar kümesi farklı olabilir:
     * bu ay hiç EUR tahsilatı olmayabilir ama EUR bakiyesi vardır. Birleşim
     * alınır ve eksik olanlar sıfırla doldurulur; böylece ekranda para birimi
     * kartları arasında boşluk oluşmaz.
     *
     * @param  array<string, array{receivable: Money, payable: Money, net: Money}>  $balances
     * @param  array<string, Money>  $stayTotals
     * @param  array<string, Money>  $paymentTotals
     * @return array<int, CurrencyTotals>
     */
    private function currencyTotals(array $balances, array $stayTotals, array $paymentTotals): array
    {
        $codes = array_values(array_unique(array_merge(
            array_keys($balances),
            array_keys($stayTotals),
            array_keys($paymentTotals),
        )));

        // Gösterim sırası enum sırasıdır (TRY, EUR, GBP) — veri sırası değil.
        $ordered = array_values(array_filter(
            Currency::values(),
            static fn (string $code): bool => in_array($code, $codes, true),
        ));

        return array_map(
            static fn (string $code): CurrencyTotals => new CurrencyTotals(
                currency: Currency::from($code),
                receivable: $balances[$code]['receivable'] ?? Money::zero(),
                payable: $balances[$code]['payable'] ?? Money::zero(),
                net: $balances[$code]['net'] ?? Money::zero(),
                staysThisMonth: $stayTotals[$code] ?? Money::zero(),
                paymentsThisMonth: $paymentTotals[$code] ?? Money::zero(),
            ),
            $ordered,
        );
    }

    /**
     * @param  array<string, array<int, array{company_id: int, balance: Money}>>  $topByCurrency
     * @param  array<int, string>  $companyNames
     * @return array<string, array<int, CompanyBalanceRow>>
     */
    private function decorateTopCompanies(array $topByCurrency, array $companyNames): array
    {
        $decorated = [];

        foreach ($topByCurrency as $code => $rows) {
            $decorated[$code] = array_map(
                static fn (array $row): CompanyBalanceRow => new CompanyBalanceRow(
                    companyId: $row['company_id'],
                    // Firma silinmişse ekran çökmez; ad yerine nötr metin gösterilir.
                    companyName: $companyNames[$row['company_id']] ?? 'Firma bulunamadı',
                    balance: $row['balance'],
                    currency: Currency::from($code),
                ),
                $rows,
            );
        }

        return $decorated;
    }
}
