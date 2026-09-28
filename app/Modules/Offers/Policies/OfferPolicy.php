<?php

declare(strict_types=1);

namespace App\Modules\Offers\Policies;

use App\Models\User;
use App\Modules\Authorization\Enums\Permission;
use App\Modules\Offers\Models\Offer;

class OfferPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::OffersView->value);
    }

    public function view(User $user, Offer $offer): bool
    {
        return $user->can(Permission::OffersView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::OffersCreate->value);
    }

    public function update(User $user, Offer $offer): bool
    {
        return $user->can(Permission::OffersUpdate->value);
    }
}
