<?php

declare(strict_types=1);

namespace App\Modules\CashBook\Models;

use App\Models\User;
use App\Modules\CashBook\Database\Factories\CashMovementFactory;
use App\Modules\CashBook\Enums\CashMovementDirection;
use App\Modules\CashBook\Enums\CashMovementType;
use App\Modules\CashBook\Exceptions\CashMovementIsImmutable;
use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Fiziksel kasa hareketi — append-only (kullanıcı talimatı §3).
 *
 * Yazma yalnızca `CashBookService` üzerinden yapılır. Model olayları Eloquent'ten
 * geçen her değiştirme denemesini engeller; veritabanı tetikleyicileri ham SQL
 * dahil her yolu kapatır (ledger ile aynı ilke, kod kopyalanmadan).
 *
 * @property int $id
 * @property int $cash_session_id
 * @property CashMovementType $type
 * @property CashMovementDirection $direction
 * @property string $amount
 * @property Currency $currency
 * @property Carbon $entry_at
 * @property string|null $description
 * @property string|null $counterparty
 * @property string|null $reference_type
 * @property int|null $reference_id
 * @property bool $is_reversal
 * @property int|null $reverses_movement_id
 * @property int $created_by
 * @property Carbon $created_at
 * @property-read CashSession|null $session
 * @property-read User|null $creator
 * @property-read CashMovement|null $reversedMovement
 */
class CashMovement extends Model
{
    /** @use HasFactory<CashMovementFactory> */
    use HasFactory;

    /** Kayıt hiçbir zaman güncellenmez; updated_at kolonu yoktur. */
    public const UPDATED_AT = null;

    protected $table = 'cash_movements';

    protected $guarded = [];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'cash_session_id' => 'integer',
            'reference_id' => 'integer',
            'reverses_movement_id' => 'integer',
            'created_by' => 'integer',
            'type' => CashMovementType::class,
            'direction' => CashMovementDirection::class,
            'currency' => Currency::class,
            'amount' => 'decimal:2',
            'entry_at' => 'datetime',
            'is_reversal' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw CashMovementIsImmutable::cannotUpdate();
        });

        static::deleting(function (): void {
            throw CashMovementIsImmutable::cannotDelete();
        });
    }

    /** Eloquent dışından çağrılabilecek kısayolları da kapatır. */
    public function update(array $attributes = [], array $options = []): bool
    {
        throw CashMovementIsImmutable::cannotUpdate();
    }

    public function delete(): bool
    {
        throw CashMovementIsImmutable::cannotDelete();
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CashSession::class, 'cash_session_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reversedMovement(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_movement_id');
    }

    public function amountMoney(): Money
    {
        return Money::fromDecimal($this->amount);
    }

    /** Beklenen kasaya katkısı: giriş pozitif, çıkış negatif. */
    public function signedAmount(): Money
    {
        return $this->direction === CashMovementDirection::In
            ? $this->amountMoney()
            : $this->amountMoney()->negate();
    }

    public function isIn(): bool
    {
        return $this->direction === CashMovementDirection::In;
    }

    /** Ekranda gösterilecek etiket: ters kayıtlar açıkça işaretlenir. */
    public function label(): string
    {
        return $this->is_reversal
            ? '[İPTAL] '.$this->type->label()
            : $this->type->label();
    }

    protected static function newFactory(): CashMovementFactory
    {
        return CashMovementFactory::new();
    }
}
