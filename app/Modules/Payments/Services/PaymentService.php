<?php

declare(strict_types=1);

namespace App\Modules\Payments\Services;

use App\Models\User;
use App\Modules\AuditLog\Enums\AuditEvent;
use App\Modules\AuditLog\Services\AuditLogger;
use App\Modules\CashBook\Enums\CashMovementType;
use App\Modules\CashBook\Exceptions\NoOpenCashSession;
use App\Modules\CashBook\Models\CashMovement;
use App\Modules\CashBook\Models\CashSession;
use App\Modules\CashBook\Services\CashBookService;
use App\Modules\Core\Support\Money;
use App\Modules\Ledger\Models\LedgerEntry;
use App\Modules\Ledger\Services\LedgerService;
use App\Modules\Payments\Enums\PaymentMethod;
use App\Modules\Payments\Enums\PaymentStatus;
use App\Modules\Payments\Exceptions\CannotUpdateCancelledPayment;
use App\Modules\Payments\Exceptions\CashSessionNotOwnedByActor;
use App\Modules\Payments\Exceptions\PaymentAlreadyCancelled;
use App\Modules\Payments\Exceptions\PaymentFinancialFieldsAreImmutable;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Support\ShiftCollectionSummary;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Tahsilat modülünün dışa açık tek kapısı (CLAUDE.md §4.3).
 *
 * Her yazma işlemi tahsilat kaydı ile cari hareketini AYNI transaction içinde
 * ele alır (CLAUDE.md §6/L6).
 *
 * Değişmez: aktif bir tahsilatın **tam olarak bir geçerli alacak hareketi**
 * vardır; iptal edilmiş tahsilatın hiç yoktur.
 *
 * v2.1.1-A: `payment_method = cash` olan tahsilatlar AYRICA fiziksel kasaya
 * (`CashBookService`) bağlı bir `company_collection` hareketi üretir — aynı
 * transaction içinde, `Payment::LEDGER_SOURCE_TYPE` ile izlenebilir biçimde.
 * Kart/banka/çek kanalları kasaya HİÇ dokunmaz. Bu, Payments'ın CashBook'a
 * TEK YÖNLÜ bağımlılığıdır (CLAUDE.md §4.3/1); `CashBookService` Payments'a
 * hiçbir zaman geri bağımlı olmaz.
 *
 * v2.1.1-A.1: `Payment::cash_session_id` hangi vardiyada oluşturulduğunu
 * AÇIKÇA, DEĞİŞMEZ olarak taşır — zaman penceresi çıkarımı YOKTUR. Aktör
 * o an açık bir vardiyayı yönetmiyorsa NULL kalır (tahmin edilmez).
 */
