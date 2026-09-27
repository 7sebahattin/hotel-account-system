<?php

declare(strict_types=1);

namespace Tests\Feature\Personnel;

use App\Models\User;
use App\Modules\Personnel\Models\PersonnelEmployee;
use App\Modules\Personnel\Models\PersonnelScanEvent;
use App\Modules\Personnel\Models\PersonnelShiftVersion;
use App\Modules\Personnel\Services\AttendanceService;
use App\Modules\Personnel\Services\PayrollService;
use App\Modules\Personnel\Services\ScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PersonnelAttendanceFinalizationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function future_shift_is_pending_and_completed_absence_has_missing_minutes(): void
    {
        $actor = User::factory()->create();
        $employee = $this->employee($actor, '2026-08-31');
        $this->shift($employee, $actor, '2026-08-31', [1, 2]);
        $attendance = app(AttendanceService::class);

        $past = $attendance->day($employee, '2026-08-31');
        $this->assertSame('absent', $past['status']);
        $this->assertSame(480, $past['missing_minutes']);
        $this->assertTrue($past['period_finalized']);

        $futureDate = today('Europe/Istanbul')->next(Carbon::TUESDAY)->toDateString();
        $future = $attendance->day($employee, $futureDate);
        $this->assertSame('pending', $future['status']);
        $this->assertSame(0, $future['missing_minutes']);
        $this->assertFalse($future['period_finalized']);
    }

    #[Test]
    public function only_scans_of_the_corrected_workday_make_its_snapshot_stale(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-01T00:00:00Z'));
        try {
            $actor = User::factory()->create();
            $employee = $this->employee($actor, '2026-08-31');
            $this->shift($employee, $actor, '2026-08-31', [1], '22:00', '06:00', true);
            $attendance = app(AttendanceService::class);
            $attendance->correct($employee, ['work_date' => '2026-09-01', 'expected_revision' => 0, 'sessions' => [], 'reason' => 'Gün boş'], $actor->id);

            $this->scan($employee, '2026-09-01 00:30:00', 'in');
            $unrelated = $attendance->day($employee, '2026-09-01');
            $this->assertFalse($unrelated['post_correction_scan']);
            $this->assertFalse($unrelated['unresolved']);

            $this->scan($employee, '2026-09-01 10:10:00', 'in');
            $stale = $attendance->day($employee, '2026-09-01');
            $this->assertTrue($stale['post_correction_scan']);
            $this->assertTrue($stale['unresolved']);
        } finally {
            Carbon::setTestNow();
        }
    }

    #[Test]
    public function scan_after_correction_in_the_same_second_invalidates_the_snapshot(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-01T10:00:00Z'));
        try {
            $actor = User::factory()->create();
            $employee = $this->employee($actor, '2026-08-31');
            $this->shift($employee, $actor, '2026-08-31', [2]);
            $attendance = app(AttendanceService::class);
            $this->scan($employee, '2026-09-01 10:00:00', 'in');
            $firstScanId = PersonnelScanEvent::query()->where('employee_id', $employee->id)->max('id');

            $correction = $attendance->correct($employee, [
                'work_date' => '2026-09-01', 'expected_revision' => 0,
                'sessions' => [], 'reason' => 'Eksik kayıt düzeltmesi',
            ], $actor->id);
            $this->assertSame((int) $firstScanId, $correction->scan_watermark_id);
            $this->assertFalse($attendance->day($employee, '2026-09-01')['post_correction_scan']);

            $this->scan($employee, '2026-09-01 10:00:00', 'out');
            $row = $attendance->day($employee, '2026-09-01');
            $this->assertTrue($row['post_correction_scan']);
            $this->assertTrue($row['unresolved']);
        } finally {
            Carbon::setTestNow();
        }
    }

    #[Test]
    public function current_planned_day_cannot_be_finalized_before_shift_end(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-31T06:00:00Z'));
        try {
            $actor = User::factory()->create();
            $employee = $this->employee($actor, '2026-08-31');
            $this->shift($employee, $actor, '2026-08-31', [1]);
            app(ScheduleService::class)->assignWage($employee, [
                'effective_from' => '2026-08-31', 'wage_type' => 'monthly', 'amount' => '3100.00',
                'currency' => 'TRY', 'standard_daily_minutes' => 480,
                'overtime_multiplier_bp' => 15000, 'overtime_enabled' => false,
            ], $actor->id);
            $row = app(AttendanceService::class)->day($employee, '2026-08-31');
            $this->assertSame('pending', $row['status']);
            $this->assertFalse($row['period_finalized']);
            $this->assertSame(0, $row['missing_minutes']);
            $preview = app(PayrollService::class)->preview($employee, '2026-08');
            $this->assertNotEmpty($preview['issues']);
        } finally {
            Carbon::setTestNow();
        }
    }

    private function employee(User $actor, string $start): PersonnelEmployee
    {
        return PersonnelEmployee::query()->create([
            'employee_code' => 'ATT-'.$actor->id, 'first_name' => 'Ada', 'last_name' => 'Yılmaz',
            'start_date' => $start, 'is_active' => true, 'created_by' => $actor->id, 'updated_by' => $actor->id,
        ]);
    }

    private function shift(PersonnelEmployee $employee, User $actor, string $from, array $workdays, string $start = '09:00', string $end = '17:00', bool $overnight = false): void
    {
        $schedule = app(ScheduleService::class);
        $shift = $schedule->createShift([
            'name' => 'Test', 'start_time' => $start, 'end_time' => $end,
            'ends_next_day' => $overnight, 'break_minutes' => 0, 'workdays' => $workdays,
        ], $actor->id);
        $version = PersonnelShiftVersion::query()->where('shift_id', $shift->id)->firstOrFail();
        $schedule->assignShift($employee, $version->id, $from, $actor->id);
    }

    private function scan(PersonnelEmployee $employee, string $occurredAt, string $action): void
    {
        PersonnelScanEvent::query()->create([
            'request_id' => (string) Str::uuid(), 'payload_hash' => hash('sha256', $occurredAt.$action),
            'employee_id' => $employee->id, 'source' => 'kiosk', 'platform' => 'kiosk',
            'actor_user_id' => $employee->created_by, 'occurred_at' => $occurredAt,
            'requested_action' => $action, 'action' => $action, 'result' => 'accepted',
        ]);
    }
}
