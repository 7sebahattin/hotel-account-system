<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Policies;

use App\Models\User;
use App\Modules\Authorization\Enums\Permission;
use App\Modules\Authorization\Models\Role;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::RolesManage->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::RolesManage->value);
    }

    /** Sistem rolünün yetkileri düzenlenebilir; adı RoleService'te korunur. */
    public function update(User $user): bool
    {
        return $user->can(Permission::RolesManage->value);
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->can(Permission::RolesManage->value) && ! $role->isSystem();
    }
}
