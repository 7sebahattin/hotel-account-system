<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use App\Modules\Authorization\Enums\Permission;
use App\Modules\Authorization\Enums\RoleName;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Üretimde varsayılan yönetici kimlik bilgisiyle seed edilmesi engellenir.
 *
 * Bu, `db:seed --force` canlıda elle çalıştırıldığında son savunma hattıdır:
 * .env eksik doldurulmuş olsa bile herkesin bildiği bir parolayla admin hesabı açılmaz.
 */
class ProductionSeedGuardTest extends TestCase
{
    use RefreshDatabase;

    private const DEV_EMAIL = 'admin@example.com';

    private const DEV_PASSWORD = 'degistir-beni';

    private const STRONG_PASSWORD = 'k9!Qz2vR7mTx-4Lw#pB6';

    /** Seeder'ı üretim ortamındaymış gibi çalıştırır. */
    private function seedAs(string $environment, ?string $email, ?string $password): void
    {
        config([
            'hotel.seed.admin_email' => $email,
            'hotel.seed.admin_name' => 'Sistem Yöneticisi',
            'hotel.seed.admin_password' => $password,
        ]);

        $this->app->detectEnvironment(fn (): string => $environment);

        // `$this->seed()` db:seed KOMUTUNU çalıştırır ve komut, local dışındaki
        // ortamlarda onay sorar (ConfirmableTrait). Burada sınanan şey seeder'ın
        // kendisi olduğu için seeder doğrudan çağrılır.
        $this->app->make(DatabaseSeeder::class)->setContainer($this->app)();
    }

    #[Test]
    public function production_parolasiz_seed_edilemez(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SEED_ADMIN_PASSWORD tanımlı değil');

        $this->seedAs('production', 'patron@lemon.example', null);
    }

    #[Test]
    public function production_bos_parolayla_seed_edilemez(): void
    {
        $this->expectException(RuntimeException::class);

        $this->seedAs('production', 'patron@lemon.example', '');
    }

    #[Test]
    public function production_varsayilan_parolayla_seed_edilemez(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('geliştirme varsayılanı ile aynı');

        $this->seedAs('production', 'patron@lemon.example', self::DEV_PASSWORD);
    }

    #[Test]
    public function production_eposta_olmadan_seed_edilemez(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SEED_ADMIN_EMAIL tanımlı değil');

        $this->seedAs('production', null, self::STRONG_PASSWORD);
    }

    #[Test]
    public function production_varsayilan_eposta_ile_seed_edilemez(): void
    {
        $this->expectException(RuntimeException::class);

        $this->seedAs('production', self::DEV_EMAIL, self::STRONG_PASSWORD);
    }

    #[Test]
    public function guard_engellediginde_hicbir_kullanici_yazilmaz(): void
    {
        try {
            $this->seedAs('production', null, null);
            $this->fail('Guard fırlatmalıydı.');
        } catch (RuntimeException) {
            // beklenen
        }

        $this->assertSame(0, User::query()->count(), 'Guard tetiklendiğinde kullanıcı oluşmamalı.');
    }

    #[Test]
    public function production_disindaki_ortamlar_da_korunur(): void
    {
        // local/testing dışındaki her ortam üretim sayılır — staging dahil.
        $this->expectException(RuntimeException::class);

        $this->seedAs('staging', self::DEV_EMAIL, self::DEV_PASSWORD);
    }

    #[Test]
    public function production_gercek_kimlik_bilgisiyle_tek_admin_olusturur(): void
    {
        $this->seedAs('production', 'patron@lemon.example', self::STRONG_PASSWORD);

        $this->assertSame(1, User::query()->count(), 'Üretimde yalnızca tek admin oluşmalı.');

        $admin = User::query()->firstOrFail();
        $this->assertSame('patron@lemon.example', $admin->email);
        $this->assertTrue($admin->hasRole(RoleName::Admin->value));
        $this->assertTrue($admin->is_active);

        // Parola hash'lenerek yazılır; düz metin saklanmaz.
        $this->assertNotSame(self::STRONG_PASSWORD, $admin->password);
        $this->assertTrue(Hash::check(self::STRONG_PASSWORD, $admin->password));
    }

    #[Test]
    public function production_demo_hesaplari_olusturmaz(): void
    {
        $this->seedAs('production', 'patron@lemon.example', self::STRONG_PASSWORD);

        $this->assertDatabaseMissing('users', ['email' => 'patron@example.com']);
        $this->assertDatabaseMissing('users', ['email' => 'personel@example.com']);
        $this->assertDatabaseMissing('users', ['email' => self::DEV_EMAIL]);
    }

    #[Test]
    public function production_seedi_rol_ve_yetki_matrisini_kurar(): void
    {
        $this->seedAs('production', 'patron@lemon.example', self::STRONG_PASSWORD);

        $this->assertDatabaseCount('roles', count(RoleName::cases()));
        $this->assertDatabaseCount('permissions', count(Permission::values()));
    }

    #[Test]
    public function local_ortamda_varsayilanlar_calismaya_devam_eder(): void
    {
        // Geliştirici deneyimi bozulmamalı: `migrate:fresh --seed` .env olmadan da çalışır.
        $this->seedAs('local', null, null);

        $this->assertDatabaseHas('users', ['email' => self::DEV_EMAIL]);
        $this->assertDatabaseHas('users', ['email' => 'patron@example.com']);
        $this->assertDatabaseHas('users', ['email' => 'personel@example.com']);

        $admin = User::query()->where('email', self::DEV_EMAIL)->firstOrFail();
        $this->assertTrue(Hash::check(self::DEV_PASSWORD, $admin->password));
    }

    #[Test]
    public function local_ortamda_verilen_kimlik_bilgisi_varsayilani_ezer(): void
    {
        $this->seedAs('local', 'gelistirici@ornek.test', self::STRONG_PASSWORD);

        $this->assertDatabaseHas('users', ['email' => 'gelistirici@ornek.test']);
        $this->assertDatabaseMissing('users', ['email' => self::DEV_EMAIL]);
    }
}
