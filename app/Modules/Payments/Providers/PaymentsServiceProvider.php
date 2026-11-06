<?php

declare(strict_types=1);

namespace App\Modules\Payments\Providers;

use App\Modules\Core\Providers\ModuleServiceProvider;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Policies\PaymentPolicy;
use App\Modules\Payments\Trash\PaymentTrashProvider;

class PaymentsServiceProvider extends ModuleServiceProvider
{
    protected string $namespace = 'payments';

    protected array $policies = [
        Payment::class => PaymentPolicy::class,
    ];

    protected array $trashProviders = [
        PaymentTrashProvider::class,
    ];
}
