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
 * PARA BİRİMİ KURALI (CLAUDE.md §6/L5): hiçbir para birimi bir başkasıyla tek
 * toplamda birleştirilmez. Buradaki her toplam para birimi anahtarlı bir
 * dizidir ve hiçbir yerde birbirine çevrilmez — V1'de kur servisi yoktur.
 *
 * Personel kayıt özeti ile yönetici cari özeti ayrı yetkilendirilir.
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
     * Finans görünümü — yalnızca `dashboard.financial` yetkisi olanlara.
     *
     * Sorgu bütçesi sabittir: 1 açık bakiye özeti + 1 konaklama toplamı +
     * 1 tahsilat toplamı + 1 firma sayısı + (para birimi adedi) en yüksek
     * bakiye sorgusu + 1 son tahsilat + 1 tahsilat firma adı çözümü +
     * 1 yaklaşan konaklama + 1 konaklama firma adı çözümü + 2 sistem çapında
     * TL karşılığı toplamı (Stays debit + Payments credit, v2.1.1-D §3) +
     * 2 en yüksek bakiyeli firmaların TL karşılığı (AYNI iki servis, yalnızca
     * o firmalarla sınırlı). Kasa hiç sorgulanmaz. Üst sınır: 17
     * (`DashboardTest::finans_gorunumu_sorgu_sayisi_firma_sayisiyla_birlikte_buyumez`).
     */
    public function finance(Carbon $today): FinancialSummary
    {
        $monthStart = $today->copy()->startOfMonth()->toDateString();
        $monthEnd = $today->copy()->endOfMonth()->toDateString();

        $balances = $this->statements->openBalanceTotalsByCurrency();
        $stayTotals = $this->stays->totalsByCurrencyBetween($monthStart, $monthEnd);
        $paymentTotals = $this->payments->totalsByCurrencyBetween($monthStart, $monthEnd);

        // v2.1.1-D §3: "Açık alacak" kartının TL karşılığı — firma ayrımı
        // olmadan, yalnızca para birimi bazında (kart zaten tüm firmaları tek
        // toplamda gösterir).
        $debitTl = $this->stays->systemWideExchangeRateDebitTotalsByCurrency();
        $creditTl = $this->payments->systemWideExchangeRateCreditTotalsByCurrency();

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
            $recentPayments->pluck('company_id')->all(),
            $upcomingStays->pluck('company_id')->all(),
            ...array_map(
                static fn (array $rows): array => array_column($rows, 'company_id'),
                array_values($topByCurrency),
            ),
        );

        /** @var array<int, int> $companyIds */
        $companyIds = array_values(array_unique(array_map('intval', $companyIds)));
        $companyNames = $this->companies->namesByIds($companyIds);

        // "En yüksek bakiyeli firmalar" listesindeki her satır zaten bir
        // FİRMA TOPLAMI (birden çok konaklama/tahsilatın netİ) — Firma
        // Bakiye Listesi'ndeki AYNI per-firma sorgu tekrar kullanılır.
        $topCompanyIds = array_values(array_unique(array_merge(
            ...array_map(
                static fn (array $rows): array => array_column($rows, 'company_id'),
                array_values($topByCurrency),
            ),
        )));
        $topDebitTl = $this->stays->exchangeRateDebitTotalsForCompanies($topCompanyIds);
        $topCreditTl = $this->payments->exchangeRateCreditTotalsForCompanies($topCompanyIds);

        return new FinancialSummary(
            currencies: $this->currencyTotals($balances, $stayTotals, $paymentTotals, $debitTl, $creditTl),
            activeCompanyCount: $this->companies->activeCount(),
            topCompanies: $this->decorateTopCompanies($topByCurrency, $companyNames, $topDebitTl, $topCreditTl),
            recentPayments: $recentPayments,
            upcomingStays: $upcomingStays,
            companyNames: $companyNames,
        );
    }

    /**
     * Personel panosu — firma cari verisi hiç sorgulanmaz (CLAUDE.md §7.4).
     *
     * Yalnızca kendi kayıt adetlerini ve son kayıtlarını görür.
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
     * @param  array<string, array{total: Money, complete: bool}>  $debitTl
     * @param  array<string, array{total: Money, complete: bool}>  $creditTl
     * @return array<int, CurrencyTotals>
     */
    private function currencyTotals(array $balances, array $stayTotals, array $paymentTotals, array $debitTl = [], array $creditTl = []): array
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
            function (string $code) use ($balances, $stayTotals, $paymentTotals, $debitTl, $creditTl): CurrencyTotals {
                $netTl = null;

                if ($code !== Currency::TRY->value) {
                    $debit = $debitTl[$code] ?? ['total' => Money::zero(), 'complete' => true];
                    $credit = $creditTl[$code] ?? ['total' => Money::zero(), 'complete' => true];

                    if ($debit['complete'] && $credit['complete']) {
                        $netTl = $debit['total']->subtract($credit['total']);
                    }
                }

                return new CurrencyTotals(
                    currency: Currency::from($code),
                    receivable: $balances[$code]['receivable'] ?? Money::zero(),
                    payable: $balances[$code]['payable'] ?? Money::zero(),
                    net: $balances[$code]['net'] ?? Money::zero(),
                    staysThisMonth: $stayTotals[$code] ?? Money::zero(),
                    paymentsThisMonth: $paymentTotals[$code] ?? Money::zero(),
                    netTl: $netTl,
                );
            },
            $ordered,
        );
    }

    /**
     * @param  array<string, array<int, array{company_id: int, balance: Money}>>  $topByCurrency
     * @param  array<int, string>  $companyNames
     * @param  array<int, array<string, array{total: Money, complete: bool}>>  $debitTl
     * @param  array<int, array<string, array{total: Money, complete: bool}>>  $creditTl
     * @return array<string, array<int, CompanyBalanceRow>>
     */
    private function decorateTopCompanies(array $topByCurrency, array $companyNames, array $debitTl = [], array $creditTl = []): array
    {
        $decorated = [];

        foreach ($topByCurrency as $code => $rows) {
            $decorated[$code] = array_map(
                function (array $row) use ($code, $companyNames, $debitTl, $creditTl): CompanyBalanceRow {
                    $companyId = $row['company_id'];
                    $balanceTl = null;

                    if ($code !== Currency::TRY->value) {
                        $debit = $debitTl[$companyId][$code] ?? ['total' => Money::zero(), 'complete' => true];
                        $credit = $creditTl[$companyId][$code] ?? ['total' => Money::zero(), 'complete' => true];

                        if ($debit['complete'] && $credit['complete']) {
                            $balanceTl = $debit['total']->subtract($credit['total']);
                        }
                    }

                    return new CompanyBalanceRow(
                        companyId: $companyId,
                        // Firma silinmişse ekran çökmez; ad yerine nötr metin gösterilir.
                        companyName: $companyNames[$companyId] ?? 'Firma bulunamadı',
                        balance: $row['balance'],
                        currency: Currency::from($code),
                        balanceTl: $balanceTl,
                    );
                },
                $rows,
            );
        }

        return $decorated;
    }
}
