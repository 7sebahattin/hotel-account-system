<?php

declare(strict_types=1);

namespace App\Modules\CariCleanup\Providers;

use App\Modules\CariCleanup\Policies\CariCleanupPolicy;
use App\Modules\CariCleanup\Services\CariCleanupService;
use App\Modules\Core\Providers\ModuleServiceProvider;

class CariCleanupServiceProvider extends ModuleServiceProvider
{
    protected string $namespace = 'cari-cleanup';

    protected array $policies = [CariCleanupService::class => CariCleanupPolicy::class];
}
