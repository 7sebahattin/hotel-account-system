<?php

declare(strict_types=1);

namespace App\Modules\ReceiptBook\Providers;

use App\Modules\Core\Providers\ModuleServiceProvider;
use App\Modules\ReceiptBook\Models\ReceiptBookEntry;
use App\Modules\ReceiptBook\Policies\ReceiptBookEntryPolicy;

class ReceiptBookServiceProvider extends ModuleServiceProvider
{
    protected string $namespace = 'receipt-book';

    protected array $policies = [
        ReceiptBookEntry::class => ReceiptBookEntryPolicy::class,
    ];
}
