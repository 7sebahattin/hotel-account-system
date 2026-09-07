<?php

declare(strict_types=1);

namespace App\Modules\Stays\Models;

use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Core\Concerns\HasCreator;
use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
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
 * @property Carbon $check_in_date
 * @property Carbon $check_out_date
 * @property int $nights
 * @property string $amount
 * @property StayPricingMode $pricing_mode
 * @property string|null $daily_rate
 * @property Currency $currency
 * @property string|null $notes
 * @property StayStatus $status
 * @property int $created_by
 * @property int|null $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Company|null $company
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
        'check_in_date',
        'check_out_date',
        'amount',
        'pricing_mode',
        'daily_rate',
        'currency',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            // company_id form'dan metin olarak gelir; cari servisine int geçmek
            // zorundayız (bkz. LedgerService::debit imzası).
            'company_id' => 'integer',
            'created_by' => 'integer',
            'updated_by' => 'integer',
            'check_in_date' => 'date',
            'check_out_date' => 'date',
            'amount' => 'decimal:2',
            'daily_rate' => 'decimal:2',
            'pricing_mode' => StayPricingMode::class,
            'currency' => Currency::class,
            'status' => StayStatus::class,
            'nights' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function amountMoney(): Money
    {
        return Money::fromDecimal($this->amount);
    }

    public function dailyRateMoney(): ?Money
    {
        return $this->daily_rate === null ? null : Money::fromDecimal($this->daily_rate);
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
