<?php

declare(strict_types=1);

namespace App\Modules\CashBook\Policies;

use App\Models\User;
use App\Modules\Authorization\Enums\Permission;
use App\Modules\CashBook\Models\CashMovementCategory;

/**
 * Kasa hareket türü (kategori) yönetimi — Admin/Patron (v2.1.1-B §5).
 *
 * DÜZENLEME görüntü adını ve aktif/pasif durumunu değiştirir; `system_type`
 * (sabit davranış) ve geçmişi olan bir kategorinin varlığı asla silinemez —
 * bu servis katmanında ayrıca zorlanır (CashMovementCategoryService yoktur,
 * kural doğrudan controller + model `hasHistory()` ile uygulanır çünkü bu
 * basit bir CRUD'dur, ayrı bir servis katmanı gereksiz soyutlama olurdu).
 */
class CashMovementCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::CashBookManageCategories->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::CashBookManageCategories->value);
    }

    public function update(User $user, CashMovementCategory $category): bool
    {
        return $user->can(Permission::CashBookManageCategories->value);
    }
}
