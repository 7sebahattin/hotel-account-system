<?php

declare(strict_types=1);

namespace App\Modules\Personnel\Services;

use App\Modules\Authorization\Enums\Permission;
use Illuminate\Http\Request;

class PersonnelPermissionService
{
    public function forRequest(Request $request): array
    {
        return [
            'view' => $request->user()->can(Permission::PersonnelView->value),
            'manage' => $request->user()->can(Permission::PersonnelManage->value),
            'attendance' => $request->user()->can(Permission::PersonnelAttendance->value),
            'payroll' => $request->user()->can(Permission::PersonnelPayroll->value),
            'scan' => $request->user()->can(Permission::PersonnelScan->value),
            'terminals' => $request->user()->can(Permission::PersonnelTerminals->value),
        ];
    }
}
