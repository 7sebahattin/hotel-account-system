<?php

declare(strict_types=1);

namespace App\Modules\Stays\Services;

use App\Models\User;
use App\Modules\AuditLog\Enums\AuditEvent;
use App\Modules\AuditLog\Services\AuditLogger;
use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Companies\Services\CompanyService;
use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
use App\Modules\Ledger\Models\LedgerEntry;
use App\Modules\Ledger\Services\LedgerService;
use App\Modules\Payments\Services\PaymentAllocationService;
use App\Modules\Stays\Enums\StayPricingMode;
use App\Modules\Stays\Enums\StayStatus;
use App\Modules\Stays\Exceptions\CannotUpdateCancelledStay;
use App\Modules\Stays\Exceptions\ExchangeRateAlreadyRecorded;
use App\Modules\Stays\Exceptions\StayAlreadyCancelled;
use App\Modules\Stays\Exceptions\StayHasLinkedAllocations;
use App\Modules\Stays\Exceptions\StayNotCancelled;
use App\Modules\Stays\Models\Stay;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

/**
 * Konaklama modülünün dışa açık tek kapısı (CLAUDE.md §4.3).
 *
 * Her yazma işlemi konaklama kaydı ile cari hareketini AYNI transaction içinde
 * ele alır (CLAUDE.md §6/L6): biri yazılıp diğeri yazılamazsa ikisi de geri alınır.
 *
 * Değişmez: bir konaklamanın her an **tam olarak bir geçerli borç hareketi**
 * vardır (iptal edilmişse hiç yoktur). Düzeltmeler eski hareketi ters kaydeder
 * ve yenisini ekler; eski satır asla güncellenmez.
 */
class StayService
{
    /**
     * Değişmesi cari hareketini etkileyen alanlar.
     *
     * @var array<int, string>
     */
    public const FINANCIAL_FIELDS = [
        'company_id', 'check_in_date', 'check_out_date',
        'pricing_mode', 'daily_rate', 'amount', 'currency',
    ];

    public function __construct(
        private readonly LedgerService $ledger,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?User $actor = null): Stay
    {
        $user = $this->resolveActor($actor);

        return $this->withCheckInDateLock($data['check_in_date'], function () use ($data, $user): Stay {
            return DB::transaction(function () use ($data, $user): Stay {
                $individualAccountId = empty($data['individual_account_id'])
                    ? null
                    : (int) $data['individual_account_id'];
                $data = $this->resolveAccount($data, $user);
                app(CompanyService::class)->lockAccounts([(int) $data['company_id']]);
                $stay = new Stay;
                $this->applyInput($stay, $data);
                $this->ensureNoActiveDuplicateCheckIn($stay, individualAccountId: $individualAccountId);
                $stay->status = StayStatus::Active;
                $stay->created_by = $user->getKey();
                $stay->updated_by = $user->getKey();
                $stay->save();

                $entry = $this->postDebit($stay, $user);
                app(PaymentAllocationService::class)->applyAdvancesToStay($stay->id, $user);

                $this->auditLogger->log(
                    event: AuditEvent::Created,
                    subject: $stay,
                    newValues: $this->snapshot($stay) + ['ledger_entry_id' => $entry->id],
                    user: $user,
                );

                return $stay;
            });
        });
    }

    /**
     * Konaklamayı günceller.
     *
     * Finansal alanlardan biri değişirse akış şudur (CLAUDE.md §6/L7):
     *   1. mevcut cari hareketi ters kaydedilir
     *   2. konaklama güncellenir
     *   3. yeni borç hareketi oluşturulur
     * Üçü de aynı transaction içindedir.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws CannotUpdateCancelledStay
     */
    public function update(Stay $stay, array $data, ?User $actor = null): Stay
    {
        if ($stay->isCancelled()) {
            throw CannotUpdateCancelledStay::make($stay->getKey());
        }

        $user = $this->resolveActor($actor);

        $lockDate = Carbon::parse($data['check_in_date'] ?? $stay->check_in_date)->toDateString();

        return $this->withCheckInDateLock($lockDate, function () use ($stay, $data, $user, $lockDate): Stay {
            return DB::transaction(function () use ($stay, $data, $user, $lockDate): Stay {
                $this->lockForFinancialChange($stay, $data);
                if ($stay->isCancelled()) {
                    throw CannotUpdateCancelledStay::make($stay->id);
                }
                $individualAccountId = empty($data['individual_account_id'])
                    ? null
                    : (int) $data['individual_account_id'];
                $data = $this->resolveAccount($data, $user, $stay);
                $before = $this->snapshot($stay);
                $financialBefore = $this->financialSnapshot($stay);

                // Aday kayıt, gerçek kayıtla AYNI hesaplamadan geçer: gece sayısı ve
                // günlük ücretlendirmede toplam yeniden hesaplandıktan sonra
                // karşılaştırılır. Aksi hâlde formdan gelen ham tutar karşılaştırılırdı.
                $candidate = clone $stay;
                $this->applyInput($candidate, $data);
                if ($candidate->check_in_date->toDateString() !== $lockDate) {
                    throw ValidationException::withMessages(['check_in_date' => 'Konaklamanın giriş tarihi değişmiş. Sayfayı yenileyin.']);
                }
                $this->ensureNoActiveDuplicateCheckIn(
                    $candidate,
                    exceptStayId: $stay->id,
                    individualAccountId: $individualAccountId,
                );
                $financialChanged = $this->financialSnapshot($candidate) !== $financialBefore;

                $reversal = null;

                if ($financialChanged) {
                    $active = $this->ledger->activeEntryForSource(Stay::LEDGER_SOURCE_TYPE, $stay->getKey());

                    if ($active !== null) {
                        $reversal = $this->ledger->reverse($active, actor: $user);
                    }
                }

                $this->applyInput($stay, $data);
                $stay->updated_by = $user->getKey();
                $stay->save();

                $newEntry = $financialChanged ? $this->postDebit($stay, $user) : null;
                if ($financialChanged) {
                    app(PaymentAllocationService::class)->reconcileStay($stay->id, $user);
                }

                $after = $this->snapshot($stay);
                $changed = $this->changedFields($before, $after);

                if ($changed === [] && ! $financialChanged) {
                    return $stay;
                }

                $context = [];

                if ($financialChanged) {
                    // Denetim kaydı, düzeltme zincirinin tamamını taşır.
                    $context = [
                        'financial_change' => true,
                        'reversed_ledger_entry_id' => $reversal?->reverses_entry_id,
                        'reversal_ledger_entry_id' => $reversal?->id,
                        'new_ledger_entry_id' => $newEntry?->id,
                    ];
                }

                $this->auditLogger->log(
                    event: AuditEvent::Updated,
                    subject: $stay,
                    oldValues: array_intersect_key($before, $changed),
                    newValues: $changed + $context,
                    user: $user,
                );

                return $stay;
            });
        });
    }

