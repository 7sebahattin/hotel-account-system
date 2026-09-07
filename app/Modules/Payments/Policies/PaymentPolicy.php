<?php

declare(strict_types=1);

namespace App\Modules\Payments\Policies;

use App\Models\User;
use App\Modules\Authorization\Enums\Permission;
use App\Modules\Payments\Models\Payment;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::PaymentsView->value);
    }

    public function view(User $user): bool
    {
        return $user->can(Permission::PaymentsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::PaymentsCreate->value);
    }

    /**
     * Yalnızca METADATA düzeltmesi (`reference_no`, `description`).
     *
     * Finansal alanların değişmezliği bu yetenekle ilgili değildir; onu
     * PaymentService ve Payment modeli hiçbir role bakmadan zorlar.
     *
     * İptal edilmiş tahsilat kimse tarafından düzenlenemez.
     * Personel yalnızca KENDİ oluşturduğu kaydı ve düzenleme penceresi
     * içinde düzeltebilir (CLAUDE.md §7.4); Admin ve Patron'u ayıran şey
     * payments.cancel yetkisine sahip olmalarıdır.
     */
    public function update(User $user, Payment $payment): bool
    {
        if (! $user->can(Permission::PaymentsCreate->value) || $payment->isCancelled()) {
            return false;
        }

        if ($user->can(Permission::PaymentsCancel->value)) {
            return true;
        }

        return $this->isOwnRecentRecord($user, $payment);
    }

    /** Tahsilat silinmez, iptal edilir. */
    public function cancel(User $user, Payment $payment): bool
    {
        return $user->can(Permission::PaymentsCancel->value) && ! $payment->isCancelled();
    }

    private function isOwnRecentRecord(User $user, Payment $payment): bool
    {
        if ($payment->created_by !== $user->getKey()) {
            return false;
        }

        $windowHours = (int) config('hotel.personnel_edit_window_hours');

        return $payment->created_at->greaterThanOrEqualTo(now()->subHours($windowHours));
    }
}
