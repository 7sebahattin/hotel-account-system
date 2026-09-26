<?php

declare(strict_types=1);

namespace App\Modules\Personnel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property int $employee_id
 * @property Carbon $entry_date
 * @property string $period
 * @property string $type
 * @property string $direction
 * @property string $amount
 * @property string $currency
 * @property string|null $payment_method
 * @property string|null $source_key
 * @property array<string,mixed>|null $calculation_snapshot
 * @property int $created_by
 * @property int|null $reverses_entry_id
 * @property string|null $description
 */
class PersonnelPayrollEntry extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'personnel_payroll_entries';

    protected $fillable = ['employee_id', 'entry_date', 'period', 'type', 'direction', 'amount', 'currency', 'payment_method', 'description', 'source_key', 'calculation_snapshot', 'reverses_entry_id', 'created_by'];

    protected function casts(): array
    {
        return ['entry_date' => 'date', 'amount' => 'decimal:2', 'calculation_snapshot' => 'array'];
    }

    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reverses_entry_id');
    }

    protected static function booted(): void
    {
        static::updating(static fn () => throw new LogicException('Ücret hareketi değiştirilemez.'));
        static::deleting(static fn () => throw new LogicException('Ücret hareketi silinemez.'));
    }
}
