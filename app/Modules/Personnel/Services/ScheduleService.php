<?php

declare(strict_types=1);

namespace App\Modules\Personnel\Services;

use App\Modules\AuditLog\Enums\AuditEvent;
use App\Modules\AuditLog\Services\AuditLogger;
use App\Modules\Core\Support\Money;
use App\Modules\Personnel\Models\PersonnelEmployee;
use App\Modules\Personnel\Models\PersonnelShift;
use App\Modules\Personnel\Models\PersonnelShiftAssignment;
use App\Modules\Personnel\Models\PersonnelShiftVersion;
use App\Modules\Personnel\Models\PersonnelWageAssignment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScheduleService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function createShift(array $data, int $actorId): PersonnelShift
    {
        return DB::transaction(function () use ($data, $actorId): PersonnelShift {
            $shift = PersonnelShift::query()->create(['name' => $data['name'], 'is_active' => true, 'created_by' => $actorId, 'updated_by' => $actorId]);
            $this->createVersion($shift, $data, $actorId);
            $this->audit->log(AuditEvent::Created, $shift, newValues: ['name' => $shift->name]);

            return $shift;
        });
    }

    public function createVersion(PersonnelShift $shift, array $data, int $actorId): PersonnelShiftVersion
    {
        return DB::transaction(function () use ($shift, $data, $actorId): PersonnelShiftVersion {
            $shift = PersonnelShift::query()->lockForUpdate()->findOrFail($shift->id);
            $start = Carbon::createFromFormat('H:i', substr($data['start_time'], 0, 5));
            $end = Carbon::createFromFormat('H:i', substr($data['end_time'], 0, 5));
            $duration = $start->diffInMinutes($end, false) + (! empty($data['ends_next_day']) ? 1440 : 0);
            if ($duration <= 0 || $duration > 1440 || $data['break_minutes'] >= $duration) {
                throw ValidationException::withMessages(['end_time' => 'Vardiya süresi 0–24 saat ve mola süresinden uzun olmalıdır.']);
            }
            $days = array_values(array_unique(array_map('intval', $data['workdays'])));
            sort($days);
            if ($days === [] || min($days) < 1 || max($days) > 7) {
                throw ValidationException::withMessages(['workdays' => 'Geçerli çalışma günleri seçin.']);
            }

            $version = PersonnelShiftVersion::query()->create([
                'shift_id' => $shift->id,
                'version' => (int) PersonnelShiftVersion::query()->where('shift_id', $shift->id)->max('version') + 1,
                'start_time' => $data['start_time'], 'end_time' => $data['end_time'],
                'ends_next_day' => (bool) $data['ends_next_day'], 'break_minutes' => (int) $data['break_minutes'],
                'workdays' => $days, 'late_grace_minutes' => (int) ($data['late_grace_minutes'] ?? 0),
                'early_grace_minutes' => (int) ($data['early_grace_minutes'] ?? 0), 'created_by' => $actorId,
            ]);
            $this->audit->log(AuditEvent::Created, $shift, newValues: ['version' => $version->version]);

            return $version;
        });
    }

    public function setShiftActive(PersonnelShift $shift, bool $active, int $actorId): void
    {
        DB::transaction(function () use ($shift, $active, $actorId): void {
            $shift = PersonnelShift::query()->lockForUpdate()->findOrFail($shift->id);
            $shift->update(['is_active' => $active, 'updated_by' => $actorId]);
            $this->audit->log(AuditEvent::Updated, $shift, newValues: ['is_active' => $active]);
        });
    }

    public function assignShift(PersonnelEmployee $employee, int $versionId, string $date, int $actorId): PersonnelShiftAssignment
    {
        return DB::transaction(function () use ($employee, $versionId, $date, $actorId): PersonnelShiftAssignment {
            $employee = PersonnelEmployee::query()->lockForUpdate()->findOrFail($employee->id);
            $version = PersonnelShiftVersion::query()->findOrFail($versionId);
            if (! PersonnelShift::query()->whereKey($version->shift_id)->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(['shift_version_id' => 'Pasif vardiya atanamaz.']);
            }
            $this->assertAssignmentDate($employee, $date, 'personnel_shift_assignments');

            $assignment = PersonnelShiftAssignment::query()->create(['employee_id' => $employee->id, 'shift_version_id' => $versionId, 'effective_from' => $date, 'created_by' => $actorId]);
            $this->audit->log(AuditEvent::Created, $employee, newValues: ['shift_version_id' => $versionId, 'effective_from' => $date]);

            return $assignment;
        });
    }

    public function assignWage(PersonnelEmployee $employee, array $data, int $actorId): PersonnelWageAssignment
    {
        if (! Money::fromDecimal($data['amount'])->isPositive()) {
            throw ValidationException::withMessages(['amount' => 'Ücret tutarı pozitif olmalıdır.']);
        }

        return DB::transaction(function () use ($employee, $data, $actorId): PersonnelWageAssignment {
            $employee = PersonnelEmployee::query()->lockForUpdate()->findOrFail($employee->id);
            $this->assertAssignmentDate($employee, $data['effective_from'], 'personnel_wage_assignments');

            $wage = PersonnelWageAssignment::query()->create($data + ['employee_id' => $employee->id, 'created_by' => $actorId]);
            $this->audit->log(AuditEvent::Created, $employee, newValues: ['wage_assignment_id' => $wage->id, 'effective_from' => $data['effective_from']]);

            return $wage;
        });
    }

    private function assertAssignmentDate(PersonnelEmployee $employee, string $date, string $table): void
    {
        $hasAssignments = DB::table($table)->where('employee_id', $employee->id)->exists();
        $hasHistory = DB::table('personnel_scan_events')->where('employee_id', $employee->id)->exists()
            || DB::table('personnel_payroll_entries')->where('employee_id', $employee->id)->exists();
        if (($hasAssignments || $hasHistory) && $date <= today('Europe/Istanbul')->toDateString()) {
            throw ValidationException::withMessages(['effective_from' => 'Sonraki atamalar en erken yarın geçerli olabilir.']);
        }
        if ($date < $employee->start_date->toDateString()) {
            throw ValidationException::withMessages(['effective_from' => 'Atama işe giriş tarihinden önce olamaz.']);
        }
    }
}
