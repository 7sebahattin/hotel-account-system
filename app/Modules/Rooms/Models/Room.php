<?php

declare(strict_types=1);

namespace App\Modules\Rooms\Models;

use App\Models\User;
use App\Modules\Core\Concerns\Auditable;
use App\Modules\Core\Concerns\HasCreator;
use App\Modules\Rooms\Database\Factories\RoomFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Oda tanımı (master data) — Yönetim → Odalar.
 *
 * Basit bir tanım kaydıdır (Company/User ile aynı desen): denetim kaydı
 * Auditable trait'i ile otomatik yazılır, StayService gibi özel bir akış
 * gerekmez çünkü oda tanımının kendisi bir cari hareket üretmez.
 *
 * Kullanılmış bir oda (bir konaklamada referans edilmiş) asla hard-delete
 * edilmez, yalnızca `is_active = false` yapılır — kullanım kontrolü
 * `RoomService::delete()` içindedir (Stays modülünün Service'i üzerinden;
 * Room modülü Stay modelini doğrudan kullanmaz, CLAUDE.md §4.3/1).
 *
 * @property int $id
 * @property string $room_no
 * @property string|null $room_name
 * @property string|null $room_type
 * @property bool $is_active
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User|null $creator
 * @property-read User|null $updater
 */
class Room extends Model
{
    /** @use HasFactory<RoomFactory> */
    use Auditable, HasCreator, HasFactory, SoftDeletes;

    protected $fillable = ['room_no', 'room_name', 'room_type', 'is_active'];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'created_by' => 'integer',
            'updated_by' => 'integer',
        ];
    }

    /** Listelerde gösterilen kısa etiket: "12 (Deniz Manzaralı)" ya da yalnızca "12". */
    public function label(): string
    {
        return $this->room_name ? sprintf('%s (%s)', $this->room_no, $this->room_name) : $this->room_no;
    }

    protected static function newFactory(): RoomFactory
    {
        return RoomFactory::new();
    }
}
