<?php

declare(strict_types=1);

namespace App\Modules\Payments\Providers;

use App\Modules\Core\Providers\ModuleServiceProvider;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Policies\PaymentPolicy;

class PaymentsServiceProvider extends ModuleServiceProvider
{
    protected string $namespace = 'payments';

    protected array $policies = [
        Payment::class => PaymentPolicy::class,
    ];
}
