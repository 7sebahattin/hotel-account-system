<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Models;

use App\Models\User;
use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
use App\Modules\Ledger\Database\Factories\LedgerEntryFactory;
use App\Modules\Ledger\Exceptions\LedgerEntryIsImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Cari hareket. CLAUDE.md §6: append-only; UPDATE ve DELETE yasaktır.
 *
 * Yazma yalnızca LedgerService üzerinden yapılır. Model olayları Eloquent'ten
 * geçen her değiştirme denemesini engeller; veritabanı tetikleyicileri ise
 * ham SQL dahil her yolu kapatır.
 *
 * @property int $id
 * @property int $company_id
 * @property string|null $source_type
 * @property int|null $source_id
 * @property Carbon $entry_date
 * @property string $description
 * @property string $debit
 * @property string $credit
 * @property Currency $currency
 * @property bool $is_reversal
 * @property int|null $reverses_entry_id
 * @property int $created_by
 * @property Carbon $created_at
 * @property-read User|null $creator
 * @property-read LedgerEntry|null $reversedEntry
 * @property-read LedgerEntry|null $reversalEntry
 */
class LedgerEntry extends Model
{
    /** @use HasFactory<LedgerEntryFactory> */
    use HasFactory;

    /** Kayıt hiçbir zaman güncellenmez; updated_at kolonu yoktur. */
    public const UPDATED_AT = null;

    protected $table = 'ledger_entries';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
            'currency' => Currency::class,
            'is_reversal' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw LedgerEntryIsImmutable::cannotUpdate();
        });

        static::deleting(function (): void {
            throw LedgerEntryIsImmutable::cannotDelete();
        });
    }

    /** Eloquent dışından çağrılabilecek kısayolları da kapatır. */
    public function update(array $attributes = [], array $options = []): bool
    {
        throw LedgerEntryIsImmutable::cannotUpdate();
    }

    public function delete(): bool
    {
        throw LedgerEntryIsImmutable::cannotDelete();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Bu kaydın ters kaydını aldığı orijinal hareket. */
    public function reversedEntry(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_entry_id');
    }

    public function debitMoney(): Money
    {
        return Money::fromDecimal($this->debit);
    }

    public function creditMoney(): Money
    {
        return Money::fromDecimal($this->credit);
    }

    /** Hareketin tutarı — borç veya alacak, hangisi doluysa. */
    public function amount(): Money
    {
        return $this->debitMoney()->isZero() ? $this->creditMoney() : $this->debitMoney();
    }

    /** Bakiyeye katkısı: borç pozitif, alacak negatif. */
    public function signedAmount(): Money
    {
        return $this->debitMoney()->subtract($this->creditMoney());
    }

    public function isDebit(): bool
    {
        return $this->debitMoney()->isPositive();
    }

    protected static function newFactory(): LedgerEntryFactory
    {
        return LedgerEntryFactory::new();
    }
}
