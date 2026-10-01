<?php

declare(strict_types=1);

namespace Tests\Feature\Stays;

use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Companies\Enums\AccountType;
use App\Modules\Companies\Models\Company;
use App\Modules\Rooms\Models\Room;
use App\Modules\Stays\Models\Stay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StayDuplicateCheckInTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->actingAs($this->userWithRole(RoleName::Admin));
        $this->company = Company::factory()->create();
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'company_id' => $this->company->id,
            'guest_name' => 'Ahmet Kurt',
            'check_in_date' => '2026-09-28',
            'check_out_date' => '2026-09-29',
            'pricing_mode' => 'total',
            'amount' => '1000.00',
            'currency' => 'TRY',
        ], $overrides);
    }

    #[Test]
    public function ilk_konaklama_olusturulur(): void
    {
        $this->post(route('stays.store'), $this->payload())->assertSessionHasNoErrors();

        $this->assertDatabaseCount('stays', 1);
    }

    #[Test]
    public function ayni_kisi_ve_giris_tarihi_ikinci_kez_olusturulamaz_ve_finansal_yan_etki_uretmez(): void
    {
        $this->post(route('stays.store'), $this->payload())->assertSessionHasNoErrors();

        $this->post(route('stays.store'), $this->payload())
            ->assertSessionHasErrors(['guest_name' => 'Bu kişi için seçilen giriş tarihinde zaten bir konaklama kaydı bulunuyor.']);

        $this->assertDatabaseCount('stays', 1);
        $this->assertDatabaseCount('ledger_entries', 1);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('companies', 1);
    }

    #[Test]
    public function ayni_kisi_farkli_giris_tarihinde_konaklayabilir(): void
    {
        $this->post(route('stays.store'), $this->payload())->assertSessionHasNoErrors();
        $this->post(route('stays.store'), $this->payload([
            'check_in_date' => '2026-09-29',
            'check_out_date' => '2026-09-30',
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('stays', 2);
    }

    #[Test]
    public function farkli_kisi_ayni_giris_tarihinde_konaklayabilir(): void
    {
        $this->post(route('stays.store'), $this->payload())->assertSessionHasNoErrors();
        $this->post(route('stays.store'), $this->payload(['guest_name' => 'Mehmet Kaya']))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('stays', 2);
    }

    #[Test]
    public function oda_degisikligi_duplicate_kontrolunu_asamaz(): void
    {
        $firstRoom = Room::factory()->create();
        $secondRoom = Room::factory()->create();

        $this->post(route('stays.store'), $this->payload(['room_id' => $firstRoom->id]))->assertSessionHasNoErrors();
        $this->post(route('stays.store'), $this->payload(['room_id' => $secondRoom->id]))->assertSessionHasErrors('guest_name');

        $this->assertDatabaseCount('stays', 1);
    }

    #[Test]
    public function iptal_edilmis_konaklama_duplicate_sayilmaz(): void
    {
        $this->post(route('stays.store'), $this->payload())->assertSessionHasNoErrors();
        $stay = Stay::query()->firstOrFail();
        $this->patch(route('stays.cancel', $stay))->assertSessionHasNoErrors();

        $this->post(route('stays.store'), $this->payload())->assertSessionHasNoErrors();

        $this->assertDatabaseCount('stays', 2);
        $this->assertDatabaseCount('ledger_entries', 3);
    }

    #[Test]
    public function kayit_kendi_kisi_ve_giris_tarihiyle_guncellenebilir(): void
    {
        $this->post(route('stays.store'), $this->payload())->assertSessionHasNoErrors();
        $stay = Stay::query()->firstOrFail();

        $this->put(route('stays.update', $stay), $this->payload(['notes' => 'Düzeltme']))->assertSessionHasNoErrors();

        $this->assertSame('Düzeltme', $stay->fresh()->notes);
    }

    #[Test]
    public function guncelleme_baska_bir_kisi_ve_tarih_ile_cakisamaz(): void
    {
        $this->post(route('stays.store'), $this->payload())->assertSessionHasNoErrors();
        $this->post(route('stays.store'), $this->payload([
            'guest_name' => 'Mehmet Kaya',
            'check_in_date' => '2026-09-29',
            'check_out_date' => '2026-09-30',
        ]))->assertSessionHasNoErrors();
        $stay = Stay::query()->where('guest_name', 'Mehmet Kaya')->firstOrFail();

        $this->put(route('stays.update', $stay), $this->payload())->assertSessionHasErrors('guest_name');

        $this->assertSame('Mehmet Kaya', $stay->fresh()->guest_name);
        $this->assertSame('2026-09-29', $stay->fresh()->check_in_date->toDateString());
    }

    #[Test]
    public function yeni_bireysel_hesapta_adin_bosluk_ve_harf_buyuklugu_duplicate_kontrolunu_asamaz(): void
    {
        $data = $this->payload(['company_id' => '']);
        $this->post(route('stays.store'), $data)->assertSessionHasNoErrors();
        $this->post(route('stays.store'), $this->payload([
            'company_id' => '',
            'guest_name' => ' ahmet kurt ',
        ]))->assertSessionHasErrors('guest_name');

        $this->assertDatabaseCount('companies', 2);
        $this->assertDatabaseCount('stays', 1);
    }

    #[Test]
    public function secilmis_bireysel_hesap_stabil_id_ile_karsilastirilir(): void
    {
        $person = Company::factory()->create(['account_type' => AccountType::Individual]);
        $this->post(route('stays.store'), $this->payload([
            'company_id' => '',
            'individual_account_id' => $person->id,
            'guest_name' => 'Ahmet Kurt',
        ]))->assertSessionHasNoErrors();
        $this->post(route('stays.store'), $this->payload([
            'company_id' => '',
            'individual_account_id' => $person->id,
            'guest_name' => 'Farklı Yazım',
        ]))->assertSessionHasErrors('guest_name');

        $this->assertDatabaseCount('stays', 1);
    }
}
