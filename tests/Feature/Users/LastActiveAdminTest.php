<?php

declare(strict_types=1);

namespace Tests\Feature\Users;

use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Users\Exceptions\LastActiveAdminException;
use App\Modules\Users\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * CLAUDE.md §7.5: son aktif admin pasifleştirilemez, silinemez ve rolü alınamaz.
 * Aksi halde sisteme yönetici erişimi tamamen kaybedilir.
 */
class LastActiveAdminTest extends TestCase
{
    use RefreshDatabase;

    private UserService $users;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->users = app(UserService::class);
    }

    #[Test]
    public function tek_admin_son_aktif_admin_olarak_taninir(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $this->assertTrue($this->users->isLastActiveAdmin($admin));
    }

    #[Test]
    public function ikinci_aktif_admin_varken_son_admin_kisiti_kalkar(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $this->userWithRole(RoleName::Admin);

        $this->assertFalse($this->users->isLastActiveAdmin($admin));
    }

    #[Test]
    public function pasif_admin_aktif_admin_sayilmaz(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $this->userWithRole(RoleName::Admin, ['is_active' => false]);

        $this->assertTrue(
            $this->users->isLastActiveAdmin($admin),
            'Pasif admin, son aktif admin kısıtını kaldırmamalı',
        );
    }

    #[Test]
    public function silinmis_admin_aktif_admin_sayilmaz(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $ikinci = $this->userWithRole(RoleName::Admin);
        $ikinci->delete();

        $this->assertTrue($this->users->isLastActiveAdmin($admin->fresh()));
    }

    #[Test]
    public function son_aktif_admin_pasiflestirilemez(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $this->expectException(LastActiveAdminException::class);

        $this->users->setActive($admin, false);
    }

    #[Test]
    public function son_aktif_admin_silinemez(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        try {
            $this->users->delete($admin);
            $this->fail('LastActiveAdminException bekleniyordu');
        } catch (LastActiveAdminException) {
            $this->assertNotSoftDeleted('users', ['id' => $admin->id]);
        }
    }

    #[Test]
    public function son_aktif_admin_in_rolu_degistirilemez(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $this->expectException(LastActiveAdminException::class);

        $this->users->assignRole($admin, RoleName::Personel->value);
    }

    #[Test]
    public function son_aktif_admin_http_uzerinden_de_korunur(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $baska = $this->userWithRole(RoleName::Personel);

        // Başka bir kullanıcı üzerinden, son admini pasifleştirme denemesi.
        $this->actingAs($admin)->put(route('users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => RoleName::Personel->value,
            'is_active' => '1',
        ])->assertSessionHas('error');

        $this->assertTrue($admin->fresh()->hasRole(RoleName::Admin->value));
        $this->assertTrue($baska->fresh()->exists);
    }

    #[Test]
    public function ikinci_admin_atandiktan_sonra_ilk_admin_serbest_kalir(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $ikinci = $this->userWithRole(RoleName::Personel);

        $this->users->assignRole($ikinci, RoleName::Admin->value);
        $this->users->setActive($admin->fresh(), false);

        $this->assertFalse($admin->fresh()->is_active);
    }
}
