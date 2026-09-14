<?php

declare(strict_types=1);

namespace App\Modules\MonthlyAccount\Policies;

use App\Models\User;
use App\Modules\Authorization\Enums\Permission;
use App\Modules\MonthlyAccount\Models\MonthlyAccountDay;

class MonthlyAccountDayPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::MonthlyAccountView->value);
    }

    public function viewAll(User $user): bool
    {
        return $this->viewAny($user) && $user->can(Permission::MonthlyAccountViewAll->value);
    }

    public function viewOwner(User $user, User|int $owner): bool
    {
        return $this->viewAny($user) && $this->ownsOrViewsAll($user, $owner);
    }

    public function view(User $user, MonthlyAccountDay $day): bool
    {
        return $this->viewOwner($user, $day->owner_id);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::MonthlyAccountCreate->value);
    }

    public function createForOwner(User $user, User|int $owner): bool
    {
        return $this->create($user) && $this->ownsOrViewsAll($user, $owner);
    }

    public function update(User $user, MonthlyAccountDay $day): bool
    {
        return $user->can(Permission::MonthlyAccountUpdate->value)
            && $this->ownsOrViewsAll($user, $day->owner_id)
            && (! $day->isDeleted() || $user->can(Permission::MonthlyAccountDelete->value));
    }

    public function delete(User $user, MonthlyAccountDay $day): bool
    {
        return $user->can(Permission::MonthlyAccountDelete->value)
            && $this->ownsOrViewsAll($user, $day->owner_id)
            && ! $day->isDeleted();
    }

    private function ownsOrViewsAll(User $user, User|int $owner): bool
    {
        $ownerId = $owner instanceof User ? (int) $owner->getKey() : $owner;

        return (int) $user->getKey() === $ownerId || $user->can(Permission::MonthlyAccountViewAll->value);
    }
}
