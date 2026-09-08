<?php

declare(strict_types=1);

namespace App\Modules\CashBook\Policies;

use App\Models\User;
use App\Modules\Authorization\Enums\Permission;
use App\Modules\CashBook\Models\CashTransfer;

/**
 * Devir yetkileri — devir İKİ TARAFLIdır.
 *
 * Kabul yetkisi YALNIZCA devrin hedefindedir: teslim eden kendi devrini kabul
 * edemez, üçüncü bir kişi araya giremez, admin bile başkasının adına kabul
 * edemez (kayıt argümanlı yetenek olduğu için Gate::before kısayolu da geçmez).
 */
class CashTransferPolicy
{
    public function view(User $user, CashTransfer $transfer): bool
    {
        if (! $user->can(Permission::CashBookView->value)) {
            return false;
        }

        return $transfer->from_user_id === $user->getKey()
            || $transfer->to_user_id === $user->getKey()
            || $user->can(Permission::CashBookViewAll->value);
    }

    public function accept(User $user, CashTransfer $transfer): bool
    {
        return $transfer->isPending()
            && $transfer->to_user_id === $user->getKey()
            && $user->can(Permission::CashBookOperate->value);
    }

    /** İptal yalnızca devri BAŞLATANIN elindedir. */
    public function cancel(User $user, CashTransfer $transfer): bool
    {
        return $transfer->isPending()
            && $transfer->from_user_id === $user->getKey()
            && $user->can(Permission::CashBookOperate->value);
    }
}
