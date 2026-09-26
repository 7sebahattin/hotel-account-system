<?php

declare(strict_types=1);

namespace App\Modules\Personnel\Http\Controllers;

use App\Modules\Personnel\Http\Requests\PersonnelRequest;
use App\Modules\Personnel\Models\PersonnelEmployee;
use App\Modules\Personnel\Models\PersonnelShift;
use App\Modules\Personnel\Models\PersonnelShiftVersion;
use App\Modules\Personnel\Services\PersonnelPermissionService;
use App\Modules\Personnel\Services\ScheduleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ShiftController
{
    public function index(Request $request)
    {
        $shifts = PersonnelShift::query()->orderBy('name')->paginate(25);
        $versions = PersonnelShiftVersion::query()->whereIn('shift_id', $shifts->pluck('id'))->orderByDesc('version')->get()->groupBy('shift_id');

        return view('personnel::shifts.index', [
            'shifts' => $shifts, 'versionsByShift' => $versions,
            'workdayOptions' => [1 => 'Pazartesi', 2 => 'Salı', 3 => 'Çarşamba', 4 => 'Perşembe', 5 => 'Cuma', 6 => 'Cumartesi', 7 => 'Pazar'],
            'permissions' => app(PersonnelPermissionService::class)->forRequest($request),
        ]);
    }

    public function store(PersonnelRequest $request, ScheduleService $service)
    {
        $service->createShift($request->validated(), (int) $request->user()->id);

        return back()->with('success', 'Vardiya oluşturuldu.');
    }

    public function version(PersonnelRequest $request, PersonnelShift $shift, ScheduleService $service)
    {
        Gate::authorize('update', $shift);
        $service->createVersion($shift, $request->validated(), (int) $request->user()->id);

        return back()->with('success', 'Vardiya sürümü oluşturuldu.');
    }

    public function active(PersonnelRequest $request, PersonnelShift $shift, ScheduleService $service)
    {
        Gate::authorize('update', $shift);
        $service->setShiftActive($shift, (bool) $request->validated()['is_active'], (int) $request->user()->id);

        return back()->with('success', 'Vardiya durumu güncellendi.');
    }

    public function assign(PersonnelRequest $request, PersonnelEmployee $employee, ScheduleService $service)
    {
        Gate::authorize('update', $employee);
        $data = $request->validated();
        $service->assignShift($employee, (int) $data['shift_version_id'], $data['effective_from'], (int) $request->user()->id);

        return back()->with('success', 'Vardiya atandı.');
    }
}
