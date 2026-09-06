<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Services;

use App\Modules\AuditLog\Enums\AuditEvent;
use App\Modules\AuditLog\Services\AuditLogger;
use App\Modules\Authorization\Enums\Permission as PermissionEnum;
use App\Modules\Authorization\Exceptions\RoleInUseException;
use App\Modules\Authorization\Exceptions\SystemRoleException;
use App\Modules\Authorization\Models\Role;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Rol ve yetki yönetiminin dışa açık tek kapısı (CLAUDE.md §4.3).
 *
 * Rol satırındaki değişiklikler Auditable trait'i ile kaydedilir; yetki
 * atamaları pivot tabloda olduğu için burada ayrıca 'permission_changed'
 * olarak kaydedilir (CLAUDE.md §7.5).
 */
class RoleService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /** @return Collection<int, Role> */
    public function all(): Collection
    {
        return Role::query()->with('permissions')->orderByDesc('is_system')->orderBy('name')->get();
    }

    /**
     * Kullanıcı formunda gösterilecek rol seçenekleri.
     *
     * Model sızdırmaz: Users modülü yalnızca bu diziyi görür.
     *
     * @return array<string, string>
     */
    public function options(): array
    {
        return Role::query()
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Role $role) => [$role->name => $role->label()])
            ->all();
    }

    /**
     * @param  array<int, string>  $permissions
     */
    public function create(string $name, array $permissions): Role
    {
        return DB::transaction(function () use ($name, $permissions): Role {
            $role = Role::create(['name' => $name, 'guard_name' => 'web', 'is_system' => false]);

            $this->applyPermissions($role, $permissions);

            return $role;
        });
    }

    /**
     * @param  array<int, string>  $permissions
     */
    public function update(Role $role, ?string $name, array $permissions): void
    {
        DB::transaction(function () use ($role, $name, $permissions): void {
            if ($name !== null && $name !== $role->name) {
                if ($role->isSystem()) {
                    throw SystemRoleException::cannotRename($role->name);
                }

                $role->update(['name' => $name]);
            }

            $this->applyPermissions($role, $permissions);
        });
    }

    public function delete(Role $role): void
    {
        if ($role->isSystem()) {
            throw SystemRoleException::cannotDelete($role->name);
        }

        $userCount = $role->users()->count();

        if ($userCount > 0) {
            throw RoleInUseException::make($role->name, $userCount);
        }

        $role->delete();
    }

    /**
     * Yetki listesini uygular ve değişiklik varsa denetim kaydı yazar.
     *
     * @param  array<int, string>  $permissions
     */
    private function applyPermissions(Role $role, array $permissions): void
    {
        $allowed = PermissionEnum::values();
        $requested = array_values(array_intersect($permissions, $allowed));

        $before = $role->permissions()->pluck('name')->sort()->values()->all();

        $role->syncPermissions($requested);

        $after = $role->permissions()->pluck('name')->sort()->values()->all();

        if ($before === $after) {
            return;
        }

        $this->auditLogger->log(
            event: AuditEvent::PermissionChanged,
            subject: $role,
            oldValues: ['permissions' => $before],
            newValues: ['permissions' => $after],
        );
    }
}
