<?php

declare(strict_types=1);

namespace App\Modules\Stays\Models;

use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Core\Concerns\HasCreator;
use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
use App\Modules\Rooms\Models\Room;
use App\Modules\Stays\Database\Factories\StayFactory;
use App\Modules\Stays\Enums\StayPricingMode;
use App\Modules\Stays\Enums\StayStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Konaklama kaydı.
 *
 * Denetim kaydı Auditable trait'i ile DEĞİL, StayService tarafından açıkça
 * yazılır: finansal düzeltmelerin denetim kaydı ters kayıt ve yeni kayıt
 * kimliklerini de taşımak zorundadır; genel trait bunu ifade edemez.
 *
 * @property int $id
 * @property int $company_id
 * @property string $guest_name
 * @property string|null $room_no
 * @property int|null $room_id
 * @property Carbon $check_in_date
 * @property Carbon $check_out_date
 * @property int $nights
 * @property string $amount
 * @property StayPricingMode $pricing_mode
 * @property string|null $daily_rate
 * @property Currency $currency
 * @property string|null $exchange_rate_to_try
 * @property string|null $try_equivalent
 * @property string|null $notes
 * @property StayStatus $status
 * @property Carbon|null $invoiced_at
 * @property int|null $invoiced_by
 * @property int $created_by
 * @property int|null $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Company|null $company
 * @property-read Room|null $room
 * @property-read User|null $creator
 * @property-read User|null $updater
 */
class Stay extends Model
{
    /** @use HasFactory<StayFactory> */
    use HasCreator, HasFactory, SoftDeletes;

    /** Cari hareketinde kaynak tipi olarak kullanılan morph takma adı. */
    public const LEDGER_SOURCE_TYPE = 'stay';

    protected $fillable = [
        'company_id',
        'guest_name',
        'room_no',
        'room_id',
        'check_in_date',
        'check_out_date',
        'amount',
        'pricing_mode',
        'daily_rate',
        'currency',
        'exchange_rate_to_try',
        'try_equivalent',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            // company_id form'dan metin olarak gelir; cari servisine int geçmek
            // zorundayız (bkz. LedgerService::debit imzası).
            'company_id' => 'integer',
            'room_id' => 'integer',
            'created_by' => 'integer',
            'updated_by' => 'integer',
            'check_in_date' => 'date',
            'check_out_date' => 'date',
            'amount' => 'decimal:2',
            'daily_rate' => 'decimal:2',
            'pricing_mode' => StayPricingMode::class,
            'currency' => Currency::class,
            'exchange_rate_to_try' => 'decimal:4',
            'try_equivalent' => 'decimal:2',
            'status' => StayStatus::class,
            'invoiced_at' => 'datetime',
            'invoiced_by' => 'integer',
            'nights' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Oda Registry (K-100). Eloquent ilişki tanımı serbesttir (CLAUDE.md
     * §4.3/1 istisnası, `company()` ile AYNI desen) — iş mantığı taşımaz,
     * yalnızca gösterim için kullanılır.
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * Görüntülenecek oda etiketi: `room_id` set edilmişse odanın kendi
     * etiketi, aksi hâlde geriye dönük uyumluluk için eski serbest metin
     * (`room_no`) — hiçbiri yoksa null. Eski veri hiçbir zaman yeniden
     * yazılmaz (kullanıcı talimatı: Room Registry §2).
     */
    public function roomLabel(): ?string
    {
        return $this->room?->label() ?? $this->room_no;
    }

    public function amountMoney(): Money
    {
        return Money::fromDecimal($this->amount);
    }

    public function dailyRateMoney(): ?Money
    {
        return $this->daily_rate === null ? null : Money::fromDecimal($this->daily_rate);
    }

    /**
     * TL karşılığı — yalnızca yabancı para birimli ve kuru KAYITLI konaklamalarda
     * dolu (v2.1.1-C §2). İşlem anında bir kez hesaplanan bir ANLIK GÖRÜNTÜdür;
     * burada YENİDEN hesaplanmaz, yalnızca saklanan değer okunur.
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

    public function isDailyPriced(): bool
    {
        return $this->pricing_mode === StayPricingMode::Daily;
    }

    /**
     * Günlük ücretlendirmede nihai toplam (CLAUDE.md §8).
     *
     * Formdan gelen tutar YOK SAYILIR; toplam her zaman gecelik ücret × gece
     * sayısı olarak yeniden hesaplanır. Çarpım tamsayı kuruş üzerinden
     * yapılır, kayan nokta kullanılmaz.
     */
    public static function calculateDailyAmount(Money $dailyRate, int $nights): Money
    {
        return $dailyRate->multiply($nights);
    }

    /** Fatura kesildi mi? (Yalnızca takip işareti — e-Fatura entegrasyonu değildir.) */
    public function isInvoiced(): bool
    {
        return $this->invoiced_at !== null;
    }

    public function isCancelled(): bool
    {
        return $this->status === StayStatus::Cancelled;
    }

    public function isActive(): bool
    {
        return $this->status === StayStatus::Active;
    }

    /** Cari hareketinin açıklaması — sabit format (CLAUDE.md §8). */
    public function ledgerDescription(): string
    {
        return sprintf(
            'Konaklama: %s (%s - %s)',
            $this->guest_name,
            $this->check_in_date->format('d.m.Y'),
            $this->check_out_date->format('d.m.Y'),
        );
    }

    /**
     * Gece sayısı sunucuda hesaplanır; formdan gelen değere güvenilmez.
     *
     * Asgari gece sayısı 1'dir: aynı gün giriş/çıkış kabul edilmez
     * (validasyon `after:check_in_date`, veritabanında `stays_date_range`
     * ve `stays_nights_positive` CHECK kısıtları).
     */
    public static function calculateNights(Carbon $checkIn, Carbon $checkOut): int
    {
        return (int) $checkIn->startOfDay()->diffInDays($checkOut->startOfDay());
    }

    protected static function newFactory(): StayFactory
    {
        return StayFactory::new();
    }
}
