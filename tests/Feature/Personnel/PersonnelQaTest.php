<?php

declare(strict_types=1);

namespace Tests\Feature\Personnel;

use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Personnel\Models\PersonnelAttendanceCorrection;
use App\Modules\Personnel\Models\PersonnelCardAssignment;
use App\Modules\Personnel\Models\PersonnelEmployee;
use App\Modules\Personnel\Models\PersonnelPayrollEntry;
use App\Modules\Personnel\Models\PersonnelScanEvent;
use App\Modules\Personnel\Models\PersonnelShiftVersion;
use App\Modules\Personnel\Services\AttendanceService;
use App\Modules\Personnel\Services\CardService;
use App\Modules\Personnel\Services\PayrollService;
use App\Modules\Personnel\Services\PersonnelReportService;
use App\Modules\Personnel\Services\ScheduleService;
use App\Modules\Personnel\Services\TerminalService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class PersonnelQaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    #[Test]
    public function personnel_forms_submit_the_fields_required_by_the_server(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $this->actingAs($admin)->get(route('personnel.employees.create'))
            ->assertOk()->assertSee('name="first_name"', false)->assertSee('name="last_name"', false)
            ->assertDontSee('name="full_name"', false);

        $this->actingAs($admin)->get(route('personnel.shifts.index'))
            ->assertOk()->assertSee('name="ends_next_day"', false)->assertSee('name="workdays[]"', false);
    }

    #[Test]
    public function attendance_detail_exposes_the_correction_workflow(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $employee = $this->employee($admin->id, 'QA-CORRECTION-UI', today('Europe/Istanbul')->toDateString());

        $this->actingAs($admin)->get(route('personnel.employees.show', ['employee' => $employee, 'month' => today('Europe/Istanbul')->format('Y-m')]))
            ->assertOk()->assertSee('action="'.route('personnel.attendance-corrections.store', $employee).'"', false)
            ->assertSee('name="work_date"', false)->assertSee('name="expected_revision"', false)
            ->assertSee('name="reason"', false);
    }

    #[Test]
    public function payroll_detail_renders_a_selected_employee_with_empty_preview_days(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $employee = $this->employee($admin->id, 'QA-PAYROLL-VIEW', today('Europe/Istanbul')->toDateString());

        $this->actingAs($admin)->get(route('personnel.payroll.index', [
            'month' => today('Europe/Istanbul')->format('Y-m'), 'employee_id' => $employee->id,
        ]))->assertOk();
    }

    #[Test]
    public function missing_wage_preview_is_a_read_only_blocker_and_employee_page_still_renders(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-31 12:00:00', 'Europe/Istanbul'));
        try {
            $admin = $this->userWithRole(RoleName::Admin);
            $employee = $this->employee($admin->id, 'QA-NO-WAGE', '2026-08-31');
            $preview = app(PayrollService::class)->preview($employee, '2026-08');

            $this->assertSame([], $preview['days']);
            $this->assertSame([], $preview['earnings']);
            $this->assertNotEmpty($preview['issues']);
            $this->assertSame(0, PersonnelPayrollEntry::query()->count());
            $this->actingAs($admin)->get(route('personnel.employees.show', [
                'employee' => $employee, 'month' => '2026-08',
            ]))->assertOk()->assertSee('QA-NO-WAGE');
            $this->assertSame(0, PersonnelPayrollEntry::query()->count());
        } finally {
            Carbon::setTestNow();
        }
    }

    #[Test]
    public function terminal_bearer_signature_is_required_and_scan_responses_are_not_cached(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $employee = $this->employee($admin->id, 'QA-TERMINAL-SIGNATURE', today('Europe/Istanbul')->toDateString());
        app(CardService::class)->assign($employee, ['uid' => '04AABBCCDD0199'], $admin->id);
        [, $token] = app(TerminalService::class)->create([
            'name' => 'QA signed terminal', 'platform' => 'android', 'device_uuid' => (string) Str::uuid(),
        ], $admin->id);
        $payload = ['request_id' => (string) Str::uuid(), 'card_uid' => '04AABBCCDD0199'];
        $tamperedToken = substr($token, 0, -1).(str_ends_with($token, 'f') ? 'e' : 'f');

        $accepted = $this->postJson('/api/personnel/scans', $payload, ['Authorization' => 'Bearer '.$token]);
        $accepted->assertOk();
        $this->assertTrue($accepted->headers->hasCacheControlDirective('no-store'));

        $tampered = $this->postJson('/api/personnel/scans', $payload, ['Authorization' => 'Bearer '.$tamperedToken]);
        $tampered->assertUnauthorized();
        $this->assertTrue($tampered->headers->hasCacheControlDirective('no-store'));

        $malformed = $this->postJson('/api/personnel/scans', $payload, ['Authorization' => 'Bearer '.$token.'x']);
        $malformed->assertUnauthorized();
        $this->assertTrue($malformed->headers->hasCacheControlDirective('no-store'));
    }

    #[Test]
    public function default_personel_role_is_limited_to_scan_routes_across_the_new_route_matrix(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $personel = $this->userWithRole(RoleName::Personel);
        $employee = $this->employee($admin->id, 'QA-AUTH', '2026-08-01');
        $shift = app(ScheduleService::class)->createShift([
            'name' => 'QA vardiyası', 'start_time' => '09:00', 'end_time' => '17:00',
            'ends_next_day' => false, 'break_minutes' => 0, 'workdays' => [1, 2, 3, 4, 5],
        ], $admin->id);
        $version = PersonnelShiftVersion::query()->where('shift_id', $shift->id)->firstOrFail();
        $cardAssignment = app(CardService::class)->assign($employee, ['uid' => '04AABBCCDD0099'], $admin->id);
        [$terminal] = app(TerminalService::class)->create([
            'name' => 'QA terminal', 'platform' => 'android', 'device_uuid' => (string) Str::uuid(),
        ], $admin->id);
        $entry = app(PayrollService::class)->addEntry($employee, [
            'type' => 'bonus', 'entry_date' => '2026-08-31', 'amount' => '1.00', 'currency' => 'TRY',
            'description' => 'QA bonus', 'request_id' => (string) Str::uuid(),
        ], $admin->id);

        $requests = [
            ['GET', route('personnel.index')], ['GET', route('personnel.employees.index')],
            ['GET', route('personnel.employees.create')], ['POST', route('personnel.employees.store')],
            ['GET', route('personnel.employees.show', $employee)], ['GET', route('personnel.employees.edit', $employee)],
            ['PUT', route('personnel.employees.update', $employee)], ['PATCH', route('personnel.employees.active', $employee)],
            ['GET', route('personnel.shifts.index')], ['POST', route('personnel.shifts.store')],
            ['POST', route('personnel.shifts.versions.store', $shift)], ['PATCH', route('personnel.shifts.active', $shift)],
            ['POST', route('personnel.shift-assignments.store', $employee)], ['POST', route('personnel.wage-assignments.store', $employee)],
            ['POST', route('personnel.cards.store', $employee)], ['POST', route('personnel.cards.release', $cardAssignment->card_id)],
            ['PATCH', route('personnel.cards.active', $cardAssignment->card_id)], ['GET', route('personnel.attendance.index')],
            ['POST', route('personnel.attendance-corrections.store', $employee)], ['GET', route('personnel.reports.daily')],
            ['GET', route('personnel.reports.daily.print')], ['GET', route('personnel.reports.monthly')],
            ['GET', route('personnel.reports.monthly.print')], ['GET', route('personnel.payroll.index')],
            ['POST', route('personnel.payroll.calculate', $employee)], ['POST', route('personnel.payroll.entries.store', $employee)],
            ['POST', route('personnel.payroll.entries.reverse', $entry)], ['GET', route('personnel.terminals.index')],
            ['POST', route('personnel.terminals.store')], ['POST', route('personnel.terminals.revoke', $terminal)],
        ];

        $this->actingAs($personel);
        foreach ($requests as [$method, $url]) {
            $this->call($method, $url)->assertForbidden();
        }
        $this->get(route('personnel.kiosk'))->assertOk();
    }

    #[Test]
    public function administrator_can_render_every_personnel_get_screen_with_existing_records(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $employee = $this->employee($admin->id, 'QA-ADMIN-PAGES', today('Europe/Istanbul')->toDateString());
        $month = today('Europe/Istanbul')->format('Y-m');
        $requests = [
            route('personnel.index'), route('personnel.employees.index'), route('personnel.employees.create'),
            route('personnel.employees.show', $employee), route('personnel.employees.edit', $employee),
            route('personnel.shifts.index'), route('personnel.kiosk'), route('personnel.attendance.index'),
            route('personnel.reports.daily'), route('personnel.reports.daily.print'),
            route('personnel.reports.monthly', ['month' => $month]),
            route('personnel.reports.monthly.print', ['month' => $month]),
            route('personnel.payroll.index'), route('personnel.terminals.index'),
        ];
        $this->actingAs($admin);

        foreach ($requests as $url) {
            $this->get($url)->assertOk();
        }
    }

    #[Test]
    public function monthly_report_totals_include_late_minutes_from_attendance(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $employee = $this->employee($admin->id, 'QA-LATE', '2026-08-31');
        $shift = app(ScheduleService::class)->createShift([
            'name' => 'QA vardiyası', 'start_time' => '09:00', 'end_time' => '17:00',
            'ends_next_day' => false, 'break_minutes' => 0, 'workdays' => [1],
            'late_grace_minutes' => 5, 'early_grace_minutes' => 5,
        ], $admin->id);
        $version = PersonnelShiftVersion::query()->where('shift_id', $shift->id)->firstOrFail();
        app(ScheduleService::class)->assignShift($employee, $version->id, '2026-08-31', $admin->id);
        app(AttendanceService::class)->correct($employee, [
            'work_date' => '2026-08-31', 'expected_revision' => 0, 'reason' => 'QA fixture',
            'sessions' => [[
                'in_at' => '2026-08-31T09:30:00+03:00', 'out_at' => '2026-08-31T16:30:00+03:00',
            ]],
        ], $admin->id);

        $report = app(PersonnelReportService::class)->monthly('2026-08', [], false);

        $this->assertSame(25, $report['totalsByEmployee'][$employee->id]['late_minutes']);
        $this->assertSame(25, $report['totalsByEmployee'][$employee->id]['early_minutes']);
    }

    #[Test]
    public function late_arrival_is_counted_while_the_employee_session_is_still_open(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $employee = $this->employee($admin->id, 'QA-OPEN-LATE', '2026-08-31');
        $shift = app(ScheduleService::class)->createShift([
            'name' => 'QA vardiyası', 'start_time' => '09:00', 'end_time' => '17:00',
            'ends_next_day' => false, 'break_minutes' => 0, 'workdays' => [1], 'late_grace_minutes' => 5,
        ], $admin->id);
        $version = PersonnelShiftVersion::query()->where('shift_id', $shift->id)->firstOrFail();
        app(ScheduleService::class)->assignShift($employee, $version->id, '2026-08-31', $admin->id);
        $this->scanEvent($employee->id, '2026-08-31 06:30:00', 'in'); // 09:30 Istanbul time

        $row = app(AttendanceService::class)->day($employee, '2026-08-31');

        $this->assertSame('missing_checkout', $row['status']);
        $this->assertSame(25, $row['late_minutes']);
    }

    #[Test]
    public function monthly_print_includes_people_beyond_the_first_report_page(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        foreach (range(1, 101) as $number) {
            $this->employee($admin->id, sprintf('QA-%03d', $number), '2026-08-31');
        }

        $this->actingAs($admin)->get(route('personnel.reports.monthly.print', ['month' => '2026-08']))
            ->assertOk()->assertSee('QA 101');
    }

    #[Test]
    public function monthly_report_shows_each_employees_payroll_amount(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $employee = $this->employee($admin->id, 'QA-MONTHLY-REPORT', '2026-08-31');
        PersonnelPayrollEntry::query()->create([
            'employee_id' => $employee->id, 'entry_date' => '2026-08-31', 'period' => '2026-08',
            'type' => 'earning', 'direction' => 'credit', 'amount' => '125.50', 'currency' => 'TRY',
            'description' => 'QA earning', 'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->get(route('personnel.reports.monthly', [
            'month' => '2026-08', 'employee_id' => $employee->id,
        ]))->assertOk()->assertSee('125,50');
    }

    #[Test]
    public function attendance_correction_cannot_overlap_raw_sessions_from_neighboring_workday(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $employee = $this->employee($admin->id, 'QA-OVERLAP', '2026-08-31');
        $this->scanEvent($employee->id, '2026-08-31 20:00:00', 'in');
        $this->scanEvent($employee->id, '2026-09-01 03:00:00', 'out');

        $this->expectException(ValidationException::class);
        app(AttendanceService::class)->correct($employee, [
            'work_date' => '2026-09-01', 'expected_revision' => 0, 'reason' => 'Overlapping neighboring day',
            'sessions' => [[
                'in_at' => '2026-09-01T02:30:00Z', 'out_at' => '2026-09-01T04:00:00Z',
            ]],
        ], $admin->id);
    }

    #[Test]
    public function terminal_request_id_cannot_be_replayed_with_a_different_action(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $employee = $this->employee($admin->id, 'QA-REPLAY', '2026-08-01');
        app(CardService::class)->assign($employee, ['uid' => '04AABBCCDD0077'], $admin->id);
        [, $token] = app(TerminalService::class)->create([
            'name' => 'QA terminal', 'platform' => 'android', 'device_uuid' => (string) Str::uuid(),
        ], $admin->id);
        $requestId = (string) Str::uuid();
        $headers = ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];
        $this->postJson('/api/personnel/scans', ['request_id' => $requestId, 'card_uid' => '04AABBCCDD0077'], $headers)
            ->assertOk()->assertJsonPath('action', 'in');

        $this->postJson('/api/personnel/scans', [
            'request_id' => $requestId, 'card_uid' => '04AABBCCDD0077', 'action' => 'in',
        ], $headers)->assertConflict();
        $this->assertSame(1, PersonnelScanEvent::query()->count());
    }

    #[Test]
    public function automatic_scans_toggle_after_duplicate_window_and_support_multiple_sessions(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $employee = $this->employee($admin->id, 'QA-AUTO-TOGGLE', today('Europe/Istanbul')->toDateString());
        app(CardService::class)->assign($employee, ['uid' => '04AABBCCDD0088'], $admin->id);
        [, $token] = app(TerminalService::class)->create([
            'name' => 'QA terminal', 'platform' => 'android', 'device_uuid' => (string) Str::uuid(),
        ], $admin->id);
        $headers = ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];
        Carbon::setTestNow(Carbon::parse(today('Europe/Istanbul')->toDateString().' 09:00:00', 'Europe/Istanbul')->utc());
        try {
            $actions = [];
            for ($index = 0; $index < 4; $index++) {
                if ($index > 0) {
                    Carbon::setTestNow(now('UTC')->addSeconds(11));
                }
                $response = $this->postJson('/api/personnel/scans', [
                    'request_id' => (string) Str::uuid(), 'card_uid' => '04AABBCCDD0088',
                ], $headers)->assertOk()->assertJsonPath('result', 'accepted');
                $actions[] = $response->json('action');
            }

            $this->assertSame(['in', 'out', 'in', 'out'], $actions);
            $this->assertSame(4, PersonnelScanEvent::query()->count());
        } finally {
            Carbon::setTestNow();
        }
    }

    #[Test]
    public function unknown_card_inactive_card_and_inactive_employee_return_distinct_safe_results(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $inactiveCardEmployee = $this->employee($admin->id, 'QA-INACTIVE-CARD', today('Europe/Istanbul')->toDateString());
        $inactiveAssignment = app(CardService::class)->assign($inactiveCardEmployee, ['uid' => '04AABBCCDD0101'], $admin->id);
        app(CardService::class)->setActive($inactiveAssignment->card, false, $admin->id);
        $inactiveEmployee = $this->employee($admin->id, 'QA-INACTIVE-EMPLOYEE', today('Europe/Istanbul')->toDateString());
        app(CardService::class)->assign($inactiveEmployee, ['uid' => '04AABBCCDD0102'], $admin->id);
        $inactiveEmployee->update(['is_active' => false]);
        [, $token] = app(TerminalService::class)->create([
            'name' => 'QA terminal', 'platform' => 'android', 'device_uuid' => (string) Str::uuid(),
        ], $admin->id);
        $headers = ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];

        $this->postJson('/api/personnel/scans', [
            'request_id' => (string) Str::uuid(), 'card_uid' => '04AABBCCDD0103',
        ], $headers)->assertOk()->assertJsonPath('result', 'unknown_card')->assertJsonPath('employee_name', null);
        $this->postJson('/api/personnel/scans', [
            'request_id' => (string) Str::uuid(), 'card_uid' => '04AABBCCDD0101',
        ], $headers)->assertOk()->assertJsonPath('result', 'inactive_card')->assertJsonPath('employee_name', $inactiveCardEmployee->full_name);
        $this->postJson('/api/personnel/scans', [
            'request_id' => (string) Str::uuid(), 'card_uid' => '04AABBCCDD0102',
        ], $headers)->assertOk()->assertJsonPath('result', 'inactive_employee')->assertJsonPath('employee_name', $inactiveEmployee->full_name);
    }

    #[Test]
    public function card_reassignment_closes_the_old_assignment_and_preserves_history(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $firstEmployee = $this->employee($admin->id, 'QA-CARD-1', '2026-08-01');
        $secondEmployee = $this->employee($admin->id, 'QA-CARD-2', '2026-08-01');
        $cards = app(CardService::class);
        $original = $cards->assign($firstEmployee, ['uid' => '04AABBCCDD0201'], $admin->id);

        try {
            $cards->assign($secondEmployee, ['uid' => '04AABBCCDD0201'], $admin->id);
            $this->fail('Etkin kart aynı anda iki personele atanamaz.');
        } catch (ValidationException) {
            $this->assertSame(1, PersonnelCardAssignment::query()->where('card_id', $original->card_id)->count());
        }

        $cards->release($original->card, 'QA reassignment', $admin->id);
        $reassigned = $cards->assign($secondEmployee, ['uid' => '04AABBCCDD0201'], $admin->id);

        $this->assertSame(2, PersonnelCardAssignment::query()->where('card_id', $original->card_id)->count());
        $this->assertNotNull($original->fresh()->getAttribute('released_at'));
        $this->assertSame($secondEmployee->id, $reassigned->employee_id);
    }

    #[Test]
    public function overnight_shift_keeps_two_sessions_on_the_start_workday_and_deducts_break_once(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $employee = $this->employee($admin->id, 'QA-OVERNIGHT', '2026-08-31');
        $shift = app(ScheduleService::class)->createShift([
            'name' => 'QA gece', 'start_time' => '22:00', 'end_time' => '06:00',
            'ends_next_day' => true, 'break_minutes' => 30, 'workdays' => [1],
        ], $admin->id);
        $version = PersonnelShiftVersion::query()->where('shift_id', $shift->id)->firstOrFail();
        app(ScheduleService::class)->assignShift($employee, $version->id, '2026-08-31', $admin->id);
        $this->scanEvent($employee->id, '2026-08-31 19:00:00', 'in');
        $this->scanEvent($employee->id, '2026-08-31 20:00:00', 'out');
        $this->scanEvent($employee->id, '2026-08-31 20:30:00', 'in');
        $this->scanEvent($employee->id, '2026-08-31 21:30:00', 'out');

        $row = app(AttendanceService::class)->day($employee, '2026-08-31');

        $this->assertSame('present', $row['status']);
        $this->assertCount(2, $row['sessions']);
        $this->assertSame(120, $row['worked_minutes']);
        $this->assertSame(30, $row['break_minutes']);
        $this->assertSame(90, $row['net_minutes']);
    }

    #[Test]
    public function payroll_payments_require_method_and_reversal_restores_balance_without_editing_history(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $employee = $this->employee($admin->id, 'QA-PAY', '2026-08-01');
        $payroll = app(PayrollService::class);
        $requestId = (string) Str::uuid();
        $payment = [
            'type' => 'payment', 'entry_date' => '2026-08-31', 'amount' => '25.05', 'currency' => 'TRY',
            'description' => 'QA ödeme', 'request_id' => $requestId,
        ];
        try {
            $payroll->addEntry($employee, $payment, $admin->id);
            $this->fail('Ödeme yöntemi zorunlu olmalı.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('personnel_payroll_entries', 0);
        }

        $entry = $payroll->addEntry($employee, $payment + ['payment_method' => 'bank_transfer'], $admin->id);
        $reversal = $payroll->reverse($entry, 'QA iptal', (string) Str::uuid(), $admin->id);

        $this->assertSame('debit', $entry->direction);
        $this->assertSame('credit', $reversal->direction);
        $this->assertSame('25.05', $reversal->amount);
        $totals = app(PersonnelReportService::class)->payroll('2026-08', ['employee_id' => $employee->id]);
        $this->assertSame('0.00', $totals['rows'][0]['balance']['decimal']);
        $this->expectException(ConflictHttpException::class);
        $payroll->reverse($entry, 'İkinci ters kayıt', (string) Str::uuid(), $admin->id);
    }

    #[Test]
    public function full_monthly_rate_is_exact_in_a_31_day_month_with_all_planned_shifts_worked(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $employee = $this->employee($admin->id, 'QA-MONTHLY', '2026-08-01');
        $shift = app(ScheduleService::class)->createShift([
            'name' => 'QA gündüz', 'start_time' => '09:00', 'end_time' => '17:00',
            'ends_next_day' => false, 'break_minutes' => 0, 'workdays' => [1, 2, 3, 4, 5],
        ], $admin->id);
        $version = PersonnelShiftVersion::query()->where('shift_id', $shift->id)->firstOrFail();
        app(ScheduleService::class)->assignShift($employee, $version->id, '2026-08-01', $admin->id);
        app(ScheduleService::class)->assignWage($employee, [
            'effective_from' => '2026-08-01', 'wage_type' => 'monthly', 'amount' => '1000.00', 'currency' => 'TRY',
            'standard_daily_minutes' => 480, 'overtime_multiplier_bp' => 15000, 'overtime_enabled' => false,
        ], $admin->id);

        for ($day = 1; $day <= 31; $day++) {
            $date = sprintf('2026-08-%02d', $day);
            if (Carbon::parse($date)->dayOfWeekIso > 5) {
                continue;
            }
            app(AttendanceService::class)->correct($employee, [
                'work_date' => $date, 'expected_revision' => 0, 'reason' => 'QA full-month fixture',
                'sessions' => [[
                    'in_at' => $date.'T09:00:00+03:00', 'out_at' => $date.'T17:00:00+03:00',
                ]],
            ], $admin->id);
        }

        $preview = app(PayrollService::class)->preview($employee, '2026-08');

        $this->assertSame([], $preview['issues']);
        $this->assertSame('1000.00', $preview['earnings']['TRY']);
    }

    #[Test]
    public function midmonth_employment_and_wage_start_are_prorated_without_missing_wage_issues(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $employee = $this->employee($admin->id, 'QA-PRORATE', '2026-08-15');
        $shift = app(ScheduleService::class)->createShift([
            'name' => 'QA gündüz', 'start_time' => '09:00', 'end_time' => '17:00',
            'ends_next_day' => false, 'break_minutes' => 0, 'workdays' => [1, 2, 3, 4, 5],
        ], $admin->id);
        $version = PersonnelShiftVersion::query()->where('shift_id', $shift->id)->firstOrFail();
        app(ScheduleService::class)->assignShift($employee, $version->id, '2026-08-15', $admin->id);
        app(ScheduleService::class)->assignWage($employee, [
            'effective_from' => '2026-08-15', 'wage_type' => 'monthly', 'amount' => '3100.00', 'currency' => 'TRY',
            'standard_daily_minutes' => 480, 'overtime_multiplier_bp' => 15000, 'overtime_enabled' => false,
        ], $admin->id);
        for ($day = 15; $day <= 31; $day++) {
            $date = sprintf('2026-08-%02d', $day);
            if (Carbon::parse($date)->dayOfWeekIso > 5) {
                continue;
            }
            app(AttendanceService::class)->correct($employee, [
                'work_date' => $date, 'expected_revision' => 0, 'reason' => 'QA prorated fixture',
                'sessions' => [[
                    'in_at' => $date.'T09:00:00+03:00', 'out_at' => $date.'T17:00:00+03:00',
                ]],
            ], $admin->id);
        }

        $preview = app(PayrollService::class)->preview($employee, '2026-08');

        $this->assertSame([], $preview['issues']);
        $this->assertSame('1700.00', $preview['earnings']['TRY']);
    }

    #[Test]
    public function daily_and_hourly_wages_apply_standard_minutes_and_overtime_multiplier(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $dailyEmployee = $this->employee($admin->id, 'QA-DAILY-OT', '2026-08-31');
        $hourlyEmployee = $this->employee($admin->id, 'QA-HOURLY-OT', '2026-08-31');
        $shift = app(ScheduleService::class)->createShift([
            'name' => 'QA overtime', 'start_time' => '09:00', 'end_time' => '17:00',
            'ends_next_day' => false, 'break_minutes' => 0, 'workdays' => [1],
        ], $admin->id);
        $version = PersonnelShiftVersion::query()->where('shift_id', $shift->id)->firstOrFail();

        foreach ([
            [$dailyEmployee, 'daily', '100.00'],
            [$hourlyEmployee, 'hourly', '20.00'],
        ] as [$employee, $type, $amount]) {
            app(ScheduleService::class)->assignShift($employee, $version->id, '2026-08-31', $admin->id);
            app(ScheduleService::class)->assignWage($employee, [
                'effective_from' => '2026-08-31', 'wage_type' => $type, 'amount' => $amount, 'currency' => 'TRY',
                'standard_daily_minutes' => 480, 'overtime_multiplier_bp' => 15000, 'overtime_enabled' => true,
            ], $admin->id);
            app(AttendanceService::class)->correct($employee, [
                'work_date' => '2026-08-31', 'expected_revision' => 0, 'reason' => 'QA overtime fixture',
                'sessions' => [[
                    'in_at' => '2026-08-31T09:00:00+03:00', 'out_at' => '2026-08-31T17:30:00+03:00',
                ]],
            ], $admin->id);
        }

        $payroll = app(PayrollService::class);
        $this->assertSame('109.38', $payroll->preview($dailyEmployee, '2026-08')['earnings']['TRY']);
        $this->assertSame('175.00', $payroll->preview($hourlyEmployee, '2026-08')['earnings']['TRY']);
    }

    #[Test]
    public function raw_scan_and_payroll_history_reject_sql_update_and_delete(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $employee = $this->employee($admin->id, 'QA-IMMUTABLE', '2026-08-01');
        $shift = app(ScheduleService::class)->createShift([
            'name' => 'QA immutable', 'start_time' => '09:00', 'end_time' => '17:00',
            'ends_next_day' => false, 'break_minutes' => 0, 'workdays' => [1, 2, 3, 4, 5],
        ], $admin->id);
        $version = PersonnelShiftVersion::query()->where('shift_id', $shift->id)->firstOrFail();
        $assignment = app(ScheduleService::class)->assignShift($employee, $version->id, '2026-08-01', $admin->id);
        $wage = app(ScheduleService::class)->assignWage($employee, [
            'effective_from' => '2026-08-01', 'wage_type' => 'hourly', 'amount' => '10.00', 'currency' => 'TRY',
            'standard_daily_minutes' => 480, 'overtime_multiplier_bp' => 15000, 'overtime_enabled' => false,
        ], $admin->id);
        $event = $this->scanEvent($employee->id, '2026-08-31 12:00:00', 'in');
        $correction = app(AttendanceService::class)->correct($employee, [
            'work_date' => '2026-08-30', 'expected_revision' => 0, 'reason' => 'QA correction', 'sessions' => [],
        ], $admin->id);
        $entry = app(PayrollService::class)->addEntry($employee, [
            'type' => 'bonus', 'entry_date' => '2026-08-31', 'amount' => '1.00', 'currency' => 'TRY',
            'description' => 'QA bonus', 'request_id' => (string) Str::uuid(),
        ], $admin->id);

        foreach ([
            fn () => DB::table('personnel_shift_versions')->where('id', $version->id)->update(['end_time' => '18:00']),
            fn () => DB::table('personnel_shift_versions')->where('id', $version->id)->delete(),
            fn () => DB::table('personnel_shift_assignments')->where('id', $assignment->id)->update(['effective_from' => '2026-08-02']),
            fn () => DB::table('personnel_shift_assignments')->where('id', $assignment->id)->delete(),
            fn () => DB::table('personnel_wage_assignments')->where('id', $wage->id)->update(['amount' => '20.00']),
            fn () => DB::table('personnel_wage_assignments')->where('id', $wage->id)->delete(),
            fn () => PersonnelScanEvent::query()->whereKey($event->id)->update(['result' => 'duplicate']),
            fn () => PersonnelScanEvent::query()->whereKey($event->id)->delete(),
            fn () => PersonnelAttendanceCorrection::query()->whereKey($correction->id)->update(['reason' => 'tampered']),
            fn () => PersonnelAttendanceCorrection::query()->whereKey($correction->id)->delete(),
            fn () => PersonnelPayrollEntry::query()->whereKey($entry->id)->update(['amount' => '2.00']),
            fn () => PersonnelPayrollEntry::query()->whereKey($entry->id)->delete(),
        ] as $mutate) {
            $this->assertTrue($this->isMutationBlocked($mutate));
        }

        $this->assertDatabaseHas('personnel_shift_versions', ['id' => $version->id, 'end_time' => '17:00:00']);
        $this->assertDatabaseHas('personnel_shift_assignments', ['id' => $assignment->id, 'effective_from' => '2026-08-01']);
        $this->assertDatabaseHas('personnel_wage_assignments', ['id' => $wage->id, 'amount' => '10.00']);
        $this->assertDatabaseHas('personnel_scan_events', ['id' => $event->id, 'result' => 'accepted']);
        $this->assertDatabaseHas('personnel_attendance_corrections', ['id' => $correction->id, 'reason' => 'QA correction']);
        $this->assertDatabaseHas('personnel_payroll_entries', ['id' => $entry->id, 'amount' => '1.00']);
    }

    private function employee(int $actorId, string $code, string $startDate): PersonnelEmployee
    {
        return PersonnelEmployee::query()->create([
            'employee_code' => $code, 'first_name' => 'QA', 'last_name' => Str::after($code, 'QA-'),
            'start_date' => $startDate, 'is_active' => true, 'created_by' => $actorId, 'updated_by' => $actorId,
        ]);
    }

    private function scanEvent(int $employeeId, string $occurredAt, string $action): PersonnelScanEvent
    {
        return PersonnelScanEvent::query()->create([
            'request_id' => (string) Str::uuid(), 'payload_hash' => hash('sha256', $occurredAt.$action),
            'employee_id' => $employeeId, 'source' => 'android', 'platform' => 'android',
            'occurred_at' => Carbon::parse($occurredAt, 'UTC'), 'requested_action' => $action,
            'action' => $action, 'result' => 'accepted',
        ]);
    }

    private function isMutationBlocked(callable $mutation): bool
    {
        try {
            $mutation();
        } catch (QueryException) {
            return true;
        }

        return false;
    }
}
