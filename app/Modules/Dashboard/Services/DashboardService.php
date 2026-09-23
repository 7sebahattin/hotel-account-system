<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Services;

use App\Models\User;
use App\Modules\AuditLog\Enums\AuditEvent;
use App\Modules\AuditLog\Services\AuditLogger;
use App\Modules\Authorization\Enums\Permission;
use App\Modules\Companies\Services\CompanyService;
use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
use App\Modules\Core\Support\TurkishDate;
use App\Modules\Dashboard\Support\ActivityItem;
use App\Modules\Dashboard\Support\ChartMonth;
use App\Modules\Dashboard\Support\CompanyBalanceRow;
use App\Modules\Dashboard\Support\CurrencyTotals;
use App\Modules\Dashboard\Support\FinancialSummary;
use App\Modules\Dashboard\Support\MonthlyChart;
use App\Modules\Dashboard\Support\PersonnelSummary;
use App\Modules\Ledger\Services\LedgerStatementService;
use App\Modules\Payments\Services\PaymentService;
use App\Modules\Stays\Services\StayService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

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
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Finans görünümü — yalnızca `dashboard.financial` yetkisi olanlara.
     *
     * 23 Eylül 2026 yeni tasarımı: KPI kartları, aylık grafik, en yüksek
     * bakiyeli hesaplar ve alt tablo SEÇİLİ para birimindedir (varsayılan
     * TRY). Alt tablonun yalnızca açık sekmesi sorgulanır.
     *
     * Sorgu bütçesi sabittir ve kayıt/firma sayısıyla BÜYÜMEZ: açık bakiye
     * özeti, bu ay konaklama/tahsilat özeti, 2 sistem çapında TL karşılığı,
     * aktif/pasif hesap sayısı, en yüksek bakiye (yalnız seçili para birimi),
     * tüm zamanların konaklama/tahsilat özeti, grafik için 3 aylık toplam,
     * açık sekmenin 1–4 sorgusu, 1 firma adı çözümü, 2 en yüksek bakiyelilerin
     * TL karşılığı ve (yetki varsa) 1 son güncellemeler. Kasa hiç sorgulanmaz.
     * Üst sınır `DashboardTest::finans_gorunumu_sorgu_sayisi_firma_sayisiyla_birlikte_buyumez`
     * içindedir.
     */
    public function finance(
        Carbon $today,
        ?string $currency = null,
        int $months = 6,
        string $tab = 'hareketler',
        bool $withActivity = false,
        int $perPage = 10,
    ): FinancialSummary {
        $monthStart = $today->copy()->startOfMonth()->toDateString();
        $monthEnd = $today->copy()->endOfMonth()->toDateString();

        $balances = $this->statements->openBalanceTotalsByCurrency();
        $staySummary = $this->stays->summaryByCurrencyBetween($monthStart, $monthEnd);
        $paymentSummary = $this->payments->summaryByCurrencyBetween($monthStart, $monthEnd);

        // v2.1.1-D §3: "Net bakiye" kartının TL karşılığı — firma ayrımı
        // olmadan, yalnızca para birimi bazında.
        $debitTl = $this->stays->systemWideExchangeRateDebitTotalsByCurrency();
        $creditTl = $this->payments->systemWideExchangeRateCreditTotalsByCurrency();

        $currencies = $this->currencyTotals($balances, $staySummary, $paymentSummary, $debitTl, $creditTl);
        $counts = $this->companies->activityCounts();

        $selected = $this->resolveCurrency($currencies, $currency);
        $months = in_array($months, FinancialSummary::CHART_MONTHS, true) ? $months : FinancialSummary::CHART_MONTHS[0];
        $tab = array_key_exists($tab, FinancialSummary::TABS) ? $tab : array_key_first(FinancialSummary::TABS);

        if ($selected === null) {
            return new FinancialSummary(
                currencies: $currencies,
                activeCompanyCount: $counts['active'],
                topCompanies: [],
                recentPayments: new Collection,
                upcomingStays: new Collection,
                companyNames: [],
                passiveCompanyCount: $counts['passive'],
                chartMonths: $months,
                tab: $tab,
                activity: $withActivity ? $this->activity() : null,
            );
        }

        $topRows = $this->statements->topDebtorCompanies($selected, 5);
        $topByCurrency = $topRows === [] ? [] : [$selected->value => $topRows];

        $stayLifetime = $this->stays->lifetimeSummaryByCurrency();
        $paymentLifetime = $this->payments->lifetimeSummaryByCurrency();
        $selectedStays = $stayLifetime[$selected->value] ?? null;
        $selectedPayments = $paymentLifetime[$selected->value] ?? null;

        // ---- Alt tablo: yalnızca açık sekme sorgulanır.
        $ledgerPage = null;
        $ledgerBalances = [];
        $recentPayments = new Collection;
        $upcomingStays = new Collection;
        $accountRows = [];

        if ($tab === 'konaklamalar') {
            $upcomingStays = $this->stays->currentAndUpcoming($today->toDateString(), $perPage);
        } elseif ($tab === 'tahsilatlar') {
            $recentPayments = $this->payments->recent($perPage);
        } elseif ($tab === 'borc-alacak') {
            $accountRows = $this->statements->nonZeroBalances($selected, $perPage);
        } else {
            $page = $this->statements->recentEntriesWithBalance($selected, $perPage, 'sayfa');
            $ledgerPage = $page['entries'];
            $ledgerBalances = $page['balances'];
        }

        // Görünen tüm hesap adları TEK sorguda çözülür — N+1 yoktur.
        /** @var array<int, int> $companyIds */
        $companyIds = array_values(array_unique(array_map('intval', array_merge(
            array_column($topRows, 'company_id'),
            array_column($accountRows, 'company_id'),
            $recentPayments->pluck('company_id')->all(),
            $upcomingStays->pluck('company_id')->all(),
            $ledgerPage === null ? [] : array_map(
                static fn ($entry): int => (int) $entry->company_id,
                $ledgerPage->items(),
            ),
        ))));
        $companyNames = $this->companies->namesByIds($companyIds);

        $topCompanyIds = array_column($topRows, 'company_id');
        $topDebitTl = $selected === Currency::TRY ? [] : $this->stays->exchangeRateDebitTotalsForCompanies($topCompanyIds);
        $topCreditTl = $selected === Currency::TRY ? [] : $this->payments->exchangeRateCreditTotalsForCompanies($topCompanyIds);

        return new FinancialSummary(
            currencies: $currencies,
            activeCompanyCount: $counts['active'],
            topCompanies: $this->decorateTopCompanies($topByCurrency, $companyNames, $topDebitTl, $topCreditTl),
            recentPayments: $recentPayments,
            upcomingStays: $upcomingStays,
            companyNames: $companyNames,
            selectedCurrency: $selected,
            passiveCompanyCount: $counts['passive'],
            totalStayCount: array_sum(array_column($stayLifetime, 'count')),
            totalNightCount: array_sum(array_column($stayLifetime, 'nights')),
            lifetimeStays: $selectedStays['total'] ?? Money::zero(),
            lifetimePayments: $selectedPayments['total'] ?? Money::zero(),
            lifetimePaymentCount: $selectedPayments['count'] ?? 0,
            averageNightly: $selectedStays === null ? null : self::average($selectedStays['total'], $selectedStays['nights']),
            chart: $this->monthlyChart($selected, $today, $months),
            chartMonths: $months,
            tab: $tab,
            ledgerEntries: $ledgerPage,
            ledgerBalances: $ledgerBalances,
            accountBalances: array_map(
                fn (array $row): CompanyBalanceRow => new CompanyBalanceRow(
                    companyId: $row['company_id'],
                    companyName: $companyNames[$row['company_id']] ?? 'Firma bulunamadı',
                    balance: $row['balance'],
                    currency: $selected,
                ),
                $accountRows,
            ),
            activity: $withActivity ? $this->activity() : null,
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
     * @param  array<string, array{total: Money, count: int, nights: int}>  $stayTotals
     * @param  array<string, array{total: Money, count: int}>  $paymentTotals
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
                    staysThisMonth: $stayTotals[$code]['total'] ?? Money::zero(),
                    paymentsThisMonth: $paymentTotals[$code]['total'] ?? Money::zero(),
                    netTl: $netTl,
                    staysCountThisMonth: $stayTotals[$code]['count'] ?? 0,
                    nightsThisMonth: $stayTotals[$code]['nights'] ?? 0,
                    paymentsCountThisMonth: $paymentTotals[$code]['count'] ?? 0,
                );
            },
            $ordered,
        );
    }

    /**
     * Seçilen para birimi: istekte gelen ve panoda verisi olan para birimi;
     * yoksa TRY, o da yoksa ilk para birimi. Veri yoksa `null`.
     *
     * @param  array<int, CurrencyTotals>  $currencies
     */
    private function resolveCurrency(array $currencies, ?string $requested): ?Currency
    {
        $available = array_map(static fn (CurrencyTotals $totals): Currency => $totals->currency, $currencies);

        foreach ([Currency::tryFrom((string) $requested), Currency::TRY] as $candidate) {
            if ($candidate !== null && in_array($candidate, $available, true)) {
                return $candidate;
            }
        }

        return $available[0] ?? null;
    }

    /**
     * Son N ayın konaklama/tahsilat toplamları ve ay sonu net bakiyesi —
     * tek para biriminde. Üç toplu sorgu; hareketsiz aylar sıfırla dolar.
     */
    private function monthlyChart(Currency $currency, Carbon $today, int $months): MonthlyChart
    {
        $first = $today->copy()->startOfMonth()->subMonths($months - 1);
        $from = $first->toDateString();
        $to = $today->copy()->endOfMonth()->toDateString();

        $stays = $this->stays->monthlyTotals($currency, $from, $to);
        $payments = $this->payments->monthlyTotals($currency, $from, $to);
        $ledger = $this->statements->monthlyNetChanges($currency, $from, $to);

        $running = $ledger['opening'];
        $series = [];

        for ($i = 0; $i < $months; $i++) {
            $month = $first->copy()->addMonths($i);
            $key = $month->format('Y-m');
            $running = $running->add($ledger['months'][$key] ?? Money::zero());

            $series[] = new ChartMonth(
                key: $key,
                label: TurkishDate::shortMonth($month).' '.$month->year,
                stays: $stays[$key] ?? Money::zero(),
                payments: $payments[$key] ?? Money::zero(),
                net: $running,
            );
        }

        return new MonthlyChart($currency, $series);
    }

    /**
     * Pano "Son Güncellemeler": konaklama, tahsilat ve cari hesap kayıtlarındaki
     * son veri değişiklikleri (denetim kaydından, TEK sorgu).
     *
     * @return array<int, ActivityItem>
     */
    private function activity(): array
    {
        $subjects = [
            'stay' => ['Konaklama kaydı', 'bed', 'purple'],
            'payment' => ['Tahsilat kaydı', 'card', 'green'],
            'company' => ['Cari hesap bilgileri', 'building', 'blue'],
        ];

        $verbs = [
            AuditEvent::Created->value => 'oluşturuldu',
            AuditEvent::Updated->value => 'güncellendi',
            AuditEvent::Cancelled->value => 'iptal edildi',
            AuditEvent::Deleted->value => 'silindi',
            AuditEvent::Restored->value => 'geri alındı',
            AuditEvent::ExchangeRateCorrected->value => 'için kur düzeltildi',
        ];

        $logs = $this->audit->recentChanges(
            array_keys($subjects),
            array_map(static fn (string $event): AuditEvent => AuditEvent::from($event), array_keys($verbs)),
            5,
        );

        return $logs->map(function ($log) use ($subjects, $verbs): ActivityItem {
            [$noun, $icon, $tone] = $subjects[(string) $log->auditable_type] ?? ['Kayıt', 'log', 'blue'];
            $event = $log->event->value;

            // İptal/silme kırmızı değil gül tonudur; anlam metindedir (§11.6).
            if ($event === AuditEvent::Cancelled->value || $event === AuditEvent::Deleted->value) {
                $tone = 'rose';
            } elseif ($event === AuditEvent::Updated->value) {
                $icon = 'pencil';
            }

            return new ActivityItem(
                title: $noun.' '.($verbs[$event] ?? 'değişti'),
                userName: $log->user_name,
                at: $log->created_at,
                icon: $icon,
                tone: $tone,
            );
        })->all();
    }

    /** Gecelik ortalama — tamsayı kuruş, yarım yukarı yuvarlanır. */
    private static function average(Money $total, int $nights): ?Money
    {
        if ($nights <= 0) {
            return null;
        }

        return Money::fromMinorUnits(intdiv(2 * $total->minorUnits + $nights, 2 * $nights));
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