    /**
     * Konaklamayı iptal eder ve cari hareketini ters kaydeder.
     *
     * İptal geri alınamaz (V1'de restore akışı yoktur).
     *
     * @throws StayAlreadyCancelled
     */
    public function cancel(Stay $stay, ?User $actor = null): Stay
    {
        if ($stay->isCancelled()) {
            throw StayAlreadyCancelled::make($stay->getKey());
        }

        $user = $this->resolveActor($actor);

        return DB::transaction(function () use ($stay, $user): Stay {
            $this->lockForFinancialChange($stay);
            if ($stay->isCancelled()) {
                throw StayAlreadyCancelled::make($stay->id);
            }
            $active = $this->ledger->activeEntryForSource(Stay::LEDGER_SOURCE_TYPE, $stay->getKey());
            $reversal = $active !== null ? $this->ledger->reverse($active, actor: $user) : null;

            $stay->status = StayStatus::Cancelled;
            $stay->updated_by = $user->getKey();
            $stay->save();

            app(PaymentAllocationService::class)->releaseStay($stay->id, $user);

            $this->auditLogger->log(
                event: AuditEvent::Cancelled,
                subject: $stay,
                oldValues: ['status' => StayStatus::Active->value],
                newValues: [
                    'status' => StayStatus::Cancelled->value,
                    'reversed_ledger_entry_id' => $active?->id,
                    'reversal_ledger_entry_id' => $reversal?->id,
                ],
                user: $user,
            );

            return $stay;
        });
    }

    /**
     * Konaklamayı veritabanından KALICI olarak siler (hard delete).
     *
     * Yalnızca `StayPolicy::forceDelete()`ten geçen çağrılar buraya ulaşır
     * (Admin + zaten İPTAL edilmiş kayıt), ama kural burada da AYRICA
     * doğrulanır — Service son savunmadır (CLAUDE.md §7.1). Ledger append-only
     * olduğu için (§6/L1) bu işlem konaklamanın ledger etkisini GERİ ALMAZ:
     * iptal sırasında zaten yazılan orijinal + ters kayıt çifti deftere
     * kalıcı olarak yazılı kalır, yalnızca artık gereksiz olan Stay satırının
     * kendisi kaldırılır.
     *
     * Bu konaklamaya bugüne kadar bir ödeme dağılımı uygulanmışsa (geçmişte
     * serbest bırakılmış olsa bile — `payment_allocations` de append-only'dir)
     * `stay_id` FK kısıtı (RESTRICT) silmeyi veritabanı düzeyinde reddeder;
     * bu durum StayHasLinkedAllocations olarak dışarı taşınır.
     *
     * @throws StayNotCancelled
     * @throws StayHasLinkedAllocations
     */
    public function forceDelete(Stay $stay, ?User $actor = null): void
    {
        if (! $stay->isCancelled()) {
            throw StayNotCancelled::make($stay->getKey());
        }

        $user = $this->resolveActor($actor);
        if (! $user->hasRole(RoleName::Admin->value)) {
            throw new AuthorizationException('Kalıcı silme yalnızca admin hesabına açıktır.');
        }

        DB::transaction(function () use ($stay, $user): void {
            app(CompanyService::class)->lockAccounts([$stay->company_id]);
            $fresh = Stay::query()->whereKey($stay->id)->lockForUpdate()->firstOrFail();
            if (! $fresh->isCancelled()) {
                throw StayNotCancelled::make($fresh->id);
            }
            $stay->setRawAttributes($fresh->getAttributes(), true);
            $before = $this->snapshot($stay);

            try {
                $stay->forceDelete();
            } catch (QueryException $e) {
                // Bağlantı hatası dışındaki SQL sorunlarını "bağlı ödeme var" diye gizleme.
                if ((int) ($e->errorInfo[1] ?? 0) !== 1451) {
                    throw $e;
                }
                throw StayHasLinkedAllocations::make($stay->getKey(), previous: $e);
            }

            $this->auditLogger->log(
                event: AuditEvent::Deleted,
                subject: $stay,
                oldValues: $before,
                user: $user,
            );
        });
    }

