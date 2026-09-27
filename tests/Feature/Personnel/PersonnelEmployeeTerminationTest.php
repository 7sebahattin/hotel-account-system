<?php

declare(strict_types=1);

namespace Tests\Feature\Personnel;

use App\Models\User;
use App\Modules\Personnel\Models\PersonnelEmployee;
use App\Modules\Personnel\Models\PersonnelPayrollEntry;
use App\Modules\Personnel\Services\EmployeeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PersonnelEmployeeTerminationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function prospective_exit_preserves_history_and_deactivation_does_not_set_exit_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-26T12:00:00Z'));
        try {
            $user = User::factory()->create();
            $employee = $this->employee($user);
            $this->earning($employee, $user, '2026-08');
            $service = app(EmployeeService::class);

            $service->setActive($employee, false, 'Geçici ara', $user->id);
            $this->assertNull($employee->fresh()->end_date);

            $updated = $service->update($employee, $this->changes($employee, '2026-09-27'), $user->id);
            $this->assertSame('2026-09-27', $updated->end_date?->toDateString());
            $this->assertSame('2026-08-01', $updated->start_date->toDateString());
            $this->assertSame(1, PersonnelPayrollEntry::query()->where('employee_id', $employee->id)->count());
        } finally {
            Carbon::setTestNow();
        }
    }

    #[Test]
    public function exit_cannot_precede_covered_payroll_period(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-26T12:00:00Z'));
        try {
            $user = User::factory()->create();
            $employee = $this->employee($user);
            $this->earning($employee, $user, '2026-09');

            $this->expectException(ValidationException::class);
            app(EmployeeService::class)->update($employee, $this->changes($employee, '2026-09-27'), $user->id);
        } finally {
            Carbon::setTestNow();
        }
    }

    #[Test]
    public function start_date_remains_frozen_after_history(): void
    {
        $user = User::factory()->create();
        $employee = $this->employee($user);
        $this->earning($employee, $user, '2026-08');
        $changes = $this->changes($employee, null);
        $changes['start_date'] = '2026-07-01';

        $this->expectException(ValidationException::class);
        app(EmployeeService::class)->update($employee, $changes, $user->id);
    }

    private function employee(User $user): PersonnelEmployee
    {
        return PersonnelEmployee::query()->create([
            'employee_code' => 'TERM-'.$user->id, 'first_name' => 'Ada', 'last_name' => 'Yılmaz',
            'start_date' => '2026-08-01', 'is_active' => true,
            'created_by' => $user->id, 'updated_by' => $user->id,
        ]);
    }

    private function earning(PersonnelEmployee $employee, User $user, string $period): void
    {
        PersonnelPayrollEntry::query()->create([
            'employee_id' => $employee->id, 'entry_date' => $period.'-01', 'period' => $period,
            'type' => 'earning', 'direction' => 'credit', 'amount' => '100.00', 'currency' => 'TRY',
            'description' => 'Geçmiş ücret', 'created_by' => $user->id,
        ]);
    }

    private function changes(PersonnelEmployee $employee, ?string $endDate): array
    {
        return [
            'employee_code' => $employee->employee_code, 'first_name' => 'Ada', 'last_name' => 'Yılmaz',
            'start_date' => '2026-08-01', 'end_date' => $endDate,
        ];
    }
}
