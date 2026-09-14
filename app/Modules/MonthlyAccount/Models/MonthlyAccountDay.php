<?php

declare(strict_types=1);

namespace App\Modules\MonthlyAccount\Models;

use App\Models\User;
use App\Modules\MonthlyAccount\Enums\MonthlyAccountStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property int $owner_id
 * @property Carbon $account_date
 * @property int $current_version
 * @property MonthlyAccountStatus $status
 * @property int $created_by
 * @property int $updated_by
 * @property-read MonthlyAccountRevision|null $currentRevision
 * @property-read User $owner
 */
class MonthlyAccountDay extends Model
{
    protected $fillable = ['owner_id', 'account_date', 'current_version', 'status', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return [
            'owner_id' => 'integer',
            'account_date' => 'date',
            'current_version' => 'integer',
            'status' => MonthlyAccountStatus::class,
            'created_by' => 'integer',
            'updated_by' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $day): void {
            if ($day->isDirty(['owner_id', 'account_date'])) {
                throw new LogicException('Kayıt sahibi ve tarih sonradan değiştirilemez.');
            }
        });

        static::deleting(function (): void {
            throw new LogicException('Aylık hesap günü kalıcı silinemez.');
        });
    }

    public function delete(): bool
    {
        throw new LogicException('Aylık hesap günü kalıcı silinemez.');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id')->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(MonthlyAccountRevision::class, 'day_id')->orderByDesc('version');
    }

    public function currentRevision(): HasOne
    {
        return $this->hasOne(MonthlyAccountRevision::class, 'day_id')->latestOfMany('version');
    }

    public function isDeleted(): bool
    {
        return $this->status === MonthlyAccountStatus::Deleted;
    }
}