    /**
     * Verilen (zaten filtrelenmiş) sorgu için para birimi bazında toplam.
     *
     * Aktif ve iptal edilmiş tutarlar AYRI tutulur — iptal edilenler normal
     * muhasebe toplamına karışmaz (kullanıcı talimatı, onaylı geliştirme
     * kapsamı notu) ama görünürlükten de kaldırılmaz; ekran ikisini de
     * gösterir. Para birimleri hiçbir zaman birleştirilmez (CLAUDE.md §6/L5).
     *
     * Sorgu KLONLANIR: aynı $query hem bu toplam hem de asıl liste (sayfalama
     * veya tam liste) için kullanılabilsin diye. `reorder()` olası bir
     * ORDER BY'ı temizler — aksi hâlde GROUP BY ile birlikte MySQL'in
     * ONLY_FULL_GROUP_BY modunda hataya yol açardı. `toBase()` sorguyu ham
     * query builder'a indirger — aksi hâlde Eloquent bu satırları `Stay`
     * modeli olarak hidratlamaya çalışır ve `currency`/`status` alanları
     * (model cast'i yüzünden) ENUM NESNESİ olarak gelir; `(string)` dönüşümü
     * o zaman fatal error verir (native enum'larda `__toString()` yoktur).
     *
     * `$query` `Builder<covariant Model>` olarak tiplenir (Stay'e daraltılmaz):
     * çağıran taraf `QueryFilter::apply()`'ın döndürdüğü sorguyu doğrudan
     * geçirir ve o metot da aynı kovaryant imzayı taşır (PHPStan level 5).
     *
     * @param  Builder<covariant Model>  $query
     * @return Collection<string, array{currency: Currency, count: int, nights: int, total: Money, activeTotal: Money, cancelledTotal: Money, cancelledCount: int}>
     */
    public function totalsFor(Builder $query): Collection
    {
        $rows = (clone $query)
            ->toBase()
            ->reorder()
            ->selectRaw('currency, status, COUNT(*) as count, COALESCE(SUM(nights), 0) as nights, CAST(COALESCE(SUM(amount), 0) AS CHAR) as total')
            ->groupBy('currency', 'status')
            ->get();

        $totals = collect();

        foreach ($rows as $row) {
            $code = (string) $row->currency;

            $bucket = $totals->get($code, [
                'currency' => Currency::from($code),
                'count' => 0,
                'nights' => 0,
                'total' => Money::zero(),
                'activeTotal' => Money::zero(),
                'cancelledTotal' => Money::zero(),
                'cancelledCount' => 0,
            ]);

            $amount = Money::fromDecimal((string) $row->total);
            $bucket['count'] += (int) $row->count;
            $bucket['nights'] += (int) $row->nights;
            $bucket['total'] = $bucket['total']->add($amount);

            if ($row->status === StayStatus::Cancelled->value) {
                $bucket['cancelledTotal'] = $bucket['cancelledTotal']->add($amount);
                $bucket['cancelledCount'] += (int) $row->count;
            } else {
                $bucket['activeTotal'] = $bucket['activeTotal']->add($amount);
            }

            $totals->put($code, $bucket);
        }

        return $totals->sortKeys();
    }

