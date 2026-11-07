<?php

declare(strict_types=1);

namespace App\Modules\Stays\Providers;

use App\Modules\Core\Providers\ModuleServiceProvider;
use App\Modules\Ledger\Support\LedgerVisibility;
use App\Modules\Stays\Models\Stay;
use App\Modules\Stays\Policies\StayPolicy;
use App\Modules\Stays\Trash\StayTrashProvider;

class StaysServiceProvider extends ModuleServiceProvider
{
    protected string $namespace = 'stays';

    protected array $policies = [
        Stay::class => StayPolicy::class,
    ];

    protected array $trashProviders = [
        StayTrashProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();

        // Çöpteki konaklamanın (soft delete) defter satırları ekstre/rapor/toplamlardan gizlenir.
        LedgerVisibility::hideSourceWhenSet('stay', 'stays', 'deleted_at');
    }
}
