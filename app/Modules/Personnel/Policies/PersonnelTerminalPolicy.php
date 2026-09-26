<?php

declare(strict_types=1);

namespace App\Modules\Personnel\Policies;

use App\Models\User;
use App\Modules\Authorization\Enums\Permission;
use App\Modules\Personnel\Models\PersonnelTerminal;

class PersonnelTerminalPolicy
{
    public function revoke(User $user, PersonnelTerminal $terminal): bool
    {
        return $user->can(Permission::PersonnelTerminals->value) && $terminal->revoked_at === null;
    }
}