    /**
     * Firmanın son konaklamaları — firma detay ekranı için.
     *
     * @return Collection<int, Stay>
     */
    public function recentForCompany(int $companyId, int $limit = 10): Collection
    {
        return Stay::query()
            ->where('company_id', $companyId)
            ->orderByDesc('check_in_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function countForCompany(int $companyId): int
    {
        return Stay::query()->where('company_id', $companyId)->count();
    }

    /**
     * Verilen kimliklerden hâlâ VAR OLANLAR — tek sorguda.
     *
     * Cari ekstre, hareketin kaynak kaydına bağlantı verirken kullanır;
     * satır satır sorgu (N+1) atmadan "kaynak kayıt bulunamadı" durumunu
     * dürüstçe gösterebilmek içindir.
     *
     * @param  array<int, int>  $ids
     * @return array<int, int>
     */
    public function existingIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return Stay::query()->whereIn('id', $ids)->pluck('id')->all();
    }

    /**
     * Cari ekstre kaynak satırları için oluşturan/düzenleyen bilgisini toplu getirir.
     *
     * @param  array<int, int>  $ids
     * @return array<int, array{edited: bool, user: string, at: string}>
     */
    public function statementSourceInfoFor(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return Stay::query()
            ->with(['creator:id,name', 'updater:id,name'])
            ->whereIn('id', $ids)
            ->get(['id', 'created_by', 'updated_by', 'created_at', 'updated_at'])
            ->mapWithKeys(function (Stay $stay): array {
                $edited = $stay->updated_at->gt($stay->created_at);
                $actor = $edited ? $stay->updater : $stay->creator;
                $at = $edited ? $stay->updated_at : $stay->created_at;

                return [$stay->getKey() => [
                    'edited' => $edited,
                    'user' => $actor->name,
                    'at' => $at->format('d.m.Y H:i'),
                ]];
            })
            ->all();
    }

    /**
     * Bir odanın herhangi bir konaklamada kullanılıp kullanılmadığı — Room
     * modülünün silme koruması bu sorguya dayanır (CLAUDE.md §4.3/1: Rooms
     * modülü Stay modelini doğrudan KULLANMAZ, bu servis üzerinden sorar).
     */
    public function roomHasHistory(int $roomId): bool
    {
        return Stay::query()->where('room_id', $roomId)->exists();
    }

    /**
     * `id` ile tek konaklama — FxCorrection modülünün tek bilmesi GEREKEN
     * erişim yoludur (§4.3/1: o modül `Stay::class`i hiç KULLANMAZ).
     */
    public function findOrFail(int $id): Stay
    {
        return Stay::query()->findOrFail($id);
    }

    /**
     * Verilen konaklama id'leri için kur anlık görüntüsü — TEK sorguda.
     *
     * Cari Ekstre satır bazında TL karşılığı göstermek için kullanılır
     * (v2.1.1-D §3): Companies modülü `Stay::class`i hiç KULLANMAZ, yalnızca
     * bu servis üzerinden (§4.3/1) — durum (aktif/iptal) filtrelenmez, çünkü
     * bir kaydın orijinal borcu VE onun ters kaydı AYNI anlık görüntüyü
     * paylaşır ve zaten birbirini götürür; burada durumu ayırmak GEREKMEZ.
     *
     * @param  array<int, int>  $ids
     * @return array<int, array{rate: ?string, equivalent: ?string}>
     */
    public function exchangeRateSnapshotsFor(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return Stay::query()
            ->whereIn('id', $ids)
            ->get(['id', 'exchange_rate_to_try', 'try_equivalent'])
            ->mapWithKeys(fn (Stay $stay): array => [
                $stay->getKey() => ['rate' => $stay->exchange_rate_to_try, 'equivalent' => $stay->try_equivalent],
            ])
            ->all();
    }

    /**
     * TÜM zamanlar TL karşılığı toplamı (yalnızca AKTİF konaklamalar),
     * verilen firmalar için, para birimi bazında — TEK sorgu.
     *
     * Yalnızca AKTİF kayıtlar sayılır: iptal edilmiş bir konaklamanın
     * orijinal borcu ile ters kaydı zaten birbirini götürür (§6/L7), bu
     * yüzden ledger'daki net etkiyle AYNI sonucu üretir — ledger'ı ayrıca
     * satır satır dolaşmaya GEREK YOKTUR (Firma Bakiye Listesi/Dashboard
     * için, çok sayıda firma kapsayabileceğinden ucuz kalması bilinçlidir).
     *
     * @param  array<int, int>  $companyIds
     * @return array<int, array<string, array{total: Money, complete: bool}>>
     */
    public function exchangeRateDebitTotalsForCompanies(array $companyIds): array
    {
        if ($companyIds === []) {
            return [];
        }

        return $this->mapExchangeRateTotals(
            $this->exchangeRateDebitQuery()->whereIn('company_id', $companyIds)->get()
        );
    }

    /**
     * Aynı toplam, TÜM firmalar için — Dashboard'ın sistem çapındaki
     * "açık alacak" TL karşılığı içindir (v2.1.1-D §3).
     *
     * @return array<int, array<string, array{total: Money, complete: bool}>>
     */
    public function systemWideExchangeRateDebitTotals(): array
    {
        return $this->mapExchangeRateTotals($this->exchangeRateDebitQuery()->get());
    }

    /**
     * Aynı toplam, firma ayrımı OLMADAN, yalnızca para birimi bazında —
     * Dashboard'ın "Açık alacak" kartının TL karşılığı içindir (v2.1.1-D §3):
     * o kart zaten tüm firmaları tek toplamda gösterir (CLAUDE.md §8/Dashboard
     * "Açık alacak/borç firma bazında NETLENDİKTEN SONRA toplanır"), bu yüzden
     * TL karşılığı da aynı granülaritede olmalıdır.
     *
     * @return array<string, array{total: Money, complete: bool}>
     */
    public function systemWideExchangeRateDebitTotalsByCurrency(): array
    {
        $rows = DB::table('stays')
            ->select('currency')
            ->selectRaw('CAST(COALESCE(SUM(try_equivalent), 0) AS CHAR) as total')
            ->selectRaw('SUM(CASE WHEN try_equivalent IS NULL THEN 1 ELSE 0 END) as missing')
            ->where('status', StayStatus::Active->value)
            ->where('currency', '<>', Currency::TRY->value)
            ->groupBy('currency')
            ->get();

        $result = [];

        foreach ($rows as $row) {
            $result[(string) $row->currency] = [
                'total' => Money::fromDecimal((string) $row->total),
                'complete' => (int) $row->missing === 0,
            ];
        }

        return $result;
    }

    private function exchangeRateDebitQuery(): QueryBuilder
    {
        return DB::table('stays')
            ->select('company_id', 'currency')
            ->selectRaw('CAST(COALESCE(SUM(try_equivalent), 0) AS CHAR) as total')
            ->selectRaw('SUM(CASE WHEN try_equivalent IS NULL THEN 1 ELSE 0 END) as missing')
            ->where('status', StayStatus::Active->value)
            ->where('currency', '<>', Currency::TRY->value)
            ->groupBy('company_id', 'currency');
    }

    /**
     * @param  Collection<int, \stdClass>  $rows
     * @return array<int, array<string, array{total: Money, complete: bool}>>
     */
    private function mapExchangeRateTotals(Collection $rows): array
    {
        $result = [];

        foreach ($rows as $row) {
            $result[(int) $row->company_id][(string) $row->currency] = [
                'total' => Money::fromDecimal((string) $row->total),
                'complete' => (int) $row->missing === 0,
            ];
        }

        return $result;
    }

    /**
     * Kur bilgisi EKSİK yabancı para birimli konaklamalar — Geçmiş Döviz
     * Düzeltme kuyruğu (v2.1.1-C §3). İptal edilmiş kayıtlar da gösterilir:
     * geçmiş veri dürüstçe tamamlanır, durumdan bağımsız.
     *
     * @return Collection<int, Stay>
     */
    public function missingExchangeRate(): Collection
    {
        return Stay::query()
            ->with('company')
            ->where('currency', '<>', Currency::TRY->value)
            ->whereNull('exchange_rate_to_try')
            ->orderBy('check_out_date')
            ->orderBy('id')
            ->get();
    }

    /**
     * Geçmiş bir konaklamanın EKSİK kur anlık görüntüsünü doldurur.
     *
     * v2.1.1-C §3'ün katı kuralları: orijinal tutar/para birimi değişmez,
     * ikinci bir finansal işlem oluşmaz, cari hareketi ters kaydedilmez/
     * yeniden işlenmez — yalnızca eksik metadata yazılır. Bu yüzden normal
     * `update()` akışından TAMAMEN AYRIDIR: ledger'a hiç dokunmaz.
     *
     * Zaten kayıtlı bir kuru "düzeltmek" için bu metot KULLANILAMAZ — normal
     * ekranlardan rastgele düzenlemeyi önlemek kasıtlıdır (§3: "do not allow
     * casual editing of completed historical FX values").
     *
     * @throws ExchangeRateAlreadyRecorded
     */
    public function correctHistoricalExchangeRate(Stay $stay, string $rate, ?User $actor = null): Stay
    {
        if ($stay->exchange_rate_to_try !== null) {
            throw ExchangeRateAlreadyRecorded::make($stay->getKey());
        }

        if ($stay->currency === Currency::TRY) {
            throw new InvalidArgumentException('TRY konaklamalar için kur düzeltmesi yapılamaz.');
        }

        $user = $this->resolveActor($actor);
        $normalizedRate = Money::normalizeInput($rate);
        $tryEquivalent = $stay->amountMoney()->multiplyByRate($normalizedRate)->toDecimal();

        return DB::transaction(function () use ($stay, $normalizedRate, $tryEquivalent, $user): Stay {
            $stay->exchange_rate_to_try = $normalizedRate;
            $stay->try_equivalent = $tryEquivalent;
            $stay->updated_by = $user->getKey();
            $stay->save();

            $this->auditLogger->log(
                event: AuditEvent::ExchangeRateCorrected,
                subject: $stay,
                oldValues: ['exchange_rate_to_try' => null, 'try_equivalent' => null],
                newValues: ['exchange_rate_to_try' => $normalizedRate, 'try_equivalent' => $tryEquivalent],
                user: $user,
            );

            return $stay;
        });
    }

    // ------------------------------------------------------------------ iç

    /** @return array<int, array<string, mixed>> */
    public function allocationCandidates(int $companyId, Currency $currency): array
    {
        $ids = Stay::query()->where('company_id', $companyId)->where('currency', $currency)
            ->where('status', StayStatus::Active)->orderBy('check_in_date')->orderBy('id')->pluck('id')->all();

        return array_values($this->allocationDetails($ids));
    }

    /** @param array<int, int> $ids
     * @return array<int, array<string, mixed>>
     */
    public function allocationDetails(array $ids): array
    {
        $models = Stay::query()->whereIn('id', $ids)->get()->keyBy('id');
        $result = [];
        foreach ($ids as $id) {
            if (! isset($models[$id])) {
                continue;
            }
            $stay = $models[$id];
            $result[$id] = ['id' => $stay->id, 'company_id' => $stay->company_id,
                'currency' => $stay->currency->value, 'amount' => $stay->amount,
                'guest_name' => $stay->guest_name, 'check_in_date' => $stay->check_in_date->toDateString(),
                'cancelled' => $stay->isCancelled()];
        }

        return $result;
    }

    /** @param array<string, mixed> $data */
    private function lockForFinancialChange(Stay $stay, array $data = []): void
    {
        $oldAccount = $stay->company_id;
        $target = (int) (! empty($data['company_id']) ? $data['company_id'] : ($data['individual_account_id'] ?? $oldAccount));
        app(CompanyService::class)->lockAccounts([$oldAccount, $target]);
        $fresh = Stay::query()->whereKey($stay->id)->lockForUpdate()->firstOrFail();
        if ($fresh->company_id !== $oldAccount) {
            throw ValidationException::withMessages(['company_id' => 'Konaklamanın cari hesabı değişmiş. Sayfayı yenileyin.']);
        }
        $stay->setRawAttributes($fresh->getAttributes(), true);
        $stay->unsetRelations();
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function resolveAccount(array $data, User $user, ?Stay $stay = null): array
    {
        if (! empty($data['company_id']) && ! empty($data['individual_account_id'])) {
            throw ValidationException::withMessages(['individual_account_id' => 'Firma ve bireysel hesap aynı anda seçilemez.']);
        }

        // Kısmi servis güncellemelerinde mevcut hesap korunur.
        if ($stay !== null && ! array_key_exists('company_id', $data) && ! array_key_exists('individual_account_id', $data)) {
            return $data;
        }

        if (empty($data['company_id'])) {
            $data['company_id'] = app(CompanyService::class)->individualForStay(
                ! empty($data['individual_account_id']) ? (int) $data['individual_account_id'] : null,
                (string) ($data['guest_name'] ?? $stay->guest_name ?? ''),
                $user,
                $stay?->company_id,
            );
        }

        return $data;
    }

    private function postDebit(Stay $stay, User $user): LedgerEntry
    {
        return $this->ledger->debit(
            companyId: $stay->company_id,
            amount: $stay->amountMoney(),
            currency: $stay->currency,
            entryDate: $stay->check_out_date,
            description: $stay->ledgerDescription(),
            sourceType: Stay::LEDGER_SOURCE_TYPE,
            sourceId: $stay->getKey(),
            actor: $user,
        );
    }

    /**
     * Aynı giriş tarihi için tüm yazmaları tek sıraya koyar. Tarih düzeyindeki
     * kilit, seçili hesap kimliği ile serbest isim akışlarının birbirini
     * atlamasını önler; duplicate kararı aşağıdaki mevcut kuralla verilir.
     * Named lock transaction dışında alınır ve commit/rollback sonrasında bırakılır.
     *
     * @template T
     * @param  Closure(): T  $operation
     * @return T
     */
    private function withCheckInDateLock(string|Carbon $checkInDate, Closure $operation): mixed
    {
        $date = Carbon::parse($checkInDate)->toDateString();
        $name = 'stay-checkin:'.substr(hash('sha256', $date), 0, 40);
        $lock = DB::selectOne('SELECT GET_LOCK(?, 10) AS acquired', [$name]);
        if ((int) ($lock->acquired ?? 0) !== 1) {
            throw new RuntimeException('Konaklama giriş tarihi kilidi alınamadı. İşlemi tekrar deneyin.');
        }

        try {
            return $operation();
        } finally {
            $released = DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$name]);
            if ((int) ($released->released ?? 0) !== 1) {
                throw new RuntimeException('Konaklama giriş tarihi kilidi bırakılamadı.');
            }
        }
    }

