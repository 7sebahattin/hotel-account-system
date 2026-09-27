<?php

declare(strict_types=1);

namespace App\Modules\Personnel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property int $employee_id
 * @property Carbon $effective_from
 * @property string $wage_type
 * @property string $amount
 * @property string $currency
 * @property int $standard_daily_minutes
 * @property int $overtime_multiplier_bp
 * @property bool $overtime_enabled
 */
class PersonnelWageAssignment extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'personnel_wage_assignments';

    protected $fillable = ['employee_id', 'effective_from', 'wage_type', 'amount', 'currency', 'standard_daily_minutes', 'overtime_multiplier_bp', 'overtime_enabled', 'created_by'];

    protected function casts(): array
    {
        return ['effective_from' => 'date', 'amount' => 'decimal:2', 'overtime_enabled' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::updating(static fn () => throw new LogicException('Ücret ataması değiştirilemez.'));
        static::deleting(static fn () => throw new LogicException('Ücret ataması silinemez.'));
    }
}