class PaymentService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly AuditLogger $auditLogger,
        private readonly CashBookService $cashBook,
    ) {}

    /**
     * Tahsilat oluşturur ve firmanın carisine alacak hareketi düşer.
     *
     * `cash_session_id` (v2.1.1-A.1) oluşturma anında AÇIKÇA belirlenir ve
     * bir daha ASLA değişmez: aktör o an açık BİR vardiyayı yönetiyorsa
     * (`opened_by === aktör`) o vardiyaya atanır, aksi hâlde NULL kalır.
     * Zaman penceresi/tahmin YOKTUR (§8/Payments).
     *
     * Nakit kanalda (`payment_method = cash`) bu vardiya ZORUNLUDUR — aynı
     * anda fiziksel kasaya bir `company_collection` hareketi de yazılır.
     * Kart/banka/çek/diğer kanallar vardiyasız da girilebilir (mevcut yetki
     * izin veriyorsa) ve kasaya asla dokunmaz.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws NoOpenCashSession
     * @throws CashSessionNotOwnedByActor
     */
    public function create(array $data, ?User $actor = null): Payment
    {
        $user = $this->resolveActor($actor);

        return DB::transaction(function () use ($data, $user): Payment {
            $payment = new Payment;
            $payment->fill($this->fillableOnCreate($data));
            $payment->status = PaymentStatus::Active;
            $payment->created_by = $user->getKey();
            $payment->updated_by = $user->getKey();

            $isCash = $payment->payment_method === PaymentMethod::Cash;
            $session = $isCash
                ? $this->requireOwnedOpenSession($user)
                : $this->resolveOwnedActiveSession($user);
            $payment->cash_session_id = $session?->getKey();

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

            $cashMovement = $isCash && $session !== null
                ? $this->postCashCollection($payment, $session, $user)
                : null;

            $this->auditLogger->log(
                event: AuditEvent::Created,
                subject: $payment,
                newValues: $this->snapshot($payment) + [
                    'ledger_entry_id' => $entry->id,
                    'cash_movement_id' => $cashMovement?->id,
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
     * Nakit kanalda, kasaya işlenmiş bağlı hareket de (varsa) ters kaydedilir
     * — GÜNCEL açık vardiyaya (orijinal vardiya artık kapanmış olsa bile,
     * CLAUDE.md §22/v2.1.1-A §13: "her iki etki de doğru şekilde ve TAM
     * OLARAK BİR KEZ sıfırlanır"). Kasa tarafı sıfırlanamıyorsa (açık vardiya
     * yok) TÜM iptal reddedilir — cari ile kasa ayrışıp yarım kalmaz.
     *
     * İptal geri alınamaz (V1'de restore akışı yoktur).
     *
     * @throws PaymentAlreadyCancelled
     * @throws NoOpenCashSession
     */
    public function cancel(Payment $payment, string $reason, ?User $actor = null): Payment
    {
        if ($payment->isCancelled()) {
            throw PaymentAlreadyCancelled::make($payment->getKey());
        }

        $user = $this->resolveActor($actor);

        return DB::transaction(function () use ($payment, $reason, $user): Payment {
            $active = $this->ledger->activeEntryForSource(Payment::LEDGER_SOURCE_TYPE, $payment->getKey());
            $reversal = $active !== null ? $this->ledger->reverse($active, actor: $user) : null;

            $cashReversal = $payment->payment_method === PaymentMethod::Cash
                ? $this->reverseCashCollection($payment, $reason, $user)
                : null;

            $payment->status = PaymentStatus::Cancelled;
            $payment->cancelled_at = Carbon::now();
            $payment->cancelled_by = $user->getKey();
            $payment->cancellation_reason = $reason;
            $payment->updated_by = $user->getKey();
            $payment->save();

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
                    'reversal_cash_movement_id' => $cashReversal?->id,
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

    // ------------------------------------------------------------------ iç

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
     * Nakit kanallı tahsilatın fiziksel kasa karşılığını yazar.
     *
     * `$session` çağıran tarafından ZATEN çözülmüş ve sahiplik doğrulanmıştır
     * (`create()`) — burada tekrar sorgulanmaz/doğrulanmaz.
     *
     * Açıklamaya makbuz numarası eklenir (§17 izlenebilirlik): Kasa Defteri
     * ekranları `CashMovement.reference_id`'yi (ham DB id) hiç göstermez,
     * yalnızca bu insan-okunur metni — `CashBookService` yine de bu metnin
     * ANLAMINI bilmez, opak bir açıklama string'i olarak saklar.
     */
    private function postCashCollection(Payment $payment, CashSession $session, User $user): CashMovement
    {
        $description = $payment->ledgerDescription();

        if ($payment->receipt_no !== null) {
            $description .= ' · '.$payment->receipt_no;
        }

        return $this->cashBook->recordMovement(
            session: $session,
            type: CashMovementType::CompanyCollection,
            currency: $payment->currency,
            amount: $payment->amountMoney(),
            actor: $user,
            description: $description,
            counterparty: null,
            referenceType: Payment::LEDGER_SOURCE_TYPE,
            referenceId: $payment->getKey(),
        );
    }

    /**
     * Nakit kanallı tahsilatın kasa hareketini (varsa) ters kaydeder.
     *
     * GÜNCEL açık vardiyaya yazılır — orijinal hareketin vardiyası bu ana
     * kadar çoktan kapanmış/devredilmiş olabilir (7/24 işletme gerçeği,
     * v2.1.1-A §13). Tarihe gidilmez: para BUGÜN düzeltilir, tıpkı gerçek bir
     * kasada olduğu gibi. Bağlı hareket yoksa (ör. bu özellikten önceki eski
     * kayıt) sessizce null döner — geriye dönük veri kırılmaz.
     *
     * @throws NoOpenCashSession
     */
    private function reverseCashCollection(Payment $payment, string $reason, User $user): ?CashMovement
    {
        $movement = $this->cashBook->activeMovementForSource(Payment::LEDGER_SOURCE_TYPE, $payment->getKey());

        if ($movement === null) {
            return null;
        }

        $session = $this->cashBook->activeSession();

        if ($session === null) {
            throw NoOpenCashSession::make();
        }

        return $this->cashBook->reverseMovement(
            movement: $movement,
            actor: $user,
            reason: '[İPTAL] '.$reason,
            intoSession: $session,
            allowLinkedSource: true,
        );
    }

    /** Kullanıcının kendi açık vardiyası; yoksa/başkasınınsa istisna fırlatır. */
    private function requireOwnedOpenSession(User $user): CashSession
    {
        $session = $this->cashBook->activeSession();

        if ($session === null) {
            throw NoOpenCashSession::make();
        }

        if ($session->opened_by !== $user->getKey()) {
            throw CashSessionNotOwnedByActor::make();
        }

        return $session;
    }

    /**
     * Kullanıcının kendi açık vardiyası — yoksa veya başkasınınsa NULL
     * (istisna FIRLATMAZ). Nakit dışı kanallar için "atfedebilirsek atfet,
     * atfedemiyorsak zorlamayalım" davranışı budur (v2.1.1-A.1 §7): Admin/
     * Patron açık vardiya olmadan da kart/banka tahsilatı girebilmelidir.
     */
    private function resolveOwnedActiveSession(User $user): ?CashSession
    {
        $session = $this->cashBook->activeSession();

        return $session !== null && $session->opened_by === $user->getKey() ? $session : null;
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
     * Bir vardiyanın tahsilat KANALI bazında özeti (v2.1.1-A §11,
     * v2.1.1-A.1 §10 ile EXPLICIT referansa geçirildi).
     *
     * "Bu vardiyada kim ne kadar kart/banka tahsilatı aldı" sorusu artık
     * ZAMAN PENCERESİ ÇIKARIMI DEĞİL — `payments.cash_session_id`'nin
     * oluşturma anında yazılan, bir daha değişmeyen DEĞERİNE göre sorgulanır.
     * Aynı kullanıcı vardiya sınırına yakın işlem yapsa bile yanlış vardiyaya
     * atfedilemez: kolon o an hangi vardiya açıksa (ve aktör sahibiyse) onu
     * taşır, sorgu zamanında yeniden hesaplanmaz.
     *
     * Eski (zaman penceresi tabanlı) sorgu TAMAMEN kaldırıldı — yalnızca bu
     * mimariden SONRA oluşturulan kayıtlar için doğru sonuç verirdi ve yanlış
     * bir kesinlik hissi yaratırdı.
     *
     * SABİT sayıda sorgudur: `totalsFor()` (1 grup sorgu) + kart + banka
     * (2 grup sorgu) = 3, vardiya kayıt sayısıyla büyümez.
     */
    public function shiftCollectionSummary(CashSession $session): ShiftCollectionSummary
    {
        $cashTotals = $this->cashBook->totalsFor($session);

        $cashCollections = array_filter(
            array_map(
                static fn ($totals) => $totals->companyCollection,
                $cashTotals,
            ),
            static fn (Money $money): bool => ! $money->isZero(),
        );

        return new ShiftCollectionSummary(
            session: $session,
            cashCollections: $cashCollections,
            creditCardCollections: $this->collectionTotalsForSession($session, PaymentMethod::CreditCard),
            bankTransferCollections: $this->collectionTotalsForSession($session, PaymentMethod::BankTransfer),
            cashTotals: $cashTotals,
        );
    }

    /**
     * Bir vardiyaya EXPLICIT olarak atanmış (`cash_session_id`), TEK bir
     * kanaldan yazılan tahsilat tutarları. Zaman/kullanıcı çıkarımı YOKTUR.
     *
     * @return array<string, Money>
     */
    private function collectionTotalsForSession(CashSession $session, PaymentMethod $method): array
    {
        $rows = DB::table('payments')
            ->selectRaw('currency')
            ->selectRaw('CAST(COALESCE(SUM(amount), 0) AS CHAR) as total')
            ->where('status', PaymentStatus::Active->value)
            ->where('payment_method', $method->value)
            ->where('cash_session_id', $session->getKey())
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
