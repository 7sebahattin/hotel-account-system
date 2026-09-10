<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Providers;

use App\Modules\Core\Providers\ModuleServiceProvider;

class DashboardServiceProvider extends ModuleServiceProvider
{
    protected string $namespace = 'dashboard';
}
