<?php

declare(strict_types=1);

namespace App\Modules\Stays\Policies;

use App\Models\User;
use App\Modules\Authorization\Enums\Permission;
use App\Modules\Stays\Models\Stay;

class StayPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::StaysView->value);
    }

    public function view(User $user): bool
    {
        return $user->can(Permission::StaysView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::StaysCreate->value);
    }

    /**
     * İptal edilmiş konaklama kimse tarafından düzenlenemez.
     *
     * Personel yalnızca KENDİ oluşturduğu kaydı ve düzenleme penceresi içinde
     * değiştirebilir (CLAUDE.md §7.4). Finansal alanları da değiştirebilir;
     * bu durumda StayService ters kayıt + yeni kayıt üretir.
     *
     * Personel'i ayıran şey stays.delete yetkisinin olmamasıdır.
     */
    public function update(User $user, Stay $stay): bool
    {
        if (! $user->can(Permission::StaysUpdate->value) || $stay->isCancelled()) {
            return false;
        }

        if ($user->can(Permission::StaysDelete->value)) {
            return true;
        }

        return $this->isOwnRecentRecord($user, $stay);
    }

    /**
     * İptal etme yetkisi. V1'de konaklama silinmez, iptal edilir; bu yüzden
     * yetki adı stays.delete olarak kalır (CLAUDE.md §7.2).
     */
    public function cancel(User $user, Stay $stay): bool
    {
        return $user->can(Permission::StaysDelete->value) && ! $stay->isCancelled();
    }

    /**
     * "Sil" = çöp kutusuna taşı. Konaklamayı iptal edebilen (stays.delete) kullanıcı
     * çöpe de atabilir; aktif konaklama önce iptal edilir (ters kayıt). Geri alma ve
     * kalıcı silme ortak Çöp Kutusu ekranındadır (Core\Trash).
     */
    public function trash(User $user, Stay $stay): bool
    {
        return $user->can(Permission::StaysDelete->value);
    }

    /**
     * Fatura kesildi/kesilmedi işareti: konaklama düzenleyebilen herkes
     * (Admin/Patron/Personel) değiştirebilir; kayıt sahipliği/süre penceresi
     * ARANMAZ çünkü finansal alan değildir. İptal edilmiş kayıtta değişmez.
     */
    public function toggleInvoice(User $user, Stay $stay): bool
    {
        return $user->can(Permission::StaysUpdate->value) && ! $stay->isCancelled();
    }

    private function isOwnRecentRecord(User $user, Stay $stay): bool
    {
        if ($stay->created_by !== $user->getKey()) {
            return false;
        }

        $windowHours = (int) config('hotel.personnel_edit_window_hours');

        return $stay->created_at->greaterThanOrEqualTo(now()->subHours($windowHours));
    }
}
