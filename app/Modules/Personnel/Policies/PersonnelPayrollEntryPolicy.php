<?php

declare(strict_types=1);

namespace App\Modules\Personnel\Policies;

use App\Models\User;
use App\Modules\Authorization\Enums\Permission;
use App\Modules\Personnel\Models\PersonnelPayrollEntry;

class PersonnelPayrollEntryPolicy
{
    public function reverse(User $user, PersonnelPayrollEntry $entry): bool
    {
        return $user->can(Permission::PersonnelPayroll->value) && $entry->type !== 'reversal';
    }
}
