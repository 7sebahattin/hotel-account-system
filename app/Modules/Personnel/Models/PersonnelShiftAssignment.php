<?php

declare(strict_types=1);

namespace App\Modules\Personnel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property int $employee_id
 * @property int $shift_version_id
 * @property Carbon $effective_from
 * @property-read PersonnelShiftVersion $version
 */
class PersonnelShiftAssignment extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'personnel_shift_assignments';

    protected $fillable = ['employee_id', 'shift_version_id', 'effective_from', 'created_by'];

    protected function casts(): array
    {
        return ['effective_from' => 'date'];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(PersonnelShiftVersion::class, 'shift_version_id');
    }

    protected static function booted(): void
    {
        static::updating(static fn () => throw new LogicException('Vardiya ataması değiştirilemez.'));
        static::deleting(static fn () => throw new LogicException('Vardiya ataması silinemez.'));
    }
}
