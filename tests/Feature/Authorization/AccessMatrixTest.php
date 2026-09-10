<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Modules\Authorization\Enums\RoleName;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Her rol × her kritik route için 200/403 matrisi (CLAUDE.md §12).
 */
class AccessMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    /**
     * @return array<string, array{string, string, int}>
     */
    public static function matrix(): array
    {
        return [
            // Kullanıcı yönetimi: yalnızca Admin
            'admin → kullanıcılar' => ['admin', 'users.index', 200],
            'patron → kullanıcılar' => ['patron', 'users.index', 403],
            'personel → kullanıcılar' => ['personel', 'users.index', 403],

            'admin → yeni kullanıcı' => ['admin', 'users.create', 200],
            'patron → yeni kullanıcı' => ['patron', 'users.create', 403],
            'personel → yeni kullanıcı' => ['personel', 'users.create', 403],

            // Rol yönetimi: yalnızca Admin
            'admin → roller' => ['admin', 'roles.index', 200],
            'patron → roller' => ['patron', 'roles.index', 403],
            'personel → roller' => ['personel', 'roles.index', 403],

            'admin → yeni rol' => ['admin', 'roles.create', 200],
            'patron → yeni rol' => ['patron', 'roles.create', 403],

            // Denetim kaydı: Admin ve Patron okur, Personel göremez
            'admin → denetim kaydı' => ['admin', 'audit.index', 200],
            'patron → denetim kaydı' => ['patron', 'audit.index', 200],
            'personel → denetim kaydı' => ['personel', 'audit.index', 403],

            // Ana sayfa herkese açık (giriş yapmış kullanıcı için)
            'admin → ana sayfa' => ['admin', 'home', 200],
            'patron → ana sayfa' => ['patron', 'home', 200],
            'personel → ana sayfa' => ['personel', 'home', 200],
        ];
    }

    #[Test]
    #[DataProvider('matrix')]
    public function rol_route_erisim_matrisi(string $role, string $route, int $expected): void
    {
        $user = $this->userWithRole(RoleName::from($role));

        $this->actingAs($user)
            ->get(route($route))
            ->assertStatus($expected);
    }

    #[Test]
    public function rolsuz_kullanici_yonetim_ekranlarina_erisemez(): void
    {
        $user = $this->userWithRole(RoleName::Personel);
        $user->syncRoles([]);

        foreach (['users.index', 'roles.index', 'audit.index'] as $route) {
            $this->actingAs($user->fresh())->get(route($route))->assertForbidden();
        }
    }

    #[Test]
    public function misafir_kullanici_tum_korumali_route_lardan_giris_e_yonlendirilir(): void
    {
        foreach (['users.index', 'roles.index', 'audit.index', 'home'] as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }
    }
}