    /**
     * Mevcut bireysel hesap seçilmişse kişi kimliği stabil company_id'dir.
     * Yeni bireysel hesap veya firma akışında guest_name karşılaştırılır.
     */
    private function ensureNoActiveDuplicateCheckIn(
        Stay $candidate,
        ?int $exceptStayId = null,
        ?int $individualAccountId = null,
    ): void
    {
        $activeStays = Stay::query()
            ->select(['id', 'company_id', 'guest_name'])
            ->forceIndex('stays_check_in_date_check_out_date_index')
            ->where('check_in_date', $candidate->check_in_date->toDateString())
            ->where('status', StayStatus::Active)
            ->lockForUpdate()
            ->get();

        $candidateName = $this->normalizedGuestName($candidate->guest_name);

        foreach ($activeStays as $existing) {
            if ($existing->id === $exceptStayId) {
                continue;
            }

            $isDuplicate = $individualAccountId !== null
                ? $existing->company_id === $individualAccountId
                : $this->normalizedGuestName($existing->guest_name) === $candidateName;

            if ($isDuplicate) {
                throw ValidationException::withMessages([
                    'guest_name' => 'Bu kişi için seçilen giriş tarihinde zaten bir konaklama kaydı bulunuyor.',
                ]);
            }
        }
    }

