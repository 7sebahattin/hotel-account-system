<?php

declare(strict_types=1);

namespace App\Modules\Personnel\Http\Controllers;

use App\Modules\Core\Enums\Currency;
use App\Modules\Personnel\Http\Requests\PersonnelFilterRequest;
use App\Modules\Personnel\Http\Requests\PersonnelRequest;
use App\Modules\Personnel\Models\PersonnelAttendanceCorrection;
use App\Modules\Personnel\Models\PersonnelCard;
use App\Modules\Personnel\Models\PersonnelCardAssignment;
use App\Modules\Personnel\Models\PersonnelEmployee;
use App\Modules\Personnel\Models\PersonnelPayrollEntry;
use App\Modules\Personnel\Models\PersonnelShiftAssignment;
use App\Modules\Personnel\Models\PersonnelShiftVersion;
use App\Modules\Personnel\Models\PersonnelWageAssignment;
use App\Modules\Personnel\Services\AttendanceService;
use App\Modules\Personnel\Services\CardService;
use App\Modules\Personnel\Services\EmployeeService;
use App\Modules\Personnel\Services\PersonnelPermissionService;
use App\Modules\Personnel\Services\PersonnelReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class EmployeeController
{
    public function index(PersonnelFilterRequest $request, PersonnelReportService $reports)
    {
        $filters = $request->validated();

        return view('personnel::employees.index', [
            'employees' => $reports->employees($filters), 'filters' => $filters,
            'departments' => PersonnelEmployee::query()->whereNotNull('department')->distinct()->orderBy('department')->limit(100)->pluck('department'),
            'permissions' => app(PersonnelPermissionService::class)->forRequest($request),
        ]);
    }

    public function create(Request $request)
    {
        return view('personnel::employees.create', ['employee' => null, 'formDefaults' => ['start_date' => today('Europe/Istanbul')->toDateString()], 'permissions' => app(PersonnelPermissionService::class)->forRequest($request)]);
    }

    public function store(PersonnelRequest $request, EmployeeService $service)
    {
        $employee = $service->create($request->validated(), (int) $request->user()->id);

        return redirect()->route('personnel.employees.show', $employee)->with('success', 'Çalışan eklendi.');
    }

    public function show(PersonnelFilterRequest $request, PersonnelEmployee $employee, AttendanceService $attendance)
    {
        Gate::authorize('view', $employee);
        $month = $request->validated('month') ?? today('Europe/Istanbul')->format('Y-m');
        $last = Carbon::parse($month.'-01')->daysInMonth;
        $attendanceRows = [];
        $context = $attendance->preload([$employee->id], $month.'-01', sprintf('%s-%02d', $month, $last));
        for ($day = 1; $day <= $last; $day++) {
            $date = sprintf('%s-%02d', $month, $day);
            if ($date >= $employee->start_date->toDateString() && ($employee->end_date === null || $date <= $employee->end_date->toDateString())) {
                $attendanceRows[] = $attendance->day($employee, $date, $context);
            }
        }
        $data = [
            'employee' => $employee, 'month' => $month, 'cards' => PersonnelCardAssignment::query()->where('employee_id', $employee->id)->with('card')->orderByDesc('id')->paginate(20, ['*'], 'card_page')->withQueryString(),
            'shiftAssignments' => PersonnelShiftAssignment::query()->where('employee_id', $employee->id)->with('version')->orderByDesc('effective_from')->paginate(20, ['*'], 'shift_page')->withQueryString(),
            'shiftVersions' => PersonnelShiftVersion::query()->whereIn('shift_id', DB::table('personnel_shifts')->where('is_active', true)->select('id'))->with('shift')->orderByDesc('id')->get(),
            'attendanceRows' => $attendanceRows, 'corrections' => PersonnelAttendanceCorrection::query()->where('employee_id', $employee->id)->orderByDesc('created_at')->paginate(20, ['*'], 'correction_page')->withQueryString(),
            'permissions' => app(PersonnelPermissionService::class)->forRequest($request), 'currencies' => Currency::active(),
        ];
        if ($data['permissions']['payroll']) {
            $data['wageAssignments'] = PersonnelWageAssignment::query()->where('employee_id', $employee->id)->orderByDesc('effective_from')->paginate(20, ['*'], 'wage_page')->withQueryString();
            $data['payrollEntries'] = PersonnelPayrollEntry::query()->where('employee_id', $employee->id)->orderByDesc('id')->paginate(20);
            $data['payrollTotals'] = DB::table('personnel_payroll_entries')->where('employee_id', $employee->id)->select('currency', 'direction', DB::raw('SUM(amount) as total'))->groupBy('currency', 'direction')->get();
        }

        return view('personnel::employees.show', $data);
    }

    public function edit(Request $request, PersonnelEmployee $employee)
    {
        Gate::authorize('update', $employee);

        return view('personnel::employees.edit', ['employee' => $employee, 'formDefaults' => [], 'permissions' => app(PersonnelPermissionService::class)->forRequest($request)]);
    }

    public function update(PersonnelRequest $request, PersonnelEmployee $employee, EmployeeService $service)
    {
        Gate::authorize('update', $employee);
        $service->update($employee, $request->validated(), (int) $request->user()->id);

        return redirect()->route('personnel.employees.show', $employee)->with('success', 'Çalışan güncellendi.');
    }

    public function active(PersonnelRequest $request, PersonnelEmployee $employee, EmployeeService $service)
    {
        Gate::authorize('update', $employee);
        $data = $request->validated();
        $service->setActive($employee, (bool) $data['is_active'], $data['reason'] ?? '', (int) $request->user()->id);

        return back()->with('success', 'Çalışan durumu güncellendi.');
    }

    public function card(PersonnelRequest $request, PersonnelEmployee $employee, CardService $cards)
    {
        Gate::authorize('update', $employee);
        $cards->assign($employee, $request->validated(), (int) $request->user()->id);

        return back()->with('success', 'Kart atandı.');
    }

    public function releaseCard(PersonnelRequest $request, PersonnelCard $card, CardService $cards)
    {
        Gate::authorize('update', $card);
        $cards->release($card, $request->validated()['reason'], (int) $request->user()->id);

        return back()->with('success', 'Kart ataması sonlandırıldı.');
    }

    public function cardActive(PersonnelRequest $request, PersonnelCard $card, CardService $cards)
    {
        Gate::authorize('update', $card);
        $cards->setActive($card, (bool) $request->validated()['is_active'], (int) $request->user()->id);

        return back()->with('success', 'Kart durumu güncellendi.');
    }
}
