<?php

declare(strict_types=1);

namespace App\Modules\CashBook\Models;

use App\Models\User;
use App\Modules\CashBook\Enums\CashTransferStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Personelden personele kasa devri — İKİ TARAFLI onay gerektirir.
 *
 * Teslim eden devri başlatır (`pending`); teslim alan kendi hesabıyla kabul
 * edene kadar devir TAMAMLANMAZ. Kabul anında teslim eden vardiya kapanır ve
 * teslim alan için yeni vardiya açılır; tutarlar yeni vardiyanın açılış
 * hareketleri olur.
 *
 * @property int $id
 * @property int $from_session_id
 * @property int|null $to_session_id
 * @property int $from_user_id
 * @property int $to_user_id
 * @property CashTransferStatus $status
 * @property Carbon $initiated_at
 * @property Carbon|null $responded_at
 * @property string|null $note
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User|null $fromUser
 * @property-read User|null $toUser
 * @property-read CashSession|null $fromSession
 * @property-read CashSession|null $toSession
 * @property-read Collection<int, CashTransferAmount> $amounts
 */
class CashTransfer extends Model
{
    protected $table = 'cash_transfers';

    protected $fillable = [
        'from_session_id',
        'from_user_id',
        'to_user_id',
        'initiated_at',
        'note',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'from_session_id' => 'integer',
            'to_session_id' => 'integer',
            'from_user_id' => 'integer',
            'to_user_id' => 'integer',
            'status' => CashTransferStatus::class,
            'initiated_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function fromSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class, 'from_session_id');
    }

    public function toSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class, 'to_session_id');
    }

    /** @return HasMany<CashTransferAmount, $this> */
    public function amounts(): HasMany
    {
        return $this->hasMany(CashTransferAmount::class, 'cash_transfer_id');
    }

    public function isPending(): bool
    {
        return $this->status === CashTransferStatus::Pending;
    }

    public function isAccepted(): bool
    {
        return $this->status === CashTransferStatus::Accepted;
    }
}
