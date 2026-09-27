<?php

declare(strict_types=1);

namespace Tests\Feature\Personnel;

use App\Models\User;
use App\Modules\Personnel\Models\PersonnelEmployee;
use App\Modules\Personnel\Models\PersonnelShiftVersion;
use App\Modules\Personnel\Services\AttendanceService;
use App\Modules\Personnel\Services\PayrollService;
use App\Modules\Personnel\Services\ScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class PersonnelPayrollIntegrityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function manual_payment_replay_requires_identical_actor_and_body(): void
    {
        $actor = User::factory()->create();
        $other = User::factory()->create();
        $employee = $this->employee($actor);
        $payroll = app(PayrollService::class);
        $input = [
            'type' => 'payment', 'entry_date' => '2026-08-31', 'amount' => '25.05',
            'currency' => 'TRY', 'payment_method' => 'bank_transfer', 'description' => 'Avans',
            'request_id' => (string) Str::uuid(),
        ];
        $first = $payroll->addEntry($employee, $input, $actor->id);
        $this->assertSame($first->id, $payroll->addEntry($employee, $input, $actor->id)->id);
        foreach ([array_replace($input, ['currency' => 'USD']), array_replace($input, ['description' => 'Farklı']), $input] as $index => $changed) {
            try {
                $payroll->addEntry($employee, $changed, $index === 2 ? $other->id : $actor->id);
                $this->fail('Aynı UUID farklı aktör veya içerikle kabul edilmemeli.');
            } catch (ConflictHttpException) {
                $this->assertDatabaseCount('personnel_payroll_entries', 1);
            }
        }
    }

    #[Test]
    public function monthly_earning_can_be_reissued_after_reversal_with_new_request_id(): void
    {
        $actor = User::factory()->create();
        $employee = $this->employee($actor);
        $schedule = app(ScheduleService::class);
        $shift = $schedule->createShift([
            'name' => 'Gündüz', 'start_time' => '09:00', 'end_time' => '17:00',
            'ends_next_day' => false, 'break_minutes' => 0, 'workdays' => [1],
        ], $actor->id);
        $version = PersonnelShiftVersion::query()->where('shift_id', $shift->id)->firstOrFail();
        $schedule->assignShift($employee, $version->id, '2026-08-31', $actor->id);
        $schedule->assignWage($employee, [
            'effective_from' => '2026-08-31', 'wage_type' => 'monthly', 'amount' => '3100.00',
            'currency' => 'TRY', 'standard_daily_minutes' => 480,
            'overtime_multiplier_bp' => 15000, 'overtime_enabled' => false,
        ], $actor->id);
        app(AttendanceService::class)->correct($employee, [
            'work_date' => '2026-08-31', 'expected_revision' => 0, 'reason' => 'Tam gün',
            'sessions' => [['in_at' => '2026-08-31T09:00:00+03:00', 'out_at' => '2026-08-31T17:00:00+03:00']],
        ], $actor->id);
        $payroll = app(PayrollService::class);
        $preview = $payroll->preview($employee, '2026-08');
        $this->assertSame('100.00', $preview['earnings']['TRY']);
        $requestId = (string) Str::uuid();
        $first = $payroll->calculate($employee, '2026-08', $preview['digest'], $actor->id, $requestId)[0];
        $this->assertSame($first->id, $payroll->calculate($employee, '2026-08', $preview['digest'], $actor->id, $requestId)[0]->id);
        $payroll->reverse($first, 'Düzeltme', (string) Str::uuid(), $actor->id);
        $second = $payroll->calculate($employee, '2026-08', $preview['digest'], $actor->id, (string) Str::uuid())[0];
        $this->assertNotSame($first->id, $second->id);
        $this->assertDatabaseCount('personnel_payroll_entries', 3);
    }

    private function employee(User $actor): PersonnelEmployee
    {
        return PersonnelEmployee::query()->create([
            'employee_code' => 'PAY-'.$actor->id, 'first_name' => 'Ada', 'last_name' => 'Yılmaz',
            'start_date' => '2026-08-31', 'is_active' => true,
            'created_by' => $actor->id, 'updated_by' => $actor->id,
        ]);
    }
}
