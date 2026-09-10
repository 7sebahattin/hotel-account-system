<?php

declare(strict_types=1);

namespace App\Modules\CashBook\Policies;

use App\Models\User;
use App\Modules\Authorization\Enums\Permission;
use App\Modules\CashBook\Models\CashSession;

/**
 * Kasa vardiyası yetkileri.
 *
 * TEMEL İLKE — HESAP VEREBİLİRLİK: fiziksel kasadan vardiyayı AÇAN kişi
 * sorumludur. Bu yüzden bir vardiyaya hareket girmek, devretmek ve ters kayıt
 * yazmak yalnızca o vardiyanın sahibine açıktır; admin/patron dahil kimse
 * başkasının vardiyasına hareket yazamaz (kullanıcı talimatı §20: "personel
 * başka personelin session'ına hareket ekleyemez").
 *
 * Bu, Gate::before'un admin kısayoluna da takılmaz: kayıt argümanı taşıyan
 * yetenekler kısa devre YAPILMAZ (CLAUDE.md §7.1).
 *
 * Tek istisna KAPATMAdır: personel vardiyayı kapatmadan giderse kasa kilitli
 * kalmasın diye `cashbook.view_all` yetkisi olan (Patron/Admin) da kapatabilir.
 * Kapatan kişi denetim kaydına yazılır.
 */
class CashSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::CashBookView->value);
    }

    public function view(User $user, CashSession $session): bool
    {
        if (! $user->can(Permission::CashBookView->value)) {
            return false;
        }

        return $this->isOwner($user, $session) || $user->can(Permission::CashBookViewAll->value);
    }

    /** Kasayı açmak (ilk açılış). Açık vardiya varsa servis ayrıca reddeder. */
    public function create(User $user): bool
    {
        return $user->can(Permission::CashBookOperate->value);
    }

    public function close(User $user, CashSession $session): bool
    {
        if (! $session->isOpen() || ! $user->can(Permission::CashBookOperate->value)) {
            return false;
        }

        return $this->isOwner($user, $session) || $user->can(Permission::CashBookViewAll->value);
    }

    /** Devri yalnızca kasayı fiilen elinde tutan başlatabilir. */
    public function handover(User $user, CashSession $session): bool
    {
        return $session->isOpen()
            && $user->can(Permission::CashBookOperate->value)
            && $this->isOwner($user, $session);
    }

    /** Günlük kasa defteri ve tüm vardiyaların raporu. */
    public function viewAll(User $user): bool
    {
        return $user->can(Permission::CashBookViewAll->value);
    }

    private function isOwner(User $user, CashSession $session): bool
    {
        return $session->opened_by === $user->getKey();
    }
}
