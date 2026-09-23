<?php

declare(strict_types=1);

namespace App\Modules\Payments\Services;

use App\Models\User;
use App\Modules\AuditLog\Enums\AuditEvent;
use App\Modules\AuditLog\Services\AuditLogger;
use App\Modules\Companies\Services\CompanyService;
use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
use App\Modules\Ledger\Models\LedgerEntry;
use App\Modules\Ledger\Services\LedgerService;
use App\Modules\Payments\Enums\AllocationMode;
use App\Modules\Payments\Enums\PayerType;
use App\Modules\Payments\Enums\PaymentMethod;
use App\Modules\Payments\Enums\PaymentStatus;
use App\Modules\Payments\Exceptions\CannotUpdateCancelledPayment;
use App\Modules\Payments\Exceptions\ExchangeRateAlreadyRecorded;
use App\Modules\Payments\Exceptions\PaymentAlreadyCancelled;
use App\Modules\Payments\Exceptions\PaymentFinancialFieldsAreImmutable;
use App\Modules\Payments\Models\Payment;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Tahsilat modülünün dışa açık tek kapısı (CLAUDE.md §4.3).
 *
 * Her yazma işlemi tahsilat kaydı ile cari hareketini AYNI transaction içinde
 * ele alır (CLAUDE.md §6/L6).
 *
 * Değişmez: aktif bir tahsilatın **tam olarak bir geçerli alacak hareketi**
 * vardır; iptal edilmiş tahsilatın hiç yoktur.
 *
 * Kasa modülü kaldırılmıştır; tahsilat ve iptal yalnızca cari/dağılım yazar.
 */
