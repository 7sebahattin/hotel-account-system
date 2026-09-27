<?php

declare(strict_types=1);

namespace App\Modules\Personnel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property int $id
 * @property int $shift_id
 * @property int $version
 * @property string $start_time
 * @property string $end_time
 * @property bool $ends_next_day
 * @property int $break_minutes
 * @property array<int,int> $workdays
 * @property int $late_grace_minutes
 * @property int $early_grace_minutes
 * @property-read PersonnelShift $shift
 */
class PersonnelShiftVersion extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'personnel_shift_versions';

    protected $fillable = ['shift_id', 'version', 'start_time', 'end_time', 'ends_next_day', 'break_minutes', 'workdays', 'late_grace_minutes', 'early_grace_minutes', 'created_by'];

    protected function casts(): array
    {
        return ['workdays' => 'array', 'ends_next_day' => 'boolean'];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(PersonnelShift::class, 'shift_id');
    }

    protected static function booted(): void
    {
        static::updating(static fn () => throw new LogicException('Vardiya sürümü değiştirilemez.'));
        static::deleting(static fn () => throw new LogicException('Vardiya sürümü silinemez.'));
    }
}
