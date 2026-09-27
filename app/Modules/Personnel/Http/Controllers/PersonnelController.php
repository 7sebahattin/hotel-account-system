<?php

declare(strict_types=1);

namespace App\Modules\Personnel\Http\Controllers;

use App\Modules\Authorization\Enums\Permission;
use App\Modules\Personnel\Http\Requests\PersonnelFilterRequest;
use App\Modules\Personnel\Services\PersonnelPermissionService;
use App\Modules\Personnel\Services\PersonnelReportService;
use Illuminate\Http\Request;

class PersonnelController
{
    public function index(PersonnelFilterRequest $request, PersonnelReportService $reports)
    {
        $date = $request->validated('date') ?? today('Europe/Istanbul')->toDateString();

        return view('personnel::index', $reports->dashboardSnapshot($date, $request->user()->can(Permission::PersonnelPayroll->value)) + ['permissions' => app(PersonnelPermissionService::class)->forRequest($request)]);
    }

    public function kiosk(Request $request)
    {
        return response()->view('personnel::kiosk', [
            'scanUrl' => route('personnel.kiosk.scan'), 'csrfToken' => csrf_token(),
            'defaultAction' => 'auto', 'permissions' => app(PersonnelPermissionService::class)->forRequest($request),
        ])->header('Cache-Control', 'no-store');
    }
}
