<?php

declare(strict_types=1);

namespace App\Modules\Companies\Services;

use App\Modules\Companies\Filters\CompanyFilter;
use App\Modules\Companies\Models\Company;
use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
use App\Modules\Ledger\Services\LedgerStatementService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Cari Ekstre firma listesi (`statements.index` / `statements.print-index`).
 *
 * "Bakiye" bir `companies` kolonu OLMADIĞI için (CLAUDE.md §6/L4) durum,
 * son hareket ve bakiye filtreleri SQL'de değil burada uygulanır: filtreye
 * uyan firmaların bakiyesi ve son hareket tarihi İKİ toplu sorguda alınır,
 * süzülür ve sıralanır. Sorgu sayısı firma sayısıyla BÜYÜMEZ. V1 veri
 * hacminde (firma sayısı düşük-orta) pragmatiktir.
 *
 * 23 Eylül 2026 tasarımı (kullanıcı talimatı): hesap hareketi OLAN firmalar
 * her sıralamada üstte, hareketi olmayanlar altta listelenir.
 *
 * Para birimi seçilirse bakiye, durum ve son hareket YALNIZCA o para
 * birimine göre hesaplanır; para birimleri hiçbir yerde birleşmez (§6/L5).
 */
class StatementListService
{
    public const string STATUS_DEBTOR = 'borclu';

    public const string STATUS_CREDITOR = 'alacakli';

    public const string STATUS_CLOSED = 'kapali';

    public const string STATUS_NONE = 'hareket-yok';

    /** @var array<string, string> Bakiye durumu → etiket. */
    public const array STATUSES = [
        self::STATUS_DEBTOR => 'Borçlu',
        self::STATUS_CREDITOR => 'Alacaklı',
        self::STATUS_CLOSED => 'Kapalı',
        self::STATUS_NONE => 'Hareket yok',
    ];

    /** @var array<int, string> Son hareket filtresi (gün) → etiket. */
    public const array RECENCY = [
        7 => 'Son 7 gün',
        30 => 'Son 30 gün',
        90 => 'Son 90 gün',
    ];

    public function __construct(private readonly LedgerStatementService $statements) {}

    /**
     * @return array{
     *     ids: list<int>,
     *     balances: array<int, array<string, Money>>,
     *     lastDates: array<int, string>,
     *     statuses: array<int, string>,
     *     stats: array{total: int, debtors: int, closed: int, latest: ?string},
     *     currency: ?Currency,
     *     status: ?string,
     *     recency: ?int,
     *     hasBalanceOnly: bool,
     *     balanceSort: ?string
     * }
     */
    public function build(Request $request, CompanyFilter $filter): array
    {
        $currency = Currency::tryFrom((string) $request->query('currency', ''));
        $status = array_key_exists((string) $request->query('durum', ''), self::STATUSES) ? (string) $request->query('durum') : null;
        $recencyRaw = (int) $request->query('son_hareket', 0);
        $recency = array_key_exists($recencyRaw, self::RECENCY) ? $recencyRaw : null;
        $hasBalanceOnly = $request->query('has_balance') === '1';

        $balanceSort = (string) $request->query('balance_sort', '');
        $balanceSort = in_array($balanceSort, ['highest', 'lowest', 'recent'], true) ? $balanceSort : null;

        // Para birimi seçilmeden bakiye sıralaması ANLAMSIZDIR (§6/L5):
        // sessizce ad sırasına düşer.
        if (in_array($balanceSort, ['highest', 'lowest'], true) && $currency === null) {
            $balanceSort = null;
        }

        // Filtre SQL sırasını (ad A→Z / Z→A) belirler; bu sıra ikincil anahtardır.
        /** @var list<int> $ids */
        $ids = array_map('intval', $filter->apply(Company::query())->pluck('id')->all());

        $allBalances = $this->statements->balancesForCompanies($ids);
        $lastDates = $this->statements->lastEntryDates($ids, $currency);

        $balances = [];
        $statuses = [];

        foreach ($ids as $id) {
            $companyBalances = $allBalances[$id] ?? [];

            if ($currency !== null) {
                $companyBalances = array_intersect_key($companyBalances, [$currency->value => true]);
            }

            $balances[$id] = $companyBalances;
            $statuses[$id] = self::statusFor($companyBalances);
        }

        $latest = $lastDates === [] ? null : max($lastDates);
        $stats = [
            'total' => count($ids),
            'debtors' => count(array_keys($statuses, self::STATUS_DEBTOR, true)),
            'closed' => count(array_keys($statuses, self::STATUS_CLOSED, true)),
            'latest' => $latest,
        ];

        $since = $recency === null ? null : Carbon::today()->subDays($recency)->toDateString();

        $filtered = array_values(array_filter($ids, function (int $id) use ($status, $since, $hasBalanceOnly, $statuses, $lastDates): bool {
            if ($status !== null && $statuses[$id] !== $status) {
                return false;
            }

            if ($hasBalanceOnly && ! in_array($statuses[$id], [self::STATUS_DEBTOR, self::STATUS_CREDITOR], true)) {
                return false;
            }

            return $since === null || (isset($lastDates[$id]) && $lastDates[$id] >= $since);
        }));

        $position = array_flip($ids);
        $code = $currency?->value;
        $amount = static fn (int $id): int => $code !== null && isset($balances[$id][$code]) ? $balances[$id][$code]->minorUnits : 0;

        usort($filtered, function (int $a, int $b) use ($balanceSort, $amount, $lastDates, $position): int {
            // 1) Hareketi olanlar her zaman üstte.
            $movement = (int) isset($lastDates[$b]) <=> (int) isset($lastDates[$a]);

            if ($movement !== 0) {
                return $movement;
            }

            // 2) Seçilen sıralama; 3) filtrenin SQL sırası (ad).
            $chosen = match ($balanceSort) {
                'highest' => $amount($b) <=> $amount($a),
                'lowest' => $amount($a) <=> $amount($b),
                // Bu noktada ikisinin de hareketi var ya da ikisinin de yok.
                'recent' => isset($lastDates[$a], $lastDates[$b]) ? strcmp($lastDates[$b], $lastDates[$a]) : 0,
                default => 0,
            };

            return $chosen !== 0 ? $chosen : $position[$a] <=> $position[$b];
        });

        return [
            'ids' => $filtered,
            'balances' => $balances,
            'lastDates' => $lastDates,
            'statuses' => $statuses,
            'stats' => $stats,
            'currency' => $currency,
            'status' => $status,
            'recency' => $recency,
            'hasBalanceOnly' => $hasBalanceOnly,
            'balanceSort' => $balanceSort,
        ];
    }

    /**
     * Borçlu: herhangi bir para biriminde bize borçlu. Alacaklı: borçlu değil
     * ama bir para biriminde biz borçluyuz. Kapalı: hareketi var, tüm
     * bakiyeler sıfır. Hareket yok: hiç cari hareket yok.
     *
     * @param  array<string, Money>  $balances
     */
    public static function statusFor(array $balances): string
    {
        if ($balances === []) {
            return self::STATUS_NONE;
        }

        $values = array_values($balances);

        if (array_filter($values, static fn (Money $money): bool => $money->isPositive()) !== []) {
            return self::STATUS_DEBTOR;
        }

        if (array_filter($values, static fn (Money $money): bool => $money->isNegative()) !== []) {
            return self::STATUS_CREDITOR;
        }

        return self::STATUS_CLOSED;
    }
}
