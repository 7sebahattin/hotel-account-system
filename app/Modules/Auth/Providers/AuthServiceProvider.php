<?php

declare(strict_types=1);

namespace App\Modules\Auth\Providers;

use App\Modules\Core\Providers\ModuleServiceProvider;

class AuthServiceProvider extends ModuleServiceProvider
{
    protected string $namespace = 'auth';
}
