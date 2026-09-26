<?php

declare(strict_types=1);

namespace App\Modules\Personnel\Http\Controllers;

use App\Modules\Personnel\Http\Requests\PersonnelFilterRequest;
use App\Modules\Personnel\Http\Requests\PersonnelRequest;
use App\Modules\Personnel\Models\PersonnelEmployee;
use App\Modules\Personnel\Models\PersonnelPayrollEntry;
use App\Modules\Personnel\Services\PayrollService;
use App\Modules\Personnel\Services\PersonnelPermissionService;
use App\Modules\Personnel\Services\PersonnelReportService;
use App\Modules\Personnel\Services\ScheduleService;
use Illuminate\Support\Facades\Gate;

class PayrollController
{
    public function index(PersonnelFilterRequest $request, PersonnelReportService $reports, PayrollService $payroll)
    {
        $month = $request->validated('month') ?? today('Europe/Istanbul')->format('Y-m');
        $filters = $request->validated();
        $selectedEmployee = empty($filters['employee_id']) ? null : PersonnelEmployee::query()->find($filters['employee_id']);
        $entries = PersonnelPayrollEntry::query()->where('employee_id', $selectedEmployee === null ? 0 : $selectedEmployee->id)->where('period', $month);
        if (! empty($filters['currency'])) {
            $entries->where('currency', $filters['currency']);
        }
        $entries->withExists('reversal');
        $data = $reports->payroll($month, $filters) + [
            'selectedEmployee' => $selectedEmployee,
            'entries' => $entries->orderByDesc('id')->paginate(20),
            'preview' => $selectedEmployee === null ? null : $payroll->preview($selectedEmployee, $month),
            'permissions' => app(PersonnelPermissionService::class)->forRequest($request),
        ];

        return view('personnel::payroll.index', $data);
    }

    public function wage(PersonnelRequest $request, PersonnelEmployee $employee, ScheduleService $schedule)
    {
        Gate::authorize('payroll', $employee);
        $schedule->assignWage($employee, $request->validated(), (int) $request->user()->id);

        return back()->with('success', 'Ücret sürümü atandı.');
    }

    public function calculate(PersonnelRequest $request, PersonnelEmployee $employee, PayrollService $payroll)
    {
        Gate::authorize('payroll', $employee);
        $data = $request->validated();
        $payroll->calculate($employee, $data['month'], $data['input_digest'], (int) $request->user()->id, $data['request_id']);

        return back()->with('success', 'Hakediş kaydedildi.');
    }

    public function entry(PersonnelRequest $request, PersonnelEmployee $employee, PayrollService $payroll)
    {
        Gate::authorize('payroll', $employee);
        $payroll->addEntry($employee, $request->validated(), (int) $request->user()->id);

        return back()->with('success', 'Hareket kaydedildi.');
    }

    public function reverse(PersonnelRequest $request, PersonnelPayrollEntry $entry, PayrollService $payroll)
    {
        Gate::authorize('reverse', $entry);
        $data = $request->validated();
        $payroll->reverse($entry, $data['reason'], $data['request_id'], (int) $request->user()->id);

        return back()->with('success', 'Ters hareket kaydedildi.');
    }
}
