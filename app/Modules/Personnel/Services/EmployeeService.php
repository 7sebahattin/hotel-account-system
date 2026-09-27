<?php

declare(strict_types=1);

namespace App\Modules\Personnel\Services;

use App\Modules\AuditLog\Enums\AuditEvent;
use App\Modules\AuditLog\Services\AuditLogger;
use App\Modules\Personnel\Models\PersonnelEmployee;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployeeService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function create(array $data, int $actorId): PersonnelEmployee
    {
        return DB::transaction(function () use ($data, $actorId): PersonnelEmployee {
            $employee = PersonnelEmployee::query()->create($data + ['created_by' => $actorId, 'updated_by' => $actorId, 'is_active' => true]);
            $this->audit->log(AuditEvent::Created, $employee, newValues: ['employee_code' => $employee->employee_code]);

            return $employee;
        });
    }

    public function update(PersonnelEmployee $employee, array $data, int $actorId): PersonnelEmployee
    {
        return DB::transaction(function () use ($employee, $data, $actorId): PersonnelEmployee {
            $employee = PersonnelEmployee::query()->lockForUpdate()->findOrFail($employee->id);
            $today = today('Europe/Istanbul')->toDateString();
            $oldEnd = $employee->end_date?->toDateString();
            $newEnd = $data['end_date'] ?? null;
            $endChanged = $oldEnd !== $newEnd;
            if ($endChanged && $oldEnd !== null && $oldEnd < $today) {
                throw ValidationException::withMessages(['end_date' => 'Geçmiş işten ayrılma tarihi değiştirilemez.']);
            }
            $hasHistory = DB::table('personnel_scan_events')->where('employee_id', $employee->id)->exists()
                || DB::table('personnel_attendance_corrections')->where('employee_id', $employee->id)->exists()
                || DB::table('personnel_payroll_entries')->where('employee_id', $employee->id)->exists();
            if ($hasHistory && $employee->start_date->toDateString() !== $data['start_date']) {
                throw ValidationException::withMessages(['start_date' => 'Geçmiş kayıtları bulunan işe giriş tarihi değiştirilemez.']);
            }
            if ($hasHistory && $endChanged && $newEnd !== null) {
                $latestScan = DB::table('personnel_scan_events')->where('employee_id', $employee->id)->max('occurred_at');
                $latestScanDate = $latestScan === null ? null : Carbon::parse($latestScan, 'UTC')->timezone('Europe/Istanbul')->toDateString();
                $latestCorrection = DB::table('personnel_attendance_corrections')->where('employee_id', $employee->id)->max('work_date');
                $lastEarningPeriod = DB::table('personnel_payroll_entries')->where('employee_id', $employee->id)->where('type', 'earning')->max('period');
                $earningEnd = $lastEarningPeriod === null ? null : Carbon::parse($lastEarningPeriod.'-01')->endOfMonth()->toDateString();
                $coveredDates = array_filter([$latestScanDate, $latestCorrection, $earningEnd]);
                $coveredThrough = $coveredDates === [] ? null : max($coveredDates);
                if ($newEnd < $today || ($coveredThrough !== null && $newEnd < $coveredThrough)) {
                    throw ValidationException::withMessages(['end_date' => 'Ayrılma tarihi bugün veya daha sonra ve kayıtlı geçmişin son gününden önce olmamalıdır.']);
                }
            }
            $employee->update($data + ['updated_by' => $actorId]);
            $this->audit->log(AuditEvent::Updated, $employee, oldValues: ['end_date' => $oldEnd], newValues: ['employee_code' => $employee->employee_code, 'end_date' => $newEnd]);

            return $employee;
        });
    }

    public function setActive(PersonnelEmployee $employee, bool $active, string $reason, int $actorId): PersonnelEmployee
    {
        return DB::transaction(function () use ($employee, $active, $reason, $actorId): PersonnelEmployee {
            $employee = PersonnelEmployee::query()->lockForUpdate()->findOrFail($employee->id);
            $before = $employee->is_active;
            $employee->update(['is_active' => $active, 'updated_by' => $actorId]);
            $this->audit->log(AuditEvent::Updated, $employee, oldValues: ['is_active' => $before], newValues: ['is_active' => $active, 'reason' => $reason]);

            return $employee;
        });
    }

    public function assertEmployed(PersonnelEmployee $employee, string $date): void
    {
        if (! $employee->is_active || $employee->start_date->toDateString() > $date || ($employee->end_date !== null && $employee->end_date->toDateString() < $date)) {
            throw ValidationException::withMessages(['employee' => 'Çalışan bu tarihte etkin değil.']);
        }
    }
}
