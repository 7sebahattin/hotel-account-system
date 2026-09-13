<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Database\Seeders;

use App\Modules\Authorization\Enums\Permission as PermissionEnum;
use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Authorization\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * CLAUDE.md §7.2 yetkilerini ve §7.3 rol matrisini kurar.
 *
 * Yeniden çalıştırılabilir: mevcut rol/yetkiler korunur, matris yeniden senkronlanır.
 * Kullanıcıların rol atamaları bu seeder'dan etkilenmez.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Kaldırılan modülün sistem, özel rol ve doğrudan kullanıcı atamalarını temizler.
        // Spatie model olayları pivotları ve yetki önbelleğini birlikte temizler.
        Permission::query()->where('guard_name', 'web')->whereIn('name', [
            'cashbook.view', 'cashbook.operate', 'cashbook.view_all', 'cashbook.manage_categories',
        ])->get()->each(fn (Permission $permission) => $permission->delete());

        foreach (PermissionEnum::values() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        // Yeni oluşturulan yetkiler spatie'nin önbelleğine yansısın; aksi halde
        // syncPermissions() henüz görmediği yetki için PermissionDoesNotExist atar.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (RoleName::cases() as $roleName) {
            $role = Role::query()->firstOrCreate(
                ['name' => $roleName->value, 'guard_name' => 'web'],
                ['is_system' => true],
            );

            $role->forceFill(['is_system' => true])->save();

            $role->syncPermissions(
                array_map(fn (PermissionEnum $p) => $p->value, $roleName->permissions())
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
