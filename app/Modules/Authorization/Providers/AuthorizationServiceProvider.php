<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Providers;

use App\Modules\Authorization\Models\Role;
use App\Modules\Authorization\Policies\RolePolicy;
use App\Modules\Core\Providers\ModuleServiceProvider;

class AuthorizationServiceProvider extends ModuleServiceProvider
{
    protected string $namespace = 'authorization';

    protected array $policies = [
        Role::class => RolePolicy::class,
    ];
}
