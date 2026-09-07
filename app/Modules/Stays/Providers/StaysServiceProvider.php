<?php

declare(strict_types=1);

namespace App\Modules\Stays\Providers;

use App\Modules\Core\Providers\ModuleServiceProvider;
use App\Modules\Stays\Models\Stay;
use App\Modules\Stays\Policies\StayPolicy;

class StaysServiceProvider extends ModuleServiceProvider
{
    protected string $namespace = 'stays';

    protected array $policies = [
        Stay::class => StayPolicy::class,
    ];
}
