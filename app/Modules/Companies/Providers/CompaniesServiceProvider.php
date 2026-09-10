<?php

declare(strict_types=1);

namespace App\Modules\Companies\Providers;

use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Policies\CompanyPolicy;
use App\Modules\Core\Providers\ModuleServiceProvider;

class CompaniesServiceProvider extends ModuleServiceProvider
{
    protected string $namespace = 'companies';

    protected array $policies = [
        Company::class => CompanyPolicy::class,
    ];
}
