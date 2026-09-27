<?php

declare(strict_types=1);

namespace App\Modules\Personnel\Policies;

use App\Models\User;
use App\Modules\Authorization\Enums\Permission;
use App\Modules\Personnel\Models\PersonnelShift;

class PersonnelShiftPolicy
{
    public function update(User $user, PersonnelShift $shift): bool
    {
        return $user->can(Permission::PersonnelManage->value);
    }
}
