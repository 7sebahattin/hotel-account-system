<?php

declare(strict_types=1);

namespace App\Modules\Users\Policies;

use App\Models\User;
use App\Modules\Authorization\Enums\Permission;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::UsersView->value);
    }

    public function view(User $user): bool
    {
        return $user->can(Permission::UsersView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::UsersManage->value);
    }

    public function update(User $user): bool
    {
        return $user->can(Permission::UsersManage->value);
    }

    /** Kullanıcı kendini silemez; son aktif admin koruması UserService'te. */
    public function delete(User $user, User $target): bool
    {
        return $user->can(Permission::UsersManage->value)
            && $user->isNot($target);
    }
}
