<?php

declare(strict_types=1);

namespace App\Modules\Payments\Models;

use App\Models\User;
use App\Modules\CashBook\Models\CashSession;
use App\Modules\Companies\Models\Company;
use App\Modules\Core\Concerns\HasCreator;
use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
use App\Modules\Core\Support\TurkishNumberToWords;
use App\Modules\Payments\Database\Factories\PaymentFactory;
use App\Modules\Payments\Enums\PaymentMethod;
use App\Modules\Payments\Enums\PaymentStatus;
use App\Modules\Payments\Exceptions\PaymentFinancialFieldsAreImmutable;
use DateTimeInterface;
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
 * @property Carbon $payment_date
 * @property string $amount
 * @property Currency $currency
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
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Company|null $company
 * @property-read User|null $creator
 * @property-read User|null $updater
 * @property-read User|null $canceller
 * @property-read CashSession|null $cashSession
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
    ];

    /** Düzeltilebilen tek alanlar. */
    public const EDITABLE_FIELDS = ['reference_no', 'description'];

    protected $fillable = [
        'company_id',
        'payment_date',
        'amount',
        'currency',
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
            'created_by' => 'integer',
            'updated_by' => 'integer',
            'cancelled_by' => 'integer',
            'cash_session_id' => 'integer',
            'payment_date' => 'date',
            'amount' => 'decimal:2',
            'currency' => Currency::class,
            'payment_method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'cancelled_at' => 'datetime',
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

    /**
     * Tahsilatın OLUŞTURULDUĞU vardiya (varsa) — v2.1.1-A.1.
     *
     * Eloquent ilişki tanımı, §4.3/1'in izin verdiği TEK istisnadır (iş
     * mantığı değil). NULL: oluşturma anında aktör açık bir vardiyayı
     * yönetmiyordu — zaman damgasından ASLA tahmin edilmez (§8/Payments).
     */
    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function amountMoney(): Money
    {
        return Money::fromDecimal($this->amount);
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
