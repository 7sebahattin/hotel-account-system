<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Authorization\Database\Seeders\RolePermissionSeeder;
use App\Modules\Authorization\Enums\RoleName;
use App\Modules\CashBook\Database\Seeders\CashMovementCategorySeeder;
use App\Modules\Users\Services\UserService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Geliştirme kolaylığı için kullanılan varsayılanlar.
     *
     * Bunlar YALNIZCA local ve testing ortamında uygulanır. Üretimde ne varsayılan
     * olarak atanır ne de elle girilmesine izin verilir: `guardProductionCredentials()`
     * bu değerleri gördüğünde seeder'ı durdurur. Herkesin bildiği bir parolayla açılan
     * bir admin hesabı, hiç admin olmamasından kötüdür.
     */
    private const DEV_ADMIN_EMAIL = 'admin@example.com';

    private const DEV_ADMIN_PASSWORD = 'degistir-beni';

    public function run(): void
    {
        // Yetkiler ve rol matrisi (CLAUDE.md §7.2 / §7.3)
        $this->call(RolePermissionSeeder::class);

        // Varsayılan kasa hareket türleri (v2.1.1-B §4) — idempotent.
        $this->call(CashMovementCategorySeeder::class);

        $email = trim((string) config('hotel.seed.admin_email'));
        $name = trim((string) config('hotel.seed.admin_name'));
        $password = (string) config('hotel.seed.admin_password');

        // Örnek Patron/Personel hesapları ve geliştirme varsayılanları YALNIZCA
        // local ve testing ortamında geçerlidir. Bunun dışındaki her ortam
        // (production, staging) üretim sayılır ve gerçek kimlik bilgisi ister.
        $isDevelopment = app()->environment(['local', 'testing']);

        if ($isDevelopment) {
            $email = $email !== '' ? $email : self::DEV_ADMIN_EMAIL;
            $password = $password !== '' ? $password : self::DEV_ADMIN_PASSWORD;
        } else {
            $this->guardProductionCredentials($email, $password);
        }

        $users = app(UserService::class);

        $this->ensureUser(
            $users,
            $email,
            $name !== '' ? $name : 'Sistem Yöneticisi',
            $password,
            RoleName::Admin,
        );

        // Staging ve production'da varsayılan şifreli demo hesap bırakmak açık kapıdır.
        if (! $isDevelopment) {
            return;
        }

        $this->ensureUser($users, 'patron@example.com', 'Patron', 'degistir-beni', RoleName::Patron);
        $this->ensureUser($users, 'personel@example.com', 'Personel', 'degistir-beni', RoleName::Personel);
    }

    /**
     * Üretimde varsayılan ya da eksik yönetici kimlik bilgisiyle seed edilmesini engeller.
     *
     * Fail-closed: hiçbir kullanıcı yazılmadan, RolePermissionSeeder'dan hemen sonra
     * çalışır. Bir istisna fırlatıldığında yarım kalan tek şey rol/yetki tablolarıdır;
     * onları yeniden çalıştırmak güvenlidir (seeder idempotenttir).
     *
     * @throws RuntimeException
     */
    private function guardProductionCredentials(string $email, string $password): void
    {
        if ($password === '') {
            throw new RuntimeException(
                'SEED_ADMIN_PASSWORD tanımlı değil. Üretimde yönetici hesabı parolasız '
                .'veya varsayılan parolayla oluşturulamaz. .env dosyasına güçlü bir parola '
                .'girin (ör. `openssl rand -base64 24`) ve seeder\'ı tekrar çalıştırın.'
            );
        }

        if ($password === self::DEV_ADMIN_PASSWORD) {
            throw new RuntimeException(
                'SEED_ADMIN_PASSWORD geliştirme varsayılanı ile aynı. Bu parola depoda '
                .'açıkça yazılıdır ve üretimde kullanılamaz. .env dosyasına gerçek bir '
                .'parola girin (ör. `openssl rand -base64 24`).'
            );
        }

        if ($email === '') {
            throw new RuntimeException(
                'SEED_ADMIN_EMAIL tanımlı değil. Üretimde yönetici hesabının e-posta '
                .'adresi .env dosyasında açıkça belirtilmelidir.'
            );
        }

        if ($email === self::DEV_ADMIN_EMAIL) {
            throw new RuntimeException(
                'SEED_ADMIN_EMAIL geliştirme varsayılanı ile aynı. Üretimde gerçek bir '
                .'e-posta adresi kullanılmalıdır; giriş kimliği budur ve V1\'de şifre '
                .'sıfırlama akışı yoktur.'
            );
        }
    }

    private function ensureUser(
        UserService $users,
        string $email,
        string $name,
        string $password,
        RoleName $role,
    ): void {
        $existing = User::query()->where('email', $email)->first();

        if ($existing !== null) {
            $users->assignRole($existing, $role->value);

            return;
        }

        $users->create(
            ['name' => $name, 'email' => $email, 'password' => $password, 'is_active' => true],
            $role->value,
        );
    }
}
