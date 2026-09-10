<?php

declare(strict_types=1);

namespace App\Modules\CashBook\Models;

use App\Models\User;
use App\Modules\CashBook\Database\Factories\CashSessionFactory;
use App\Modules\CashBook\Enums\CashSessionStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Kasa vardiyası.
 *
 * İşletmede aynı anda yalnızca BİR açık vardiya olur; kısıt veritabanındaki
 * `UNIQUE(is_open)` ile uygulanır (bkz. migration). `is_open` kolonu modelden
 * elle set EDİLMEZ — `open()` ve `close()` servis metotları yönetir.
 *
 * @property int $id
 * @property int $opened_by
 * @property Carbon $opened_at
 * @property Carbon|null $closed_at
 * @property int|null $closed_by
 * @property CashSessionStatus $status
 * @property int|null $is_open
 * @property string|null $opening_note
 * @property string|null $closing_note
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User|null $openedBy
 * @property-read User|null $closedBy
 * @property-read Collection<int, CashMovement> $movements
 * @property-read Collection<int, CashCount> $counts
 */
class CashSession extends Model
{
    /** @use HasFactory<CashSessionFactory> */
    use HasFactory;

    protected $table = 'cash_sessions';

    protected $fillable = [
        'opened_by',
        'opened_at',
        'opening_note',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'opened_by' => 'integer',
            'closed_by' => 'integer',
            'is_open' => 'integer',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'status' => CashSessionStatus::class,
        ];
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /** @return HasMany<CashMovement, $this> */
    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class, 'cash_session_id');
    }

    /** @return HasMany<CashCount, $this> */
    public function counts(): HasMany
    {
        return $this->hasMany(CashCount::class, 'cash_session_id');
    }

    /** Bu vardiyadan çıkan devir(ler). */
    public function outgoingTransfers(): HasMany
    {
        return $this->hasMany(CashTransfer::class, 'from_session_id');
    }

    /** Bu vardiyayı başlatan devir (varsa). */
    public function incomingTransfer(): BelongsTo
    {
        return $this->belongsTo(CashTransfer::class, 'id', 'to_session_id');
    }

    public function isOpen(): bool
    {
        return $this->status === CashSessionStatus::Open;
    }

    public function isClosed(): bool
    {
        return $this->status === CashSessionStatus::Closed;
    }

    protected static function newFactory(): CashSessionFactory
    {
        return CashSessionFactory::new();
    }
}
