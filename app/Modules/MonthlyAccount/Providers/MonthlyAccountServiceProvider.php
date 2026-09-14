<?php

declare(strict_types=1);

namespace App\Modules\MonthlyAccount\Providers;

use App\Modules\Core\Providers\ModuleServiceProvider;
use App\Modules\MonthlyAccount\Models\MonthlyAccountDay;
use App\Modules\MonthlyAccount\Models\MonthlyPaymentProvider;
use App\Modules\MonthlyAccount\Policies\MonthlyAccountDayPolicy;
use App\Modules\MonthlyAccount\Policies\MonthlyPaymentProviderPolicy;

class MonthlyAccountServiceProvider extends ModuleServiceProvider
{
    protected string $namespace = 'monthly-account';

    protected array $policies = [
        MonthlyAccountDay::class => MonthlyAccountDayPolicy::class,
        MonthlyPaymentProvider::class => MonthlyPaymentProviderPolicy::class,
    ];
}
