<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Modules\Authorization\Database\Seeders\RolePermissionSeeder;
use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Authorization\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Her deploy'da koşan seeder, Roller / Yetkiler ekranındaki değişiklikleri sıfırlamaz.
 */
class SeederPreservesRoleChangesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    #[Test]
    public function reseeding_keeps_manual_role_permission_changes(): void
    {
        $personel = Role::query()->where('name', RoleName::Personel->value)->firstOrFail();
        $personel->givePermissionTo('companies.delete');
        $personel->revokePermissionTo('payments.create');

        $this->seed(RolePermissionSeeder::class);

        $personel = $personel->fresh();
        $this->assertTrue($personel->hasPermissionTo('companies.delete'));
        $this->assertFalse($personel->hasPermissionTo('payments.create'));
    }

    #[Test]
    public function newly_added_permission_is_given_only_to_default_roles(): void
    {
        $admin = Role::query()->where('name', RoleName::Admin->value)->firstOrFail();
        $patron = Role::query()->where('name', RoleName::Patron->value)->firstOrFail();

        // Kodla yeni eklenmiş (henüz DB'de olmayan) bir yetkiyi taklit et.
        Permission::query()->where('name', 'offers.update')->firstOrFail()->delete();
        $patron->revokePermissionTo('offers.view');

        $this->seed(RolePermissionSeeder::class);

        $this->assertTrue($admin->fresh()->hasPermissionTo('offers.update'));
        $this->assertTrue($patron->fresh()->hasPermissionTo('offers.update'));
        $this->assertFalse($patron->fresh()->hasPermissionTo('offers.view'));
        $this->assertFalse(
            Role::query()->where('name', RoleName::Personel->value)->firstOrFail()->hasPermissionTo('offers.update')
        );
    }
}
