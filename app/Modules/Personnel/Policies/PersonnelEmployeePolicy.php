<?php

declare(strict_types=1);

namespace App\Modules\Personnel\Policies;

use App\Models\User;
use App\Modules\Authorization\Enums\Permission;
use App\Modules\Personnel\Models\PersonnelEmployee;

class PersonnelEmployeePolicy
{
    public function view(User $user, PersonnelEmployee $employee): bool
    {
        return $user->can(Permission::PersonnelView->value);
    }

    public function update(User $user, PersonnelEmployee $employee): bool
    {
        return $user->can(Permission::PersonnelManage->value);
    }

    public function correct(User $user, PersonnelEmployee $employee): bool
    {
        return $user->can(Permission::PersonnelAttendance->value);
    }

    public function payroll(User $user, PersonnelEmployee $employee): bool
    {
        return $user->can(Permission::PersonnelPayroll->value);
    }
}