class PaymentService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Tahsilat oluşturur ve firmanın carisine alacak hareketi düşer.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?User $actor = null): Payment
    {
        $user = $this->resolveActor($actor);

        return DB::transaction(function () use ($data, $user): Payment {
            app(CompanyService::class)->lockAccounts([(int) $data['company_id']]);
            $payment = new Payment;
            $payment->fill($this->fillableOnCreate($data));
            $payment->payer_type = PayerType::from($data['payer_type'] ?? PayerType::AccountHolder->value);
            $payment->payer_name = $payment->payer_type === PayerType::Individual
                ? trim((string) ($data['payer_name'] ?? ''))
                : (app(CompanyService::class)->namesByIds([(int) $data['company_id']])[(int) $data['company_id']] ?? '');
            if ($payment->payer_type === PayerType::Individual && ($payment->payer_name === '' || mb_strlen($payment->payer_name) > 200)) {
                throw ValidationException::withMessages(['payer_name' => 'Ödemeyi yapan kişinin adını girin (en fazla 200 karakter).']);
            }
            $payment->allocation_mode = AllocationMode::from($data['allocation_mode'] ?? AllocationMode::OldestFirst->value);
            $payment->status = PaymentStatus::Active;
            $payment->created_by = $user->getKey();
            $payment->updated_by = $user->getKey();
            $this->applyExchangeRate($payment, $data);
            // Tarihsel vardiya bağlantısı yeni tahsilatlara atanmaz.
            $payment->cash_session_id = null;

            $payment->save();

            // Makbuz numarası `id`ye bağımlıdır (v2.1.1-B §13), yalnızca
            // INSERT'ten SONRA üretilebilir. `saveQuietly()`: bu, dışarıdan
            // bir "düzenleme" DEĞİL, create() akışının kendi TAMAMLANMASIDIR
            // — `updating` olay koruması (IMMUTABLE_FIELDS) burada bilerek
            // atlanır; aksi hâlde receipt_no'nun kendi ilk atanışı bile
            // "değişmez alan değiştirildi" istisnasına çarpardı.
            $payment->receipt_no = Payment::receiptNumberFor($payment->getKey(), $payment->payment_date);
            $payment->saveQuietly();

            $entry = $this->postCredit($payment, $user);

            app(PaymentAllocationService::class)->allocate($payment, $data['stay_ids'] ?? [], $user);

            $this->auditLogger->log(
                event: AuditEvent::Created,
                subject: $payment,
                newValues: $this->snapshot($payment) + [
                    'ledger_entry_id' => $entry->id,
                ],
                user: $user,
            );

            return $payment;
        });
    }

    /**
     * Yalnızca metadata düzeltmesi: `reference_no` ve `description`.
     *
     * Finansal alanlar hiçbir koşulda değişmez; istekte gönderilseler bile
     * burada süzülürler. Model katmanı da aynı kuralı ayrıca zorlar.
     * Metadata düzeltmesi cari hareketi ÜRETMEZ.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws CannotUpdateCancelledPayment
     * @throws PaymentFinancialFieldsAreImmutable
     */
    public function updateMetadata(Payment $payment, array $data, ?User $actor = null): Payment
    {
        if ($payment->isCancelled()) {
            throw CannotUpdateCancelledPayment::make($payment->getKey());
        }

        $this->guardImmutableFields($payment, $data);

        $user = $this->resolveActor($actor);

        return DB::transaction(function () use ($payment, $data, $user): Payment {
            $before = $this->metadataSnapshot($payment);

            $payment->fill(array_intersect_key($data, array_flip(Payment::EDITABLE_FIELDS)));

            $after = $this->metadataSnapshot($payment);
            $changed = array_diff_assoc($after, $before);

            if ($changed === []) {
                return $payment;
            }

            $payment->updated_by = $user->getKey();
            $payment->save();

            $this->auditLogger->log(
                event: AuditEvent::Updated,
                subject: $payment,
                oldValues: array_intersect_key($before, $changed),
                newValues: $changed,
                user: $user,
            );

            return $payment;
        });
    }

    /**
     * Tahsilatı iptal eder ve cari hareketini ters kaydeder.
     *
     * İptal geri alınamaz (V1'de restore akışı yoktur).
     *
     * @throws PaymentAlreadyCancelled
     */
    public function cancel(Payment $payment, string $reason, ?User $actor = null): Payment
    {
        if ($payment->isCancelled()) {
            throw PaymentAlreadyCancelled::make($payment->getKey());
        }

        $user = $this->resolveActor($actor);

        return DB::transaction(function () use ($payment, $reason, $user): Payment {
            app(CompanyService::class)->lockAccounts([$payment->company_id]);
            $fresh = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $payment->setRawAttributes($fresh->getAttributes(), true);
            if ($payment->isCancelled()) {
                throw PaymentAlreadyCancelled::make($payment->id);
            }
            $active = $this->ledger->activeEntryForSource(Payment::LEDGER_SOURCE_TYPE, $payment->getKey());
            $reversal = $active !== null ? $this->ledger->reverse($active, actor: $user) : null;

            $payment->status = PaymentStatus::Cancelled;
            $payment->cancelled_at = Carbon::now();
            $payment->cancelled_by = $user->getKey();
            $payment->cancellation_reason = $reason;
            $payment->updated_by = $user->getKey();
            $payment->save();

            app(PaymentAllocationService::class)->releasePayment($payment->id, $user);

            $this->auditLogger->log(
                event: AuditEvent::Cancelled,
                subject: $payment,
                oldValues: ['status' => PaymentStatus::Active->value],
                newValues: [
                    'status' => PaymentStatus::Cancelled->value,
                    'amount' => Money::fromDecimal($payment->amount)->toDecimal(),
                    'currency' => $payment->currency->value,
                    'cancellation_reason' => $reason,
                    'cancelled_by' => $user->getKey(),
                    'cancelled_at' => $payment->cancelled_at->toDateTimeString(),
                    'reversed_ledger_entry_id' => $active?->id,
                    'reversal_ledger_entry_id' => $reversal?->id,
                ],
                user: $user,
            );

            return $payment;
        });
    }

    /**
     * Firmanın son tahsilatları — firma detay ekranı için.
     *
     * @return Collection<int, Payment>
     */
    public function recentForCompany(int $companyId, int $limit = 10): Collection
    {
        return Payment::query()
            ->where('company_id', $companyId)
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function countForCompany(int $companyId): int
    {
        return Payment::query()->where('company_id', $companyId)->count();
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

        return Payment::query()->whereIn('id', $ids)->pluck('id')->all();
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

        return Payment::query()
            ->with(['creator:id,name', 'updater:id,name'])
            ->whereIn('id', $ids)
            ->get(['id', 'created_by', 'updated_by', 'created_at', 'updated_at'])
            ->mapWithKeys(function (Payment $payment): array {
                $edited = $payment->updated_at->gt($payment->created_at);
                $actor = $edited ? $payment->updater : $payment->creator;
                $at = $edited ? $payment->updated_at : $payment->created_at;

                return [$payment->getKey() => [
                    'edited' => $edited,
                    'user' => $actor->name,
                    'at' => $at->format('d.m.Y H:i'),
                ]];
            })
            ->all();
    }

    /**
     * `id` ile tek tahsilat — FxCorrection modülünün tek bilmesi GEREKEN
     * erişim yoludur (§4.3/1: o modül `Payment::class`i hiç KULLANMAZ).
     */
    public function findOrFail(int $id): Payment
    {
        return Payment::query()->findOrFail($id);
    }

    /**
     * Verilen tahsilat id'leri için kur anlık görüntüsü — TEK sorguda.
     *
     * `StayService::exchangeRateSnapshotsFor()` ile AYNI gerekçe: durum
     * filtrelenmez, orijinal + ters kayıt aynı anlık görüntüyü paylaşır ve
     * zaten birbirini götürür.
     *
     * @param  array<int, int>  $ids
     * @return array<int, array{rate: ?string, equivalent: ?string}>
     */
    public function exchangeRateSnapshotsFor(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return Payment::query()
            ->whereIn('id', $ids)
            ->get(['id', 'exchange_rate_to_try', 'try_equivalent'])
            ->mapWithKeys(fn (Payment $payment): array => [
                $payment->getKey() => ['rate' => $payment->exchange_rate_to_try, 'equivalent' => $payment->try_equivalent],
            ])
            ->all();
    }

    /**
     * TÜM zamanlar TL karşılığı toplamı (yalnızca AKTİF tahsilatlar),
     * verilen firmalar için, para birimi bazında — TEK sorgu.
     *
     * `StayService::exchangeRateDebitTotalsForCompanies()` ile AYNI gerekçe
     * (yön ALACAK'tır): iptal edilmiş bir tahsilatın orijinal alacağı ile
     * ters kaydı zaten birbirini götürür.
     *
     * @param  array<int, int>  $companyIds
     * @return array<int, array<string, array{total: Money, complete: bool}>>
     */
    public function exchangeRateCreditTotalsForCompanies(array $companyIds): array
    {
        if ($companyIds === []) {
            return [];
        }

        return $this->mapExchangeRateTotals(
            $this->exchangeRateCreditQuery()->whereIn('company_id', $companyIds)->get()
        );
    }

    /**
     * Aynı toplam, TÜM firmalar için — Dashboard'ın sistem çapındaki
     * "açık borç" TL karşılığı içindir (v2.1.1-D §3).
     *
     * @return array<int, array<string, array{total: Money, complete: bool}>>
     */
    public function systemWideExchangeRateCreditTotals(): array
    {
        return $this->mapExchangeRateTotals($this->exchangeRateCreditQuery()->get());
    }

    /**
     * Aynı toplam, firma ayrımı OLMADAN, yalnızca para birimi bazında —
     * `StayService::systemWideExchangeRateDebitTotalsByCurrency()` ile AYNI
     * gerekçe (v2.1.1-D §3, Dashboard "Açık alacak" kartı).
     *
     * @return array<string, array{total: Money, complete: bool}>
     */
    public function systemWideExchangeRateCreditTotalsByCurrency(): array
    {
        $rows = DB::table('payments')
            ->select('currency')
            ->selectRaw('CAST(COALESCE(SUM(try_equivalent), 0) AS CHAR) as total')
            ->selectRaw('SUM(CASE WHEN try_equivalent IS NULL THEN 1 ELSE 0 END) as missing')
            ->where('status', PaymentStatus::Active->value)
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

    private function exchangeRateCreditQuery(): QueryBuilder
    {
        return DB::table('payments')
            ->select('company_id', 'currency')
            ->selectRaw('CAST(COALESCE(SUM(try_equivalent), 0) AS CHAR) as total')
            ->selectRaw('SUM(CASE WHEN try_equivalent IS NULL THEN 1 ELSE 0 END) as missing')
            ->where('status', PaymentStatus::Active->value)
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
     * Kur bilgisi EKSİK yabancı para birimli tahsilatlar — Geçmiş Döviz
     * Düzeltme kuyruğu (v2.1.1-C §3). İptal edilmiş kayıtlar da gösterilir.
     *
     * @return Collection<int, Payment>
     */
    public function missingExchangeRate(): Collection
    {
        return Payment::query()
            ->with('company')
            ->where('currency', '<>', Currency::TRY->value)
            ->whereNull('exchange_rate_to_try')
            ->orderBy('payment_date')
            ->orderBy('id')
            ->get();
    }

    /**
     * Geçmiş bir tahsilatın EKSİK kur anlık görüntüsünü doldurur.
     *
     * v2.1.1-C §3'ün katı kuralları: `Payment::IMMUTABLE_FIELDS` (tutar,
     * para birimi) hiç değişmez, ikinci bir tahsilat/cari hareketi oluşmaz,
     * iptal/yeniden işleme YAPILMAZ — yalnızca eksik metadata yazılır. Bu
     * yüzden normal `updateMetadata()` akışından TAMAMEN AYRIDIR.
     *
     * Zaten kayıtlı bir kuru "düzeltmek" için bu metot KULLANILAMAZ (§3:
     * "do not allow casual editing of completed historical FX values").
     *
     * @throws ExchangeRateAlreadyRecorded
     */
    public function correctHistoricalExchangeRate(Payment $payment, string $rate, ?User $actor = null): Payment
    {
        if ($payment->exchange_rate_to_try !== null) {
            throw ExchangeRateAlreadyRecorded::make($payment->getKey());
        }

        if ($payment->currency === Currency::TRY) {
            throw new \InvalidArgumentException('TRY tahsilatlar için kur düzeltmesi yapılamaz.');
        }

        $user = $this->resolveActor($actor);
        $normalizedRate = Money::normalizeInput($rate);
        $tryEquivalent = $payment->amountMoney()->multiplyByRate($normalizedRate)->toDecimal();

        return DB::transaction(function () use ($payment, $normalizedRate, $tryEquivalent, $user): Payment {
            $payment->exchange_rate_to_try = $normalizedRate;
            $payment->try_equivalent = $tryEquivalent;
            $payment->updated_by = $user->getKey();
            $payment->save();

            $this->auditLogger->log(
                event: AuditEvent::ExchangeRateCorrected,
                subject: $payment,
                oldValues: ['exchange_rate_to_try' => null, 'try_equivalent' => null],
                newValues: ['exchange_rate_to_try' => $normalizedRate, 'try_equivalent' => $tryEquivalent],
                user: $user,
            );

            return $payment;
        });
    }

    // ------------------------------------------------------------------ iç

    /**
     * Kur anlık görüntüsünü çözer (v2.1.1-C §2) — yalnızca OLUŞTURMADA
     * çağrılır. TRY için her zaman NULL; yabancı para biriminde
     * `StorePaymentRequest` kuru ZORUNLU kıldığı için `$data` içinde vardır.
     */
    private function applyExchangeRate(Payment $payment, array $data): void
    {
        if ($payment->currency === Currency::TRY) {
            $payment->exchange_rate_to_try = null;
            $payment->try_equivalent = null;

            return;
        }

        if (! array_key_exists('exchange_rate_to_try', $data) || $data['exchange_rate_to_try'] === null) {
            return;
        }

        $rate = Money::normalizeInput((string) $data['exchange_rate_to_try']);
        $payment->exchange_rate_to_try = $rate;
        $payment->try_equivalent = $payment->amountMoney()->multiplyByRate($rate)->toDecimal();
    }

    private function postCredit(Payment $payment, User $user): LedgerEntry
    {
        return $this->ledger->credit(
            companyId: $payment->company_id,
            amount: $payment->amountMoney(),
            currency: $payment->currency,
            entryDate: $payment->payment_date,
            description: $payment->ledgerDescription(),
            sourceType: Payment::LEDGER_SOURCE_TYPE,
            sourceId: $payment->getKey(),
            actor: $user,
        );
    }

    /**
     * Oluşturmada alınabilecek alanlar. `status` ve iptal alanları kullanıcı
     * girdisinden ASLA alınmaz.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function fillableOnCreate(array $data): array
    {
        return array_intersect_key($data, array_flip([
            ...Payment::IMMUTABLE_FIELDS,
            ...Payment::EDITABLE_FIELDS,
        ]));
    }

    /**
     * Güncelleme isteğinde finansal alan gönderilmişse ve GERÇEKTEN farklıysa
     * işlem reddedilir. Aynı değerin tekrar gönderilmesi (form gizli alanı)
     * sessizce yok sayılır.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws PaymentFinancialFieldsAreImmutable
     */
    private function guardImmutableFields(Payment $payment, array $data): void
    {
        $attempted = [];

        foreach (Payment::IMMUTABLE_FIELDS as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            if ($this->normalize($field, $data[$field]) !== $this->normalize($field, $payment->{$field})) {
                $attempted[] = $field;
            }
        }

        if ($attempted !== []) {
            throw PaymentFinancialFieldsAreImmutable::make($attempted);
        }
    }

    /**
     * Gelen değeri, modeldeki tipli değerle karşılaştırılabilir hâle getirir.
     *
     * Girdi tarayıcıdan metin olarak gelebilir ve Türkçe biçimli olabilir
     * ('5.000,00'). Çözümlenemeyen bir değer, karşılaştırmayı bilerek
     * BAŞARISIZ kılan bir işaret döndürür: belirsiz girdi "değişiklik yok"
     * sayılmaz, reddedilir.
     */
    private function normalize(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        try {
            return match ($field) {
                'company_id' => (string) (int) (is_scalar($value) ? $value : throw new \InvalidArgumentException),
                'payment_date' => Carbon::parse(
                    is_string($value) || $value instanceof \DateTimeInterface ? $value : throw new \InvalidArgumentException
                )->toDateString(),
                'amount' => Money::fromDecimal(
                    Money::normalizeInput(is_string($value) || is_int($value) ? (string) $value : throw new \InvalidArgumentException)
                )->toDecimal(),
                default => (string) (is_scalar($value) ? $value : throw new \InvalidArgumentException),
            };
        } catch (\Throwable) {
            return '__cozumlenemedi__'.spl_object_id(new \stdClass);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Payment $payment): array
    {
        return [
            'company_id' => (int) $payment->company_id,
            'payer_type' => $payment->payer_type?->value,
            'payer_name' => $payment->payer_name,
            'allocation_mode' => $payment->allocation_mode?->value,
            'exchange_rate_to_try' => $payment->exchange_rate_to_try,
            'try_equivalent' => $payment->try_equivalent,
            'payment_date' => Carbon::parse($payment->payment_date)->toDateString(),
            'amount' => Money::fromDecimal($payment->amount)->toDecimal(),
            'currency' => $payment->currency->value,
            'payment_method' => $payment->payment_method?->value,
            'cash_session_id' => $payment->cash_session_id,
            'receipt_no' => $payment->receipt_no,
            'reference_no' => $payment->reference_no,
            'description' => $payment->description,
            'status' => $payment->status->value,
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function metadataSnapshot(Payment $payment): array
    {
        return [
            'reference_no' => $payment->reference_no,
            'description' => $payment->description,
        ];
    }

    private function resolveActor(?User $actor): User
    {
        $user = $actor ?? (Auth::check() ? Auth::user() : null);

        if ($user === null) {
            throw new \RuntimeException('Tahsilat işlemi için işlemi yapan kullanıcı belirtilmelidir.');
        }

        return $user;
    }

    /**
     * Bir tarih aralığında cariye YAZILMIŞ tahsilat tutarları, para birimi bazında.
     *
     * Aralık `payment_date` üzerinden uygulanır: cari hareketin `entry_date`
     * değeri budur (CLAUDE.md §8). İptal edilmiş tahsilatlar sayılmaz.
     *
     * TEK sorgu; para birimi sayısı kadar satır döner.
     *
     * @return array<string, Money>
     */
    public function totalsByCurrencyBetween(string $from, string $to): array
    {
        $rows = DB::table('payments')
            ->selectRaw('currency')
            ->selectRaw('CAST(COALESCE(SUM(amount), 0) AS CHAR) as total')
            ->where('status', PaymentStatus::Active->value)
            ->whereBetween('payment_date', [$from, $to])
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
     * Bir tarih aralığında aktif tahsilatların tutarı ve ADEDİ — para birimi
     * bazında (Finans panosu KPI kartları). `totalsByCurrencyBetween()` ile
     * AYNI evren. TEK sorgu.
     *
     * @return array<string, array{total: Money, count: int}>
     */
    public function summaryByCurrencyBetween(string $from, string $to): array
    {
        return $this->summaryByCurrency(
            DB::table('payments')->whereBetween('payment_date', [$from, $to]),
        );
    }

    /**
     * Tüm zamanların aktif tahsilat özeti — para birimi bazında. TEK sorgu.
     *
     * @return array<string, array{total: Money, count: int}>
     */
    public function lifetimeSummaryByCurrency(): array
    {
        return $this->summaryByCurrency(DB::table('payments'));
    }

    /**
     * Tek para biriminde aylık tahsilat tutarları (`Y-m` anahtarlı).
     * Tahsilatsız aylar dizide YOKTUR. TEK sorgu.
     *
     * @return array<string, Money>
     */
    public function monthlyTotals(Currency $currency, string $from, string $to): array
    {
        $rows = DB::table('payments')
            ->selectRaw("DATE_FORMAT(payment_date, '%Y-%m') as ym")
            ->selectRaw('CAST(COALESCE(SUM(amount), 0) AS CHAR) as total')
            ->where('status', PaymentStatus::Active->value)
            ->where('currency', $currency->value)
            ->whereBetween('payment_date', [$from, $to])
            ->groupBy('ym')
            ->get();

        $totals = [];

        foreach ($rows as $row) {
            $totals[(string) $row->ym] = Money::fromDecimal((string) $row->total);
        }

        return $totals;
    }

    /**
     * @return array<string, array{total: Money, count: int}>
     */
    private function summaryByCurrency(QueryBuilder $query): array
    {
        $rows = $query
            ->selectRaw('currency')
            ->selectRaw('CAST(COALESCE(SUM(amount), 0) AS CHAR) as total')
            ->selectRaw('COUNT(*) as payment_count')
            ->where('status', PaymentStatus::Active->value)
            ->groupBy('currency')
            ->orderBy('currency')
            ->get();

        $summary = [];

        foreach ($rows as $row) {
            $summary[(string) $row->currency] = [
                'total' => Money::fromDecimal((string) $row->total),
                'count' => (int) $row->payment_count,
            ];
        }

        return $summary;
    }

    /**
     * Bir tarih aralığında TEK bir kanaldan (ör. kart) yazılan tahsilat
     * tutarları, para birimi bazında (v2.1.1-A §12 — dönem raporlanabilirliği).
     *
     * `totalsByCurrencyBetween()`'den farkı: o TÜM kanalların toplamıdır
     * (Finans panosu için), bu METOD belirli bir kanalı izole eder. İptal
     * edilmiş tahsilatlar sayılmaz.
     *
     * @return array<string, Money>
     */
    public function totalsByMethodAndCurrencyBetween(PaymentMethod $method, string $from, string $to): array
    {
        $rows = DB::table('payments')
            ->selectRaw('currency')
            ->selectRaw('CAST(COALESCE(SUM(amount), 0) AS CHAR) as total')
            ->where('status', PaymentStatus::Active->value)
            ->where('payment_method', $method->value)
            ->whereBetween('payment_date', [$from, $to])
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
     * Son tahsilatlar (iptaller dahil — pano gerçeği gizlemez).
     *
     * @return Collection<int, Payment>
     */
    public function recent(int $limit = 10): Collection
    {
        return Payment::query()
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Bir kullanıcının belirli tarihten sonra girdiği tahsilat sayısı.
     *
     * Personel panosu için: tutar değil, yalnızca ADET (CLAUDE.md §7.4).
     */
    public function countCreatedBySince(int $userId, string $since): int
    {
        return Payment::query()
            ->where('created_by', $userId)
            ->where('created_at', '>=', $since)
            ->count();
    }

    /**
     * Bir kullanıcının son kayıtları.
     *
     * @return Collection<int, Payment>
     */
    public function recentCreatedBy(int $userId, int $limit = 5): Collection
    {
        return Payment::query()
            ->where('created_by', $userId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }
}