    private function normalizedGuestName(string $name): string
    {
        return mb_strtolower(trim($name), 'UTF-8');
    }

    /**
     * Yalnızca modelde doldurulabilir alanları geçirir; nights ve status
     * kullanıcı girdisinden ASLA alınmaz.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function fillable(array $data): array
    {
        return array_intersect_key($data, array_flip([
            'company_id', 'guest_name', 'room_no', 'room_id',
            'check_in_date', 'check_out_date',
            'pricing_mode', 'daily_rate', 'amount', 'currency', 'notes',
        ]));
    }

    /**
     * Kur anlık görüntüsünü çözer (v2.1.1-C §2).
     *
     * Yalnızca YENİ kayıtta `exchange_rate_to_try` `$data` içinde bulunur
     * (StayRequest bunu düzenlemede hiç tanımlamaz) — düzenlemede bu metot
     * sessizce hiçbir şey yapmaz, mevcut kur DEĞİŞMEZ. TRY'ye geçiş her
     * koşulda ikisini de temizler (§6/L5 — TRY çevrim taşımaz).
     *
     * Finansal bir düzenleme (tutar/para birimi) MEVCUT kuru koruyarak
     * karşılığı yeniden hesaplar — bu, YENİ bir canlı kur DEĞİL, aynı
     * işlemin aynı anlık görüntüsünün tutarla tutarlı kalmasıdır.
     */
    private function resolveExchangeRate(Stay $stay, array $data): void
    {
        if ($stay->currency === Currency::TRY) {
            $stay->exchange_rate_to_try = null;
            $stay->try_equivalent = null;

            return;
        }

        $rate = array_key_exists('exchange_rate_to_try', $data) && $data['exchange_rate_to_try'] !== null
            ? Money::normalizeInput((string) $data['exchange_rate_to_try'])
            : $stay->exchange_rate_to_try;

        if ($rate === null) {
            return;
        }

        $stay->exchange_rate_to_try = $rate;
        $stay->try_equivalent = $stay->amountMoney()->multiplyByRate($rate)->toDecimal();
    }

