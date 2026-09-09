<?php

declare(strict_types=1);

namespace App\Modules\Rooms\Policies;

use App\Models\User;
use App\Modules\Authorization\Enums\Permission;
use App\Modules\Rooms\Models\Room;

/**
 * Oda tanımı yönetimi — Admin/Patron (kullanıcı talimatı: Room Registry §1).
 *
 * Görüntüleme ayrı yetki DEĞİLDİR: `rooms.manage` yetkisi olmayan (Personel)
 * bu ekranı hiç görmez, ama konaklama formundaki oda SEÇİMİ ayrı bir yoldur
 * (RoomService::optionsForForm()) ve stays.create/update yetkisine bağlıdır.
 */
class RoomPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::RoomsManage->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::RoomsManage->value);
    }

    public function update(User $user, Room $room): bool
    {
        return $user->can(Permission::RoomsManage->value);
    }

    /**
     * Silme yetkisi kayda bağlı değildir; kullanım (konaklama geçmişi)
     * kontrolü RoomService::delete() içindedir — asıl koruma oradadır,
     * burası yalnızca arayüzde butonu erken gizler (Company ile AYNI ilke).
     */
    public function delete(User $user, Room $room): bool
    {
        return $user->can(Permission::RoomsManage->value);
    }
}
