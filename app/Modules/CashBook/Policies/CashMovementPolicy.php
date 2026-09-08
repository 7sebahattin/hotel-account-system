<?php

declare(strict_types=1);

namespace App\Modules\CashBook\Policies;

use App\Models\User;
use App\Modules\Authorization\Enums\Permission;
use App\Modules\CashBook\Enums\CashMovementType;
use App\Modules\CashBook\Models\CashMovement;
use App\Modules\CashBook\Models\CashSession;

/**
 * Kasa hareketi yetkileri.
 *
 * DÜZENLEME YOKTUR — bilinçli olarak `update` yeteneği tanımlanmamıştır.
 * Personel düzenleme penceresi (CLAUDE.md §7.4) burada UYGULANMAZ: finansal
 * doğruluk için düzeltmenin tek yolu ters harekettir (kullanıcı talimatı §16).
 * Ters hareket geçmişi silmez, üstüne yazar.
 */
class CashMovementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::CashBookView->value);
    }

    public function view(User $user, CashMovement $movement): bool
    {
        if (! $user->can(Permission::CashBookView->value)) {
            return false;
        }

        return $movement->created_by === $user->getKey()
            || $user->can(Permission::CashBookViewAll->value);
    }

    /**
     * Hareket yalnızca AÇIK ve KENDİ vardiyasına yazılabilir.
     *
     * Kapalı vardiya kontrolü üç katmandadır: burada, serviste ve veritabanı
     * tetikleyicisinde.
     */
    public function create(User $user, CashSession $session): bool
    {
        return $session->isOpen()
            && $user->can(Permission::CashBookOperate->value)
            && $session->opened_by === $user->getKey();
    }

    /**
     * Ters kayıt: kendi açık vardiyasındaki, henüz ters kaydedilmemiş hareket.
     *
     * AÇILIŞ BAKİYESİ TERS KAYDEDİLEMEZ: devirle gelen açılış tutarı, teslim
     * eden tarafın imzaladığı devir kaydıyla birebir eşleşir. Kullanıcı onu
     * ters kaydedebilseydi devredilen para ile kasadaki para sessizce
     * çelişirdi. Yanlış açılış, kapanış sayımındaki FARK olarak görünür ve
     * orada açıklanır — gizlenmez.
     */
    public function reverse(User $user, CashMovement $movement): bool
    {
        $session = $movement->session;

        if ($session === null || ! $session->isOpen() || $movement->is_reversal) {
            return false;
        }

        if ($movement->type === CashMovementType::Opening) {
            return false;
        }

        return $user->can(Permission::CashBookOperate->value)
            && $session->opened_by === $user->getKey();
    }
}
