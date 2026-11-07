<?php

declare(strict_types=1);

namespace App\Modules\Payments\Models;

use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Core\Concerns\HasCreator;
use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
use App\Modules\Core\Support\TurkishNumberToWords;
use App\Modules\Payments\Database\Factories\PaymentFactory;
use App\Modules\Payments\Enums\AllocationMode;
use App\Modules\Payments\Enums\PayerType;
use App\Modules\Payments\Enums\PaymentMethod;
use App\Modules\Payments\Enums\PaymentStatus;
use App\Modules\Payments\Exceptions\PaymentFinancialFieldsAreImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Tahsilat kaydı.
 *
 * Denetim kaydı Auditable trait'i ile DEĞİL, PaymentService tarafından açıkça
 * yazılır: finansal kaydın denetim satırı ürettiği ya da ters kaydettiği cari
 * hareketlerin kimliklerini de taşımak zorundadır (CLAUDE.md §8).
 *
 * SoftDeletes KULLANILMAZ: tahsilat silinmez, iptal edilir.
 *
 * @property int $id
 * @property int $company_id
 * @property PayerType|null $payer_type
 * @property string|null $payer_name
 * @property AllocationMode|null $allocation_mode
 * @property Carbon $payment_date
 * @property string $amount
 * @property Currency $currency
 * @property string|null $exchange_rate_to_try
 * @property string|null $try_equivalent
 * @property PaymentMethod|null $payment_method
 * @property int|null $cash_session_id
 * @property string|null $receipt_no
 * @property string|null $reference_no
 * @property string|null $description
 * @property PaymentStatus $status
 * @property int $created_by
 * @property int|null $updated_by
 * @property Carbon|null $cancelled_at
 * @property int|null $cancelled_by
 * @property string|null $cancellation_reason
 * @property Carbon|null $trashed_at
 * @property int|null $trashed_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Company|null $company
 * @property-read User|null $creator
 * @property-read User|null $updater
 * @property-read User|null $canceller
 * @property-read User|null $trasher
 */
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasCreator, HasFactory;

    /** Cari hareketinde kaynak tipi olarak kullanılan morph takma adı. */
    public const LEDGER_SOURCE_TYPE = 'payment';

    /**
     * Oluşturulduktan sonra HİÇBİR rol tarafından değiştirilemeyen alanlar.
     *
     * Bu liste modelin `updating` olayında zorlanır; servis ve FormRequest
     * katmanları da aynı listeyi kullanır (üç katmanlı savunma).
     *
     * `cash_session_id` (v2.1.1-A.1): oluşturma anında AÇIKÇA belirlenir ve
     * bir daha ASLA yeniden atanmaz — iptal/ters kayıt bile bunu değiştirmez
     * (kaynak işlem hangi vardiyada olduysa öyle kalır, CLAUDE.md §8/Payments).
     *
     * `receipt_no` (v2.1.1-B): makbuz numarası bir kez üretilir, iptalde bile
     * DEĞİŞMEZ — geçmiş makbuz her zaman izlenebilir kalmalıdır (§13).
     *
     * @var array<int, string>
     */
    public const IMMUTABLE_FIELDS = [
        'company_id', 'payment_date', 'amount', 'currency', 'payment_method', 'cash_session_id', 'receipt_no',
        'payer_type', 'payer_name', 'allocation_mode',
    ];

    /** Düzeltilebilen tek alanlar. */
    public const EDITABLE_FIELDS = ['reference_no', 'description'];

    protected $fillable = [
        'payer_type', 'payer_name', 'allocation_mode',
        'company_id',
        'payment_date',
        'amount',
        'currency',
        'exchange_rate_to_try',
        'try_equivalent',
        'payment_method',
        'reference_no',
        'description',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            // Form alanları metin olarak gelir; cari servisine int geçmek
            // zorundayız (bkz. LedgerService::credit imzası).
            'company_id' => 'integer',
            'payer_type' => PayerType::class,
            'allocation_mode' => AllocationMode::class,
            'created_by' => 'integer',
            'updated_by' => 'integer',
            'cancelled_by' => 'integer',
            'cash_session_id' => 'integer',
            'payment_date' => 'date',
            'amount' => 'decimal:2',
            'currency' => Currency::class,
            'exchange_rate_to_try' => 'decimal:4',
            'try_equivalent' => 'decimal:2',
            'payment_method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'cancelled_at' => 'datetime',
            'trashed_at' => 'datetime',
            'trashed_by' => 'integer',
        ];
    }

    /**
     * Finansal alanların değişmezliği MODEL seviyesinde de zorlanır.
     *
     * Servis katmanı atlansa bile (konsol, ileride import kodu, doğrudan
     * `$payment->update()`) bu koruma devrededir. İptal akışı yalnızca
     * durum alanlarını değiştirdiği için buradan geçer.
     */
    protected static function booted(): void
    {
        // Çöpteki tahsilat HİÇBİR listede/sorguda yoktur (7 Kasım 2026); çöp kutusu,
        // geri alma ve kalıcı silme `withoutGlobalScopes()` kullanır.
        static::addGlobalScope('notTrashed', function (Builder $builder): void {
            $builder->whereNull($builder->getModel()->getTable().'.trashed_at');
        });

        static::updating(function (Payment $payment): void {
            $changed = array_values(array_intersect(
                self::IMMUTABLE_FIELDS,
                array_keys($payment->getDirty()),
            ));

            if ($changed !== []) {
                throw PaymentFinancialFieldsAreImmutable::make($changed);
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function trasher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trashed_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function amountMoney(): Money
    {
        return Money::fromDecimal($this->amount);
    }

    /**
     * TL karşılığı — yalnızca yabancı para birimli ve kuru KAYITLI
     * tahsilatlarda dolu (v2.1.1-C §2). İşlem anında bir kez hesaplanan bir
     * ANLIK GÖRÜNTÜdür; burada YENİDEN hesaplanmaz.
     */
    public function tryEquivalentMoney(): ?Money
    {
        return $this->try_equivalent === null ? null : Money::fromDecimal($this->try_equivalent);
    }

    public function hasExchangeRate(): bool
    {
        return $this->exchange_rate_to_try !== null;
    }

    /** Yabancı para birimli ve kur bilgisi EKSİK olan kayıt — düzeltme kuyruğuna girer. */
    public function isMissingExchangeRate(): bool
    {
        return $this->currency !== Currency::TRY && $this->exchange_rate_to_try === null;
    }

    public function isTrashed(): bool
    {
        return $this->trashed_at !== null;
    }

    public function isCancelled(): bool
    {
        return $this->status === PaymentStatus::Cancelled;
    }

    public function isActive(): bool
    {
        return $this->status === PaymentStatus::Active;
    }

    /** Cari hareketinin açıklaması — sabit format. */
    public function ledgerDescription(): string
    {
        $method = $this->payment_method?->label();

        return 'Tahsilat: '.$this->payment_date->format('d.m.Y').($method === null ? '' : ' · '.$method);
    }

    /**
     * Makbuz numarası — `id` üzerinden TÜRETİLİR, ayrı bir sıra tablosu
     * gerektirmez (v2.1.1-B §13). `id` MySQL AUTO_INCREMENT'i sayesinde
     * zaten eşzamanlılığa karşı güvenli ve benzersizdir; `MAX()+1` riski
     * taşımaz. Yalnızca kayıt GERÇEKTEN oluştuktan sonra (id atandıktan
     * sonra) çağrılabilir.
     */
    public static function receiptNumberFor(int $id, DateTimeInterface $issuedOn): string
    {
        return sprintf('TM-%s-%06d', $issuedOn->format('Y'), $id);
    }

    /**
     * "Yalnız: …" alanı — yalnızca TRY için üretilir (v2.1.1-B §15).
     *
     * Yabancı para birimleri için YANLIŞ/UYDURMA bir çeviri üretmek yerine
     * NULL döner; ekran bu durumda yalnızca sayısal tutar + ISO kodunu
     * gösterir ("Correctness over decorative completeness").
     */
    public function amountInWords(): ?string
    {
        if ($this->currency !== Currency::TRY) {
            return null;
        }

        $minorUnits = $this->amountMoney()->minorUnits;
        $lira = intdiv($minorUnits, 100);
        $kurus = $minorUnits % 100;

        $words = TurkishNumberToWords::convert($lira).' türk lirası';

        if ($kurus > 0) {
            $words .= ', '.TurkishNumberToWords::convert($kurus).' kuruş';
        }

        return TurkishNumberToWords::titleCase($words);
    }

    protected static function newFactory(): PaymentFactory
    {
        return PaymentFactory::new();
    }
}
