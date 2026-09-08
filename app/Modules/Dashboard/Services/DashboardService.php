<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Services;

use App\Models\User;
use App\Modules\Authorization\Enums\Permission;
use App\Modules\CashBook\Services\CashBookService;
use App\Modules\CashBook\Support\CashSessionSummary;
use App\Modules\CashBook\Support\CashSessionTotals;
use App\Modules\Companies\Services\CompanyService;
use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
use App\Modules\Dashboard\Support\CompanyBalanceRow;
use App\Modules\Dashboard\Support\CurrencyTotals;
use App\Modules\Dashboard\Support\FinancialSummary;
use App\Modules\Dashboard\Support\OperationsSummary;
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
 * GÜN 11: Admin/Patron panosu artık İKİ ayrı, ayrı sorgulanan görünümdür —
 * `operations()` ("otelde şu an ne oluyor" — kasa) ve `finance()` ("para ve
 * cari hesap durumu" — ledger/firma). Personel hâlâ `personnel()` görür ve
 * ikisine de hiç dokunmaz.
 */
class DashboardService
{
    public function __construct(
        private readonly LedgerStatementService $statements,
        private readonly CompanyService $companies,
        private readonly StayService $stays,
        private readonly PaymentService $payments,
        private readonly CashBookService $cashBook,
    ) {}

    /**
     * Finans görünümü — yalnızca `dashboard.financial` yetkisi olanlara.
     *
     * Sorgu bütçesi sabittir: 1 açık bakiye özeti + 1 konaklama toplamı +
     * 1 tahsilat toplamı + 1 firma sayısı + (para birimi adedi) en yüksek
     * bakiye sorgusu + 1 son tahsilat + 1 tahsilat firma adı çözümü +
     * 1 yaklaşan konaklama + 1 konaklama firma adı çözümü. Kasa hiç sorgulanmaz.
     */
    public function finance(Carbon $today): FinancialSummary
    {
        $monthStart = $today->copy()->startOfMonth()->toDateString();
        $monthEnd = $today->copy()->endOfMonth()->toDateString();

        $balances = $this->statements->openBalanceTotalsByCurrency();
        $stayTotals = $this->stays->totalsByCurrencyBetween($monthStart, $monthEnd);
        $paymentTotals = $this->payments->totalsByCurrencyBetween($monthStart, $monthEnd);

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
        $companyNames = $this->companies->namesByIds(array_map('intval', $companyIds));

        return new FinancialSummary(
            currencies: $this->currencyTotals($balances, $stayTotals, $paymentTotals),
            activeCompanyCount: $this->companies->activeCount(),
            topCompanies: $this->decorateTopCompanies($topByCurrency, $companyNames),
            recentPayments: $recentPayments,
            upcomingStays: $upcomingStays,
            companyNames: $companyNames,
        );
    }

    /**
     * Operasyon görünümü — "Otelde şu anda ne oluyor?" Ledger/firma sorgusu
     * hiç ÇALIŞMAZ; yalnızca kasa (`CashBookService`) sorgulanır.
     *
     * Sorgu bütçesi sabittir: `dashboardSnapshot()` (en fazla 3) + açık
     * vardiyanın son hareketleri (1) + bekleyen devir kontrolü (1) +
     * bugünün vardiyaları (1) + toplu özetleri (en fazla 4). Hiçbiri kayıt
     * sayısıyla büyümez.
     */
    public function operations(Carbon $today): OperationsSummary
    {
        $cash = $this->cashBook->dashboardSnapshot();

        $recentMovements = $cash !== null
            ? $this->cashBook->movementsFor($cash->session, 6)
            : new Collection;

        $pendingOutgoing = null;

        if ($cash !== null) {
            $latest = $this->cashBook->latestTransferFrom($cash->session);
            $pendingOutgoing = $latest?->isPending() === true ? $latest : null;
        }

        $sessionsToday = $this->cashBook->sessionsForDate($today);
        $summariesToday = $this->cashBook->summariesFor($sessionsToday);

        return new OperationsSummary(
            cash: $cash,
            recentMovements: $recentMovements,
            pendingOutgoingTransfer: $pendingOutgoing,
            todayTotals: $this->aggregateTodayTotals($summariesToday),
            hasCountDifferenceToday: (new Collection($summariesToday))->contains(
                static fn (CashSessionSummary $summary): bool => $summary->hasCountDifference(),
            ),
        );
    }

    /**
     * Personel panosu — firma cari verisi hiç sorgulanmaz (CLAUDE.md §7.4).
     *
     * Personel yalnızca kendi üretkenliğini ve — sahibiyse — kendi kasasını
     * görür. İşletmedeki açık vardiyanın VARLIĞI ve sahibi (tutar hariç)
     * herkese görünürdür (CLAUDE.md §8/Kasa Defteri "Görünürlük").
     */
    public function personnel(User $user, Carbon $today): PersonnelSummary
    {
        $userId = (int) $user->getKey();
        $dayStart = $today->copy()->startOfDay()->toDateTimeString();
        $weekStart = $today->copy()->startOfWeek()->toDateTimeString();

        $activeSession = $this->cashBook->activeSession();
        $ownsActive = $activeSession !== null && $activeSession->opened_by === $userId;

        return new PersonnelSummary(
            staysToday: $this->stays->countCreatedBySince($userId, $dayStart),
            staysThisWeek: $this->stays->countCreatedBySince($userId, $weekStart),
            paymentsToday: $this->payments->countCreatedBySince($userId, $dayStart),
            paymentsThisWeek: $this->payments->countCreatedBySince($userId, $weekStart),
            recentStays: $this->stays->recentCreatedBy($userId, 5),
            recentPayments: $this->payments->recentCreatedBy($userId, 5),
            activeSession: $activeSession,
            ownsActiveSession: $ownsActive,
            mySummary: $ownsActive ? $this->cashBook->summaryFor($activeSession) : null,
            myRecentMovements: $ownsActive ? $this->cashBook->movementsFor($activeSession, 6) : new Collection,
            canOpenSession: $this->cashBook->canOpenSession($user),
            pendingIncomingTransfer: $this->cashBook->pendingTransferFor($user),
        );
    }

    public function canSeeFinancials(User $user): bool
    {
        return $user->can(Permission::DashboardFinancial->value);
    }

    /**
     * Bugünün vardiyalarındaki gelir/gider tiplerini para birimi bazında
     * TOPLAR. Yeni bir sorgu YAZMAZ: `summariesFor()`'un zaten hesapladığı
     * `CashSessionTotals` nesnelerini (para birimi eşleşince) toplar.
     *
     * @param  array<int, CashSessionSummary>  $summaries
     * @return array<string, CashSessionTotals>
     */
    private function aggregateTodayTotals(array $summaries): array
    {
        /** @var array<string, CashSessionTotals> $totals */
        $totals = [];

        foreach ($summaries as $summary) {
            foreach ($summary->totals as $code => $sessionTotals) {
                $totals[$code] = isset($totals[$code])
                    ? $this->combineTotals($totals[$code], $sessionTotals)
                    : $sessionTotals;
            }
        }

        $ordered = [];

        foreach (Currency::values() as $code) {
            if (isset($totals[$code])) {
                $ordered[$code] = $totals[$code];
            }
        }

        return $ordered;
    }

    private function combineTotals(CashSessionTotals $a, CashSessionTotals $b): CashSessionTotals
    {
        return new CashSessionTotals(
            currency: $a->currency,
            opening: $a->opening->add($b->opening),
            roomIncome: $a->roomIncome->add($b->roomIncome),
            extraIncome: $a->extraIncome->add($b->extraIncome),
            expense: $a->expense->add($b->expense),
            cashOut: $a->cashOut->add($b->cashOut),
            cashIn: $a->cashIn->add($b->cashIn),
        );
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
