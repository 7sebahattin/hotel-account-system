<?php

declare(strict_types=1);

namespace App\Modules\MonthlyAccount\Policies;

use App\Models\User;
use App\Modules\Authorization\Enums\Permission;
use App\Modules\MonthlyAccount\Models\MonthlyPaymentProvider;

class MonthlyPaymentProviderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::MonthlyAccountManageProviders->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::MonthlyAccountManageProviders->value);
    }

    public function update(User $user, MonthlyPaymentProvider $provider): bool
    {
        return $user->can(Permission::MonthlyAccountManageProviders->value);
    }

    public function delete(User $user, MonthlyPaymentProvider $provider): bool
    {
        return $user->can(Permission::MonthlyAccountManageProviders->value);
    }
}