    /**
     * Kullanıcı girdisini modele uygular ve SUNUCU tarafından belirlenen
     * alanları (gece sayısı, nihai tutar) yeniden hesaplar.
     *
     * `nights` ve `status` kullanıcıdan hiçbir koşulda alınmaz; günlük
     * ücretlendirmede `amount` da alınmaz — istek gövdesine sahte bir tutar
     * yazılsa bile burada gecelik ücret × gece sayısı ile ezilir.
     *
     * @param  array<string, mixed>  $data
     */
    private function applyInput(Stay $stay, array $data): void
    {
        $stay->fill($this->fillable($data));
        $stay->nights = $this->nightsFor($stay);
        $this->resolvePricing($stay);
        $this->resolveExchangeRate($stay, $data);
    }

    /** Ücretlendirme moduna göre nihai tutarı ve günlük ücreti belirler. */
    private function resolvePricing(Stay $stay): void
    {
        $mode = $stay->pricing_mode ?? StayPricingMode::Total;
        $stay->pricing_mode = $mode;

        if ($mode === StayPricingMode::Total) {
            $stay->daily_rate = null;

            return;
        }

        $dailyRate = $stay->dailyRateMoney();

        if ($dailyRate === null) {
            throw new InvalidArgumentException('Günlük ücretlendirmede gecelik tutar zorunludur.');
        }

        $stay->amount = Stay::calculateDailyAmount($dailyRate, $stay->nights)->toDecimal();
    }

