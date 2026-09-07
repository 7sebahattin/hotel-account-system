<?php

declare(strict_types=1);

namespace Tests\Feature\Users;

use App\Models\User;
use App\Modules\AuditLog\Enums\AuditEvent;
use App\Modules\AuditLog\Models\AuditLog;
use App\Modules\AuditLog\Services\AuditLogger;
use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Users\Exceptions\SelfActionException;
use App\Modules\Users\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        $this->admin = $this->userWithRole(RoleName::Admin, ['name' => 'Birinci Yönetici']);
        // Son aktif admin korumasının testleri etkilememesi için ikinci bir admin.
        $this->userWithRole(RoleName::Admin, ['name' => 'İkinci Yönetici']);
    }

    #[Test]
    public function admin_kullanici_olusturur_ve_rol_atar(): void
    {
        $this->actingAs($this->admin)->post(route('users.store'), [
            'name' => 'Ayşe Personel',
            'email' => 'ayse@example.com',
            'password' => 'guclu-sifre-1',
            'password_confirmation' => 'guclu-sifre-1',
            'role' => RoleName::Personel->value,
            'is_active' => '1',
        ])->assertRedirect(route('users.index'));

        $created = User::query()->where('email', 'ayse@example.com')->firstOrFail();

        $this->assertTrue($created->hasRole(RoleName::Personel->value));
        $this->assertTrue($created->is_active);
        $this->assertSame($this->admin->id, $created->created_by);
        $this->assertTrue(Hash::check('guclu-sifre-1', $created->password));
    }

    #[Test]
    public function kullanici_guncellenirken_bos_sifre_mevcut_sifreyi_degistirmez(): void
    {
        $target = $this->userWithRole(RoleName::Personel, ['password' => 'eski-sifre-1']);

        $this->actingAs($this->admin)->put(route('users.update', $target), [
            'name' => 'Yeni Ad',
            'email' => $target->email,
            'password' => '',
            'password_confirmation' => '',
            'role' => RoleName::Personel->value,
            'is_active' => '1',
        ])->assertRedirect(route('users.index'));

        $target->refresh();

        $this->assertSame('Yeni Ad', $target->name);
        $this->assertTrue(Hash::check('eski-sifre-1', $target->password));
    }

    #[Test]
    public function kullanici_aktif_pasif_yapilabilir(): void
    {
        $target = $this->userWithRole(RoleName::Personel);

        $this->actingAs($this->admin)
            ->patch(route('users.toggle-active', $target))
            ->assertRedirect();

        $this->assertFalse($target->fresh()->is_active);

        $this->actingAs($this->admin)->patch(route('users.toggle-active', $target));

        $this->assertTrue($target->fresh()->is_active);
    }

    #[Test]
    public function pasif_kullanici_silinebilir_ve_soft_delete_uygulanir(): void
    {
        $target = $this->userWithRole(RoleName::Personel);

        $this->actingAs($this->admin)
            ->delete(route('users.destroy', $target))
            ->assertRedirect(route('users.index'));

        $this->assertSoftDeleted('users', ['id' => $target->id]);
    }

    #[Test]
    public function kullanici_kendini_silemez(): void
    {
        // Policy 403 verir (Gate::before model argumanli yeteneklerde kisa devre
        // yapmaz) ve servis katmani ayrica ikinci savunma olarak korur.
        $this->actingAs($this->admin)
            ->delete(route('users.destroy', $this->admin))
            ->assertForbidden();

        $this->assertNotSoftDeleted('users', ['id' => $this->admin->id]);

        // Ikinci savunma: servis katmani da reddeder.
        $this->expectException(SelfActionException::class);
        app(UserService::class)->delete($this->admin, $this->admin);
    }

    #[Test]
    public function kullanici_kendini_pasiflestiremez(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('users.toggle-active', $this->admin))
            ->assertSessionHas('error');

        $this->assertTrue($this->admin->fresh()->is_active);
    }

    #[Test]
    public function admin_olmayan_kullanici_baskasini_silemez(): void
    {
        $patron = $this->userWithRole(RoleName::Patron);
        $target = $this->userWithRole(RoleName::Personel);

        $this->actingAs($patron)
            ->delete(route('users.destroy', $target))
            ->assertForbidden();

        $this->assertNotSoftDeleted('users', ['id' => $target->id]);
    }

    #[Test]
    public function patron_kullanici_olusturamaz(): void
    {
        $patron = $this->userWithRole(RoleName::Patron);

        $this->actingAs($patron)->post(route('users.store'), [
            'name' => 'İzinsiz',
            'email' => 'izinsiz@example.com',
            'password' => 'guclu-sifre-1',
            'password_confirmation' => 'guclu-sifre-1',
            'role' => RoleName::Personel->value,
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'izinsiz@example.com']);
    }

    #[Test]
    public function rol_degisikligi_denetim_kaydina_yazilir(): void
    {
        $target = $this->userWithRole(RoleName::Personel);
        AuditLog::query()->delete();

        $this->actingAs($this->admin)->put(route('users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'role' => RoleName::Patron->value,
            'is_active' => '1',
        ]);

        $log = AuditLog::query()
            ->where('event', AuditEvent::PermissionChanged->value)
            ->where('auditable_id', $target->id)
            ->firstOrFail();

        $this->assertSame(['roles' => ['personel']], $log->old_values);
        $this->assertSame(['roles' => ['patron']], $log->new_values);
        $this->assertSame($this->admin->id, $log->user_id);
    }

    #[Test]
    public function denetim_kaydinda_sifre_asla_yer_almaz(): void
    {
        AuditLog::query()->delete();

        $this->actingAs($this->admin)->post(route('users.store'), [
            'name' => 'Gizli',
            'email' => 'gizli@example.com',
            'password' => 'cok-gizli-sifre-1',
            'password_confirmation' => 'cok-gizli-sifre-1',
            'role' => RoleName::Personel->value,
            'is_active' => '1',
        ]);

        $logs = AuditLog::query()->get();

        $this->assertNotEmpty($logs);

        foreach ($logs as $log) {
            $encoded = json_encode([$log->old_values, $log->new_values], JSON_THROW_ON_ERROR);

            $this->assertStringNotContainsString('cok-gizli-sifre-1', $encoded);

            // Anahtar korunur (şifre değişti bilgisi görünür), değer maskelenir.
            foreach (['password', 'remember_token'] as $hassas) {
                if (array_key_exists($hassas, $log->new_values ?? [])) {
                    $this->assertSame(AuditLogger::MASK, $log->new_values[$hassas]);
                }
            }
        }
    }
}
