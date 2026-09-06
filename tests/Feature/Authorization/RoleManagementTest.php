<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Modules\AuditLog\Enums\AuditEvent;
use App\Modules\AuditLog\Models\AuditLog;
use App\Modules\Authorization\Enums\Permission;
use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Authorization\Exceptions\RoleInUseException;
use App\Modules\Authorization\Exceptions\SystemRoleException;
use App\Modules\Authorization\Models\Role;
use App\Modules\Authorization\Services\RoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    private RoleService $roles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->roles = app(RoleService::class);
    }

    #[Test]
    public function admin_ozel_rol_olusturabilir(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $this->actingAs($admin)->post(route('roles.store'), [
            'name' => 'muhasebe',
            'permissions' => [Permission::LedgerView->value, Permission::ExportExcel->value],
        ])->assertRedirect(route('roles.index'));

        $role = Role::findByName('muhasebe');

        $this->assertFalse($role->isSystem());
        $this->assertEqualsCanonicalizing(
            [Permission::LedgerView->value, Permission::ExportExcel->value],
            $role->permissions->pluck('name')->all(),
        );
    }

    #[Test]
    public function sistem_rolu_silinemez(): void
    {
        $role = Role::findByName(RoleName::Admin->value);

        $this->expectException(SystemRoleException::class);

        $this->roles->delete($role);
    }

    #[Test]
    public function sistem_rolu_http_uzerinden_de_silinemez(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $role = Role::findByName(RoleName::Personel->value);

        $this->actingAs($admin)
            ->delete(route('roles.destroy', $role))
            ->assertForbidden();

        $this->assertDatabaseHas('roles', ['name' => RoleName::Personel->value]);
    }

    #[Test]
    public function sistem_rolunun_adi_degistirilemez(): void
    {
        $role = Role::findByName(RoleName::Patron->value);

        $this->expectException(SystemRoleException::class);

        $this->roles->update($role, 'yeni-ad', []);
    }

    #[Test]
    public function sistem_rolunun_yetkileri_duzenlenebilir(): void
    {
        $role = Role::findByName(RoleName::Personel->value);

        $this->roles->update($role, $role->name, [Permission::CompaniesView->value]);

        $this->assertSame(
            [Permission::CompaniesView->value],
            $role->fresh()->permissions->pluck('name')->all(),
        );
    }

    #[Test]
    public function kullaniciya_atanmis_rol_silinemez(): void
    {
        $role = $this->roles->create('gecici', []);
        $user = $this->userWithRole(RoleName::Personel);
        $user->assignRole('gecici');

        $this->expectException(RoleInUseException::class);

        $this->roles->delete($role->fresh());
    }

    #[Test]
    public function tanimsiz_yetki_kabul_edilmez(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $this->actingAs($admin)->post(route('roles.store'), [
            'name' => 'sahte',
            'permissions' => ['uydurma.yetki'],
        ])->assertSessionHasErrors('permissions.0');

        $this->assertNull(Role::query()->where('name', 'sahte')->first());
    }

    #[Test]
    public function yetki_degisikligi_denetim_kaydina_yazilir(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $role = Role::findByName(RoleName::Personel->value);
        AuditLog::query()->delete();

        $this->actingAs($admin)->put(route('roles.update', $role), [
            'name' => $role->name,
            'permissions' => [Permission::CompaniesView->value],
        ])->assertRedirect(route('roles.index'));

        $log = AuditLog::query()
            ->where('event', AuditEvent::PermissionChanged->value)
            ->where('auditable_type', 'role')
            ->firstOrFail();

        $this->assertContains(Permission::StaysCreate->value, $log->old_values['permissions']);
        $this->assertSame([Permission::CompaniesView->value], $log->new_values['permissions']);
        $this->assertSame($admin->id, $log->user_id);
    }

    #[Test]
    public function yetki_degismediyse_denetim_kaydi_acilmaz(): void
    {
        $role = Role::findByName(RoleName::Personel->value);
        $mevcut = $role->permissions->pluck('name')->all();
        AuditLog::query()->delete();

        $this->roles->update($role, $role->name, $mevcut);

        $this->assertSame(0, AuditLog::query()->where('event', AuditEvent::PermissionChanged->value)->count());
    }
}
