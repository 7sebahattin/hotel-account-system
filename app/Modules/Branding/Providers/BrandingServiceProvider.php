<?php

declare(strict_types=1);

namespace App\Modules\Branding\Providers;

use App\Models\User;
use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Core\Providers\ModuleServiceProvider;
use Illuminate\Support\Facades\Gate;

class BrandingServiceProvider extends ModuleServiceProvider
{
    protected string $namespace = 'branding';

    public function boot(): void
    {
        parent::boot();

        Gate::define('brand.manage', fn (User $user): bool => $user->hasAnyRole([
            RoleName::Admin->value,
            RoleName::Patron->value,
        ]));
    }
}
