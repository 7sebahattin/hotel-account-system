<?php

declare(strict_types=1);

namespace App\Modules\Payments\Services;

use App\Models\User;
use App\Modules\AuditLog\Enums\AuditEvent;
use App\Modules\AuditLog\Services\AuditLogger;
use App\Modules\Core\Support\Money;
use App\Modules\Ledger\Models\LedgerEntry;
use App\Modules\Ledger\Services\LedgerService;
use App\Modules\Payments\Enums\PaymentStatus;
use App\Modules\Payments\Exceptions\CannotUpdateCancelledPayment;
use App\Modules\Payments\Exceptions\PaymentAlreadyCancelled;
use App\Modules\Payments\Exceptions\PaymentFinancialFieldsAreImmutable;
use App\Modules\Payments\Models\Payment;
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
            $payment = new Payment;
            $payment->fill($this->fillableOnCreate($data));
            $payment->status = PaymentStatus::Active;
            $payment->created_by = $user->getKey();
            $payment->updated_by = $user->getKey();
            $payment->save();

            $entry = $this->postCredit($payment, $user);

            $this->auditLogger->log(
                event: AuditEvent::Created,
                subject: $payment,
                newValues: $this->snapshot($payment) + ['ledger_entry_id' => $entry->id],
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
            $active = $this->ledger->activeEntryForSource(Payment::LEDGER_SOURCE_TYPE, $payment->getKey());
            $reversal = $active !== null ? $this->ledger->reverse($active, actor: $user) : null;

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
