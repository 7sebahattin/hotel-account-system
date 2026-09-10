<?php

declare(strict_types=1);

namespace Tests\Feature\Rooms;

use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Companies\Models\Company;
use App\Modules\Rooms\Models\Room;
use App\Modules\Stays\Models\Stay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Oda tanımı (Room Registry, K-100) — CLAUDE.md §3.2 istisnası, kullanıcı
 * onayıyla kapsama alındı.
 */
class RoomCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    #[Test]
    public function admin_oda_olusturabilir(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $this->actingAs($admin)
            ->post(route('rooms.store'), ['room_no' => '101', 'room_name' => 'Deniz Manzaralı', 'room_type' => 'Suit'])
            ->assertRedirect(route('rooms.index'));

        $room = Room::query()->firstOrFail();
        $this->assertSame('101', $room->room_no);
        $this->assertSame('Deniz Manzaralı', $room->room_name);
        $this->assertTrue($room->is_active);
    }

    #[Test]
    public function oda_numarasi_benzersiz_olmalidir(): void
    {
        Room::factory()->create(['room_no' => '101']);
        $admin = $this->userWithRole(RoleName::Admin);

        $this->actingAs($admin)
            ->post(route('rooms.store'), ['room_no' => '101'])
            ->assertSessionHasErrors('room_no');

        $this->assertSame(1, Room::query()->count());
    }

    #[Test]
    public function personel_oda_ekranina_erisemez(): void
    {
        $personel = $this->userWithRole(RoleName::Personel);

        $this->actingAs($personel)->get(route('rooms.index'))->assertForbidden();
        $this->actingAs($personel)->get(route('rooms.create'))->assertForbidden();
        $this->actingAs($personel)->post(route('rooms.store'), ['room_no' => '101'])->assertForbidden();
    }

    #[Test]
    public function patron_oda_yonetebilir(): void
    {
        $patron = $this->userWithRole(RoleName::Patron);

        $this->actingAs($patron)->get(route('rooms.index'))->assertOk();
        $this->actingAs($patron)
            ->post(route('rooms.store'), ['room_no' => '202'])
            ->assertRedirect(route('rooms.index'));

        $this->assertDatabaseHas('rooms', ['room_no' => '202']);
    }

    #[Test]
    public function oda_pasiflestirilebilir_ve_aktiflestirilebilir(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $room = Room::factory()->create(['is_active' => true]);

        $this->actingAs($admin)->patch(route('rooms.toggle-active', $room))->assertRedirect();
        $this->assertFalse($room->fresh()->is_active);

        $this->actingAs($admin)->patch(route('rooms.toggle-active', $room->fresh()))->assertRedirect();
        $this->assertTrue($room->fresh()->is_active);
    }

    #[Test]
    public function kullanilmamis_oda_silinebilir(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $room = Room::factory()->create();

        $this->actingAs($admin)->delete(route('rooms.destroy', $room))->assertRedirect(route('rooms.index'));

        $this->assertDatabaseMissing('rooms', ['id' => $room->id]);
    }

    #[Test]
    public function konaklama_gecmisi_olan_oda_silinemez(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $room = Room::factory()->create();
        Stay::factory()->forRoom($room)->create();

        $this->actingAs($admin)->delete(route('rooms.destroy', $room))->assertRedirect();

        $this->assertDatabaseHas('rooms', ['id' => $room->id]);
    }

    #[Test]
    public function pasif_oda_yeni_konaklama_icin_secilemez(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $inactiveRoom = Room::factory()->inactive()->create();
        $company = Company::factory()->create();

        $this->actingAs($admin)->post(route('stays.store'), [
            'company_id' => $company->id,
            'guest_name' => 'Test Misafir',
            'room_id' => $inactiveRoom->id,
            'check_in_date' => '2026-09-07',
            'check_out_date' => '2026-09-10',
            'pricing_mode' => 'total',
            'amount' => '9000,00',
            'currency' => 'TRY',
        ])->assertSessionHasErrors('room_id');

        $this->assertSame(0, Stay::query()->count());
    }
}
