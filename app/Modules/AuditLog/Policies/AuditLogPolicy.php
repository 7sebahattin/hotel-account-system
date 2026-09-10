<?php

declare(strict_types=1);

namespace App\Modules\AuditLog\Policies;

use App\Models\User;
use App\Modules\Authorization\Enums\Permission;

class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::AuditView->value);
    }

    /** Denetim kaydı hiç kimse tarafından değiştirilemez (CLAUDE.md §5.6). */
    public function update(User $user): bool
    {
        return false;
    }

    /** Denetim kaydı hiç kimse tarafından silinemez — admin dahil. */
    public function delete(User $user): bool
    {
        return false;
    }
}
