<?php

declare(strict_types=1);

namespace App\Modules\ReceiptBook\Policies;

use App\Models\User;
use App\Modules\Authorization\Enums\Permission;
use App\Modules\ReceiptBook\Models\ReceiptBookEntry;

/**
 * Tahsilat Makbuzu Defteri — görüntüleme/yazdırma ve oluşturma iki AYRI
 * yetkidir (v2.1.1-C §9). Düzenleme/silme yeteneği hiç TANIMLANMAZ: route
 * yoktur, çünkü makbuz geçmişi asla değiştirilmez (§6 — "do not hard-delete
 * issued receipts").
 */
class ReceiptBookEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ReceiptBookView->value);
    }

    public function view(User $user, ReceiptBookEntry $entry): bool
    {
        return $user->can(Permission::ReceiptBookView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ReceiptBookCreate->value);
    }
}
