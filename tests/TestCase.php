<?php

declare(strict_types=1);

namespace Tests;

use App\Models\User;
use App\Modules\Authorization\Database\Seeders\RolePermissionSeeder;
use App\Modules\Authorization\Enums\RoleName;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Rol matrisini kurar (CLAUDE.md §7.3). */
    protected function seedRoles(): void
    {
        $this->seed(RolePermissionSeeder::class);
    }

    /** Verilen role sahip aktif bir kullanıcı üretir. */
    protected function userWithRole(RoleName $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role->value);

        return $user->fresh();
    }
}
