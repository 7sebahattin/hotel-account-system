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
 * Room Registry (K-100) — Stays entegrasyonu. Geriye dönük uyumluluk:
 * eski `room_no` serbest metin verisi hiç yeniden yazılmaz, yalnızca
 * OKUNUR; yeni kayıtlar `room_id` ile bağlanır.
 */
class RoomStayIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->company = Company::factory()->create();
    }

    #[Test]
    public function yeni_konaklama_odaya_baglanir(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $room = Room::factory()->create(['room_no' => '305', 'room_name' => 'Bahçe Katı']);

        $this->actingAs($admin)->post(route('stays.store'), [
            'company_id' => $this->company->id,
            'guest_name' => 'Oda Seçen Misafir',
            'room_id' => $room->id,
            'check_in_date' => '2026-09-07',
            'check_out_date' => '2026-09-10',
            'pricing_mode' => 'total',
            'amount' => '9000,00',
            'currency' => 'TRY',
        ])->assertSessionHasNoErrors();

        $stay = Stay::query()->firstOrFail();
        $this->assertSame($room->id, $stay->room_id);
        $this->assertNull($stay->room_no);
        $this->assertSame('305 (Bahçe Katı)', $stay->roomLabel());
    }

    #[Test]
    public function eski_serbest_metin_oda_bilgisi_okunabilir_kalir(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        // Room Registry ÖNCESİ oluşturulmuş bir kayıt simülasyonu: room_id
        // yok, yalnızca eski serbest metin var. Bu veri hiç değiştirilmez.
        $legacyStay = Stay::factory()->create(['room_no' => '112', 'company_id' => $this->company->id]);

        $this->assertNull($legacyStay->room_id);
        $this->assertSame('112', $legacyStay->roomLabel());

        $this->actingAs($admin)->get(route('stays.show', $legacyStay))->assertOk()->assertSee('112');
        $this->actingAs($admin)->get(route('stays.index'))->assertOk()->assertSee('112');
    }

    #[Test]
    public function oda_secilmeden_de_konaklama_olusturulabilir(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $this->actingAs($admin)->post(route('stays.store'), [
            'company_id' => $this->company->id,
            'guest_name' => 'Odasız Kayıt',
            'check_in_date' => '2026-09-07',
            'check_out_date' => '2026-09-10',
            'pricing_mode' => 'total',
            'amount' => '9000,00',
            'currency' => 'TRY',
        ])->assertSessionHasNoErrors();

        $stay = Stay::query()->firstOrFail();
        $this->assertNull($stay->room_id);
        $this->assertNull($stay->roomLabel());
    }

    #[Test]
    public function duzenlemede_kendi_pasif_odasi_secenekten_kaybolmaz(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $room = Room::factory()->create();
        $stay = Stay::factory()->forRoom($room)->create(['company_id' => $this->company->id]);

        $room->update(['is_active' => false]);

        // Aynı (artık pasif) odayı KORUYARAK güncelleme kabul edilir.
        $this->actingAs($admin)->put(route('stays.update', $stay), [
            'company_id' => $this->company->id,
            'guest_name' => 'Güncellendi',
            'room_id' => $room->id,
            'check_in_date' => $stay->check_in_date->toDateString(),
            'check_out_date' => $stay->check_out_date->toDateString(),
            'pricing_mode' => 'total',
            'amount' => $stay->amount,
            'currency' => $stay->currency->value,
        ])->assertSessionHasNoErrors();

        $this->assertSame($room->id, $stay->fresh()->room_id);
    }

    #[Test]
    public function konaklama_arama_odaya_gore_de_calisir(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $room = Room::factory()->create(['room_no' => '777']);
        Stay::factory()->forRoom($room)->create(['guest_name' => 'Aranan Kayıt', 'company_id' => $this->company->id]);
        Stay::factory()->create(['guest_name' => 'Diğer Kayıt', 'company_id' => $this->company->id]);

        $this->actingAs($admin)
            ->get(route('stays.index', ['q' => '777']))
            ->assertOk()
            ->assertSee('Aranan Kayıt')
            ->assertDontSee('Diğer Kayıt');
    }
}
