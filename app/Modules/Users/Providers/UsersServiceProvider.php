<?php

declare(strict_types=1);

namespace App\Modules\Users\Providers;

use App\Models\User;
use App\Modules\Core\Providers\ModuleServiceProvider;
use App\Modules\Users\Policies\UserPolicy;
use App\Modules\Users\Trash\UserTrashProvider;

class UsersServiceProvider extends ModuleServiceProvider
{
    protected string $namespace = 'users';

    protected array $policies = [
        User::class => UserPolicy::class,
    ];

    protected array $trashProviders = [
        UserTrashProvider::class,
    ];
}
