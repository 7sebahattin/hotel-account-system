<?php

declare(strict_types=1);

namespace App\Modules\CariCleanup\Policies;

use App\Models\User;
use App\Modules\Authorization\Enums\RoleName;

class CariCleanupPolicy
{
    public function view(User $user): bool
    {
        return $user->is_active && $user->hasRole(RoleName::Admin->value);
    }

    public function execute(User $user): bool
    {
        return $this->view($user);
    }
}
