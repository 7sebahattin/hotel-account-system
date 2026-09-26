<?php

declare(strict_types=1);

namespace App\Modules\Personnel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property int $employee_id
 * @property Carbon $work_date
 * @property int $revision
 * @property int|null $scan_watermark_id
 * @property array<int,array{in_at:string,out_at:string}> $sessions
 */
class PersonnelAttendanceCorrection extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'personnel_attendance_corrections';

    protected $fillable = ['employee_id', 'work_date', 'revision', 'supersedes_id', 'scan_watermark_id', 'sessions', 'reason', 'created_by'];

    protected function casts(): array
    {
        return ['work_date' => 'date', 'scan_watermark_id' => 'integer', 'sessions' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(static fn () => throw new LogicException('Puantaj düzeltmesi değiştirilemez.'));
        static::deleting(static fn () => throw new LogicException('Puantaj düzeltmesi silinemez.'));
    }
}
