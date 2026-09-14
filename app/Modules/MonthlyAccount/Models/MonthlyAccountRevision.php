<?php

declare(strict_types=1);

namespace App\Modules\MonthlyAccount\Models;

use App\Models\User;
use App\Modules\Core\Support\Money;
use App\Modules\MonthlyAccount\Concerns\HasImmutableHistory;
use App\Modules\MonthlyAccount\Enums\MonthlyAccountStatus;
use App\Modules\MonthlyAccount\Services\MonthlyAccountSummary;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $day_id
 * @property int $version
 * @property MonthlyAccountStatus $status
 * @property string $daily_total
 * @property string|null $note
 * @property int $created_by
 * @property-read Collection<int, MonthlyAccountItem> $items
 */
class MonthlyAccountRevision extends Model
{
    use HasImmutableHistory;

    public const UPDATED_AT = null;

    protected $fillable = ['day_id', 'version', 'status', 'daily_total', 'note', 'created_by'];

    protected function casts(): array
    {
        return [
            'day_id' => 'integer',
            'version' => 'integer',
            'status' => MonthlyAccountStatus::class,
            'daily_total' => 'decimal:2',
            'created_by' => 'integer',
        ];
    }

    public function day(): BelongsTo
    {
        return $this->belongsTo(MonthlyAccountDay::class, 'day_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(MonthlyAccountItem::class, 'revision_id')->orderBy('id');
    }

    public function dailyTotalMoney(): Money
    {
        return Money::fromDecimal($this->daily_total);
    }

    public function summary(): array
    {
        return MonthlyAccountSummary::fromRevisions([$this]);
    }
}
