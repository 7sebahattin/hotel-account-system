<?php

declare(strict_types=1);

namespace App\Modules\CashBook\Providers;

use App\Modules\CashBook\Models\CashMovement;
use App\Modules\CashBook\Models\CashSession;
use App\Modules\CashBook\Models\CashTransfer;
use App\Modules\CashBook\Policies\CashMovementPolicy;
use App\Modules\CashBook\Policies\CashSessionPolicy;
use App\Modules\CashBook\Policies\CashTransferPolicy;
use App\Modules\Core\Providers\ModuleServiceProvider;

class CashBookServiceProvider extends ModuleServiceProvider
{
    protected string $namespace = 'cashbook';

    protected array $policies = [
        CashSession::class => CashSessionPolicy::class,
        CashMovement::class => CashMovementPolicy::class,
        CashTransfer::class => CashTransferPolicy::class,
    ];
}
