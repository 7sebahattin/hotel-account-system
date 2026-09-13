<?php

declare(strict_types=1);

namespace App\Modules\AuditLog\Policies;

use App\Models\User;
use App\Modules\AuditLog\Models\AuditLog;
use App\Modules\Authorization\Enums\Permission;

class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::AuditView->value);
    }

    /**
     * Tek kaydın detayı. Liste ile AYNI yetkidir (`audit.view`): listeyi
     * görebilen kullanıcı zaten aynı satırın özetini görüyor — detay yalnızca
     * o satırın eski/yeni değerlerini açar, yeni bir veri kapısı açmaz.
     */
    public function view(User $user, AuditLog $log): bool
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
