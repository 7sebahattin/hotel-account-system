<?php

declare(strict_types=1);

namespace App\Modules\Rooms\Services;

use App\Modules\Rooms\Exceptions\RoomHasStayHistory;
use App\Modules\Rooms\Models\Room;
use App\Modules\Stays\Services\StayService;
use Illuminate\Support\Facades\DB;

/**
 * Oda modülünün dışa açık tek kapısı (CLAUDE.md §4.3).
 *
 * Kullanılmış (bir konaklamada referans edilmiş) bir oda asla hard-delete
 * edilmez — CompanyService::delete()'in "cari hareketi olan firma silinemez"
 * ilkesiyle AYNI desen. Kontrol StayService::roomHasHistory() üzerinden
 * yapılır: Rooms modülü Stay modelini doğrudan KULLANMAZ (CLAUDE.md §4.3/1).
 */
class RoomService
{
    public function __construct(private readonly StayService $stays) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Room
    {
        return DB::transaction(fn (): Room => Room::query()->create([
            'room_no' => $data['room_no'],
            'room_name' => $data['room_name'] ?? null,
            'room_type' => $data['room_type'] ?? null,
            'is_active' => true,
        ]));
    }

    /**
     * Yalnızca tanım alanları güncellenir; `is_active` ayrı bir eylemdir (setActive).
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Room $room, array $data): void
    {
        DB::transaction(function () use ($room, $data): void {
            $room->update([
                'room_no' => $data['room_no'],
                'room_name' => $data['room_name'] ?? null,
                'room_type' => $data['room_type'] ?? null,
            ]);
        });
    }

    public function setActive(Room $room, bool $active): void
    {
        DB::transaction(function () use ($room, $active): void {
            $room->update(['is_active' => $active]);
        });
    }

    /**
     * @throws RoomHasStayHistory konaklama geçmişi olan bir oda için
     */
    public function delete(Room $room): void
    {
        if ($this->stays->roomHasHistory($room->getKey())) {
            throw RoomHasStayHistory::make($room->room_no);
        }

        DB::transaction(function () use ($room): void {
            $room->delete();
        });
    }

    /**
     * Konaklama formundaki oda seçeneği listesi: aktif odalar + (varsa)
     * düzenlenen kaydın kendi odası — pasifleşmiş olsa bile seçenekten
     * KAYBOLMAZ, aksi hâlde düzenleme ekranı formu sessizce boşaltırdı.
     *
     * @return array<int, string>
     */
    public function optionsForForm(?int $includeInactiveId = null): array
    {
        $query = Room::query()->where('is_active', true);

        if ($includeInactiveId !== null) {
            $query->orWhere('id', $includeInactiveId);
        }

        return $query->orderBy('room_no')
            ->get()
            ->mapWithKeys(fn (Room $room): array => [$room->getKey() => $room->label()])
            ->all();
    }

    public function activeCount(): int
    {
        return Room::query()->where('is_active', true)->count();
    }
}
