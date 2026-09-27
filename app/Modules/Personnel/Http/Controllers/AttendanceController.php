<?php

declare(strict_types=1);

namespace App\Modules\Personnel\Http\Controllers;

use App\Modules\Authorization\Enums\Permission;
use App\Modules\Personnel\Http\Requests\PersonnelFilterRequest;
use App\Modules\Personnel\Http\Requests\PersonnelRequest;
use App\Modules\Personnel\Models\PersonnelEmployee;
use App\Modules\Personnel\Services\AttendanceService;
use App\Modules\Personnel\Services\PersonnelPermissionService;
use App\Modules\Personnel\Services\PersonnelReportService;
use Illuminate\Support\Facades\Gate;

class AttendanceController
{
    public function index(PersonnelFilterRequest $request, PersonnelReportService $reports)
    {
        return view('personnel::attendance.index', $reports->daily($this->date($request), $request->only(['employee_id', 'department'])) + ['permissions' => app(PersonnelPermissionService::class)->forRequest($request)]);
    }

    public function correct(PersonnelRequest $request, PersonnelEmployee $employee, AttendanceService $attendance)
    {
        Gate::authorize('correct', $employee);
        $attendance->correct($employee, $request->validated(), (int) $request->user()->id);

        return back()->with('success', 'Puantaj düzeltmesi kaydedildi.');
    }

    public function daily(PersonnelFilterRequest $request, PersonnelReportService $reports)
    {
        return view('personnel::reports.daily', $reports->daily($this->date($request), $request->only(['employee_id', 'department'])) + ['permissions' => app(PersonnelPermissionService::class)->forRequest($request)]);
    }

    public function dailyPrint(PersonnelFilterRequest $request, PersonnelReportService $reports)
    {
        return view('personnel::reports.daily-print', $reports->daily($this->date($request), $request->only(['employee_id', 'department']), true) + ['permissions' => app(PersonnelPermissionService::class)->forRequest($request)]);
    }

    public function monthly(PersonnelFilterRequest $request, PersonnelReportService $reports)
    {
        return view('personnel::reports.monthly', $reports->monthly($this->month($request), $request->only(['employee_id', 'department', 'active']), $request->user()->can(Permission::PersonnelPayroll->value)) + ['permissions' => app(PersonnelPermissionService::class)->forRequest($request)]);
    }

    public function monthlyPrint(PersonnelFilterRequest $request, PersonnelReportService $reports)
    {
        return view('personnel::reports.monthly-print', $reports->monthly($this->month($request), $request->only(['employee_id', 'department', 'active']), $request->user()->can(Permission::PersonnelPayroll->value), true) + ['permissions' => app(PersonnelPermissionService::class)->forRequest($request)]);
    }

    private function date(PersonnelFilterRequest $request): string
    {
        $input = $request->validated('date');

        return is_string($input) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $input) ? $input : today('Europe/Istanbul')->toDateString();
    }

    private function month(PersonnelFilterRequest $request): string
    {
        $input = $request->validated('month');

        return is_string($input) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $input) ? $input : today('Europe/Istanbul')->format('Y-m');
    }
}
