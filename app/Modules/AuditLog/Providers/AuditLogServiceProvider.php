<?php

declare(strict_types=1);

namespace App\Modules\AuditLog\Providers;

use App\Modules\AuditLog\Models\AuditLog;
use App\Modules\AuditLog\Policies\AuditLogPolicy;
use App\Modules\Core\Providers\ModuleServiceProvider;

class AuditLogServiceProvider extends ModuleServiceProvider
{
    protected string $namespace = 'audit';

    protected array $policies = [
        AuditLog::class => AuditLogPolicy::class,
    ];
}
