<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Sisteme giriş yapılabilmesi için ilk yönetici hesabı.
     *
     * Rol ataması Authorization modülü ile gelecektir (CLAUDE.md §14, Gün 2);
     * bu aşamada yalnızca kimlik doğrulama hesabı oluşturulur.
     */
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['email' => env('SEED_ADMIN_EMAIL', 'admin@example.com')],
            [
                'name' => env('SEED_ADMIN_NAME', 'Sistem Yöneticisi'),
                'password' => env('SEED_ADMIN_PASSWORD', 'degistir-beni'),
                'is_active' => true,
            ],
        );
    }
}
