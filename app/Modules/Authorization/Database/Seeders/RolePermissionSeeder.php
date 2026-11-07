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
 * Yeniden çalıştırılabilir ve YIKICI DEĞİLDİR: mevcut rollerin yetkileri korunur
 * (Roller / Yetkiler ekranındaki değişiklikler deploy ile sıfırlanmaz). Varsayılan
 * matris yalnızca ilk kurulumda ve kodla yeni eklenen yetkiler için uygulanır.
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

        // Yeni oluşturulan yetkiler: yalnızca bu çalıştırmada ilk kez doğan yetki
        // varsayılan matristeki rollere verilir. Var olan yetkiler dokunulmaz.
        $created = [];
        foreach (PermissionEnum::values() as $name) {
            $permission = Permission::findOrCreate($name, 'web');

            if ($permission->wasRecentlyCreated) {
                $created[] = $name;
            }
        }

        // Yeni oluşturulan yetkiler spatie'nin önbelleğine yansısın; aksi halde
        // givePermissionTo() henüz görmediği yetki için PermissionDoesNotExist atar.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (RoleName::cases() as $roleName) {
            $role = Role::query()->firstOrCreate(
                ['name' => $roleName->value, 'guard_name' => 'web'],
                ['is_system' => true],
            );

            $role->forceFill(['is_system' => true])->save();

            $defaults = array_map(fn (PermissionEnum $p) => $p->value, $roleName->permissions());

            if ($role->wasRecentlyCreated) {
                // Rol ilk kez kuruluyor: varsayılan matris uygulanır.
                $role->syncPermissions($defaults);

                continue;
            }

            // Mevcut rolün yetkileri "Roller / Yetkiler" ekranında yönetilir; her
            // deploy'da varsayılana DÖNDÜRÜLMEZ. Yalnızca yeni doğan yetki eklenir.
            $newForRole = array_values(array_intersect($defaults, $created));

            if ($newForRole !== []) {
                $role->givePermissionTo($newForRole);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