    private function nightsFor(Stay $stay): int
    {
        return Stay::calculateNights(
            Carbon::parse($stay->check_in_date),
            Carbon::parse($stay->check_out_date),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Stay $stay): array
    {
        return [
            'company_id' => (int) $stay->company_id,
            'guest_name' => $stay->guest_name,
            'room_no' => $stay->room_no,
            'room_id' => $stay->room_id === null ? null : (int) $stay->room_id,
            'check_in_date' => Carbon::parse($stay->check_in_date)->toDateString(),
            'check_out_date' => Carbon::parse($stay->check_out_date)->toDateString(),
            'nights' => (int) $stay->nights,
            'pricing_mode' => $stay->pricing_mode->value,
            'daily_rate' => $stay->daily_rate === null ? null : Money::fromDecimal($stay->daily_rate)->toDecimal(),
            'amount' => Money::fromDecimal($stay->amount)->toDecimal(),
            'currency' => $stay->currency->value,
            'exchange_rate_to_try' => $stay->exchange_rate_to_try,
            'try_equivalent' => $stay->try_equivalent === null ? null : Money::fromDecimal($stay->try_equivalent)->toDecimal(),
            'notes' => $stay->notes,
            'status' => $stay->status->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function financialSnapshot(Stay $stay): array
    {
        return array_intersect_key($this->snapshot($stay), array_flip(self::FINANCIAL_FIELDS));
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, mixed>
     */
    private function changedFields(array $before, array $after): array
    {
        $changed = [];

        foreach ($after as $key => $value) {
            if (($before[$key] ?? null) !== $value) {
                $changed[$key] = $value;
            }
        }

        return $changed;
    }

    private function resolveActor(?User $actor): User
    {
        $user = $actor ?? (Auth::check() ? Auth::user() : null);

        if ($user === null) {
            throw new RuntimeException('Konaklama işlemi için işlemi yapan kullanıcı belirtilmelidir.');
        }

        return $user;
    }

    /**
     * Bir tarih aralığında cariye YAZILMIŞ konaklama tutarları, para birimi bazında.
     *
     * Aralık `check_out_date` üzerinden uygulanır: cari hareketin `entry_date`
     * değeri budur (CLAUDE.md §8). "Bu ayki konaklama tutarı" bu yüzden aynı ay
     * içinde deftere düşen tutarla birebir aynıdır.
     *
     * İptal edilmiş konaklamalar sayılmaz — cari hareketleri ters kayıtla
     * sıfırlanmıştır. TEK sorgu; para birimi sayısı kadar satır döner.
     *
     * @return array<string, Money>
     */
    public function totalsByCurrencyBetween(string $from, string $to): array
    {
        $rows = DB::table('stays')
            ->selectRaw('currency')
            ->selectRaw('CAST(COALESCE(SUM(amount), 0) AS CHAR) as total')
            ->whereNull('deleted_at')
            ->where('status', StayStatus::Active->value)
            ->whereBetween('check_out_date', [$from, $to])
            ->groupBy('currency')
            ->orderBy('currency')
            ->get();

        $totals = [];

        foreach ($rows as $row) {
            $totals[(string) $row->currency] = Money::fromDecimal((string) $row->total);
        }

        return $totals;
    }

    /**
     * Bir tarih aralığında cariye yazılmış konaklamaların tutarı, ADEDİ ve
     * GECE sayısı — para birimi bazında (Finans panosu KPI kartları).
     *
     * `totalsByCurrencyBetween()` ile AYNI evren (aktif, silinmemiş,
     * `check_out_date` aralıkta); tek fark adet/gece de döndürmesidir.
     * TEK sorgu.
     *
     * @return array<string, array{total: Money, count: int, nights: int}>
     */
    public function summaryByCurrencyBetween(string $from, string $to): array
    {
        return $this->summaryByCurrency(
            DB::table('stays')->whereBetween('check_out_date', [$from, $to]),
        );
    }

    /**
     * Tüm zamanların aktif konaklama özeti — para birimi bazında.
     * TEK sorgu.
     *
     * @return array<string, array{total: Money, count: int, nights: int}>
     */
    public function lifetimeSummaryByCurrency(): array
    {
        return $this->summaryByCurrency(DB::table('stays'));
    }

    /**
     * Tek para biriminde aylık konaklama tutarları (`Y-m` anahtarlı).
     *
     * Ay, cari hareketin `entry_date`'i olan `check_out_date`'ten alınır.
     * Konaklamasız aylar dizide YOKTUR; çağıran taraf sıfırla doldurur. TEK sorgu.
     *
     * @return array<string, Money>
     */
    public function monthlyTotals(Currency $currency, string $from, string $to): array
    {
        $rows = DB::table('stays')
            ->selectRaw("DATE_FORMAT(check_out_date, '%Y-%m') as ym")
            ->selectRaw('CAST(COALESCE(SUM(amount), 0) AS CHAR) as total')
            ->whereNull('deleted_at')
            ->where('status', StayStatus::Active->value)
            ->where('currency', $currency->value)
            ->whereBetween('check_out_date', [$from, $to])
            ->groupBy('ym')
            ->get();

        $totals = [];

        foreach ($rows as $row) {
            $totals[(string) $row->ym] = Money::fromDecimal((string) $row->total);
        }

        return $totals;
    }

    /**
     * @return array<string, array{total: Money, count: int, nights: int}>
     */
    private function summaryByCurrency(QueryBuilder $query): array
    {
        $rows = $query
            ->selectRaw('currency')
            ->selectRaw('CAST(COALESCE(SUM(amount), 0) AS CHAR) as total')
            ->selectRaw('COUNT(*) as stay_count')
            ->selectRaw('COALESCE(SUM(nights), 0) as night_count')
            ->whereNull('deleted_at')
            ->where('status', StayStatus::Active->value)
            ->groupBy('currency')
            ->orderBy('currency')
            ->get();

        $summary = [];

        foreach ($rows as $row) {
            $summary[(string) $row->currency] = [
                'total' => Money::fromDecimal((string) $row->total),
                'count' => (int) $row->stay_count,
                'nights' => (int) $row->night_count,
            ];
        }

        return $summary;
    }

    /**
     * Bugün devam eden ve yaklaşan konaklamalar.
     *
     * "Devam eden" = giriş yapılmış, çıkış tarihi henüz gelmemiş.
     * Sıralama çıkış tarihine göredir: ekrandaki soru "kim ne zaman çıkıyor".
     *
     * @return Collection<int, Stay>
     */
    public function currentAndUpcoming(string $today, int $limit = 8): Collection
    {
        return Stay::query()
            ->where('status', StayStatus::Active->value)
            ->whereDate('check_out_date', '>=', $today)
            ->orderBy('check_out_date')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Bir kullanıcının belirli tarihten sonra girdiği konaklama sayısı.
     *
     * Personel panosu için: tutar değil, yalnızca ADET (CLAUDE.md §7.4).
     */
    public function countCreatedBySince(int $userId, string $since): int
    {
        return Stay::query()
            ->where('created_by', $userId)
            ->where('created_at', '>=', $since)
            ->count();
    }

    /**
     * Bir kullanıcının son kayıtları.
     *
     * @return Collection<int, Stay>
     */
    public function recentCreatedBy(int $userId, int $limit = 5): Collection
    {
        return Stay::query()
            ->where('created_by', $userId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }
}
