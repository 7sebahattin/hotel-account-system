<?php

declare(strict_types=1);

namespace App\Modules\CashBook\Models;

use App\Models\User;
use App\Modules\CashBook\Exceptions\CashRecordIsImmutable;
use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Vardiya kapanışındaki fiziksel sayım — para birimi başına bir satır.
 *
 * Fark SAKLANMAZ, hesaplanır: hem beklenen hem sayılan tutar değişmez olduğu
 * için fark her zaman aynı çıkar; saklamak üçüncü bir doğruluk kaynağı yaratırdı.
 *
 * Satırın kendisi de DEĞİŞMEZDİR: kapanmış bir vardiyanın imzalanmış tanığıdır,
 * sonradan "düzeltilebilseydi" kapanış raporu ile gerçek sayım birbirinden
 * kopardı.
 *
 * @property int $id
 * @property int $cash_session_id
 * @property Currency $currency
 * @property string $expected_amount
 * @property string $counted_amount
 * @property int $created_by
 * @property Carbon $created_at
 * @property-read CashSession|null $session
 * @property-read User|null $creator
 */
class CashCount extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'cash_counts';

    protected $fillable = [
        'cash_session_id',
        'currency',
        'expected_amount',
        'counted_amount',
        'created_by',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw CashRecordIsImmutable::cannotUpdate('Kasa sayımı');
        });

        static::deleting(function (): void {
            throw CashRecordIsImmutable::cannotDelete('Kasa sayımı');
        });
    }

    public function update(array $attributes = [], array $options = []): bool
    {
        throw CashRecordIsImmutable::cannotUpdate('Kasa sayımı');
    }

    public function delete(): bool
    {
        throw CashRecordIsImmutable::cannotDelete('Kasa sayımı');
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'cash_session_id' => 'integer',
            'created_by' => 'integer',
            'currency' => Currency::class,
            'expected_amount' => 'decimal:2',
            'counted_amount' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CashSession::class, 'cash_session_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function expectedMoney(): Money
    {
        return Money::fromDecimal($this->expected_amount);
    }

    public function countedMoney(): Money
    {
        return Money::fromDecimal($this->counted_amount);
    }

    /** Sayılan − beklenen. Negatif = kasada eksik var. */
    public function differenceMoney(): Money
    {
        return $this->countedMoney()->subtract($this->expectedMoney());
    }

    public function hasDifference(): bool
    {
        return ! $this->differenceMoney()->isZero();
    }
}
