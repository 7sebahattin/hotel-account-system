<?php

declare(strict_types=1);

namespace App\Modules\Stays\Services;

use App\Models\User;
use App\Modules\AuditLog\Enums\AuditEvent;
use App\Modules\AuditLog\Services\AuditLogger;
use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
use App\Modules\Ledger\Models\LedgerEntry;
use App\Modules\Ledger\Services\LedgerService;
use App\Modules\Stays\Enums\StayPricingMode;
use App\Modules\Stays\Enums\StayStatus;
use App\Modules\Stays\Exceptions\CannotUpdateCancelledStay;
use App\Modules\Stays\Exceptions\ExchangeRateAlreadyRecorded;
use App\Modules\Stays\Exceptions\StayAlreadyCancelled;
use App\Modules\Stays\Models\Stay;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

        return DB::transaction(function () use ($data, $user): Stay {
            $stay = new Stay;
            $this->applyInput($stay, $data);
            $stay->status = StayStatus::Active;
            $stay->created_by = $user->getKey();
            $stay->updated_by = $user->getKey();
            $stay->save();

            $entry = $this->postDebit($stay, $user);

            $this->auditLogger->log(
                event: AuditEvent::Created,
                subject: $stay,
                newValues: $this->snapshot($stay) + ['ledger_entry_id' => $entry->id],
                user: $user,
            );

            return $stay;
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

        return DB::transaction(function () use ($stay, $data, $user): Stay {
            $before = $this->snapshot($stay);
            $financialBefore = $this->financialSnapshot($stay);

            // Aday kayıt, gerçek kayıtla AYNI hesaplamadan geçer: gece sayısı ve
            // günlük ücretlendirmede toplam yeniden hesaplandıktan sonra
            // karşılaştırılır. Aksi hâlde formdan gelen ham tutar karşılaştırılırdı.
            $candidate = clone $stay;
            $this->applyInput($candidate, $data);
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
            $active = $this->ledger->activeEntryForSource(Stay::LEDGER_SOURCE_TYPE, $stay->getKey());
            $reversal = $active !== null ? $this->ledger->reverse($active, actor: $user) : null;

            $stay->status = StayStatus::Cancelled;
            $stay->updated_by = $user->getKey();
            $stay->save();

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
            throw new \InvalidArgumentException('TRY konaklamalar için kur düzeltmesi yapılamaz.');
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
            throw new \InvalidArgumentException('Günlük ücretlendirmede gecelik tutar zorunludur.');
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
            throw new \RuntimeException('Konaklama işlemi için işlemi yapan kullanıcı belirtilmelidir.');
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
