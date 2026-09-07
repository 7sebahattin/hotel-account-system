<?php

declare(strict_types=1);

namespace App\Modules\Companies\Policies;

use App\Models\User;
use App\Modules\Authorization\Enums\Permission;
use App\Modules\Companies\Models\Company;

class CompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::CompaniesView->value);
    }

    public function view(User $user, Company $company): bool
    {
        if (! $user->can(Permission::CompaniesView->value)) {
            return false;
        }

        // Silinmiş firmayı yalnızca geri alma yetkisi olan görebilir.
        return ! $company->trashed() || $user->can(Permission::CompaniesRestore->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::CompaniesCreate->value);
    }

    /**
     * Personel yalnızca KENDİ oluşturduğu firmayı ve yalnızca düzenleme
     * penceresi içinde değiştirebilir (CLAUDE.md §7.4).
     *
     * Personel'i ayıran şey companies.delete yetkisinin olmamasıdır; Admin ve
     * Patron bu yetkiye sahip olduğu için kısıt onlara uygulanmaz.
     */
    public function update(User $user, Company $company): bool
    {
        if (! $user->can(Permission::CompaniesUpdate->value) || $company->trashed()) {
            return false;
        }

        if ($user->can(Permission::CompaniesDelete->value)) {
            return true;
        }

        return $this->isOwnRecentRecord($user, $company);
    }

    public function delete(User $user, Company $company): bool
    {
        return $user->can(Permission::CompaniesDelete->value) && ! $company->trashed();
    }

    public function restore(User $user, Company $company): bool
    {
        return $user->can(Permission::CompaniesRestore->value) && $company->trashed();
    }

    /** Silinmiş kayıtları listeleyebilme. */
    public function viewTrashed(User $user): bool
    {
        return $user->can(Permission::CompaniesRestore->value);
    }

    private function isOwnRecentRecord(User $user, Company $company): bool
    {
        if ($company->created_by !== $user->getKey()) {
            return false;
        }

        $windowHours = (int) config('hotel.personnel_edit_window_hours');

        return $company->created_at->greaterThanOrEqualTo(now()->subHours($windowHours));
    }
}
