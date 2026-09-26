<?php

declare(strict_types=1);

namespace App\Modules\Personnel\Policies;

use App\Models\User;
use App\Modules\Authorization\Enums\Permission;
use App\Modules\Personnel\Models\PersonnelCard;

class PersonnelCardPolicy
{
    public function update(User $user, PersonnelCard $card): bool
    {
        return $user->can(Permission::PersonnelManage->value);
    }
}
