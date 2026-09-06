<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Authorization\Database\Seeders\RolePermissionSeeder;
use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Users\Services\UserService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Yetkiler ve rol matrisi (CLAUDE.md §7.2 / §7.3)
        $this->call(RolePermissionSeeder::class);

        $users = app(UserService::class);

        // İlk yönetici hesabı. Canlıda şifre .env üzerinden verilmelidir.
        $this->ensureUser(
            $users,
            (string) env('SEED_ADMIN_EMAIL', 'admin@example.com'),
            (string) env('SEED_ADMIN_NAME', 'Sistem Yöneticisi'),
            (string) env('SEED_ADMIN_PASSWORD', 'degistir-beni'),
            RoleName::Admin,
        );

        // Patron ve Personel örnek hesapları yalnızca geliştirme/test içindir.
        if (app()->environment('production')) {
            return;
        }

        $this->ensureUser($users, 'patron@example.com', 'Patron', 'degistir-beni', RoleName::Patron);
        $this->ensureUser($users, 'personel@example.com', 'Personel', 'degistir-beni', RoleName::Personel);
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
