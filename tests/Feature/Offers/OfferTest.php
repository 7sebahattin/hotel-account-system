<?php

declare(strict_types=1);

namespace Tests\Feature\Offers;

use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Companies\Models\Company;
use App\Modules\Ledger\Models\LedgerEntry;
use App\Modules\Offers\Enums\OfferStatus;
use App\Modules\Offers\Models\Offer;
use App\Modules\Payments\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OfferTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'customer_name_snapshot' => 'Deniz Turizm',
            'title' => 'Sonbahar konaklama teklifi',
            'offer_date' => '2026-09-28',
            'valid_until' => '2026-10-15',
            'currency' => 'EUR',
            'status' => 'draft',
            'document_heading' => 'LEMON HOTEL FİYATLAR',
            'document_year' => 2026,
            'intro_text' => 'Değerli misafirimiz,',
            'general_notes' => 'Kahvaltı dahildir.',
            'cancellation_terms' => 'Girişten 7 gün öncesine kadar.',
            'footer_notes' => 'Teşekkür ederiz.',
            'bank_account_holder' => 'Lemon Turizm',
            'bank_iban_eur' => 'TR00 0000 0000 0000 0000 0000 00',
            'periods' => [
                ['label' => 'Eylül 2026', 'start_date' => '2026-09-01', 'end_date' => '2026-09-30'],
                ['label' => 'Ekim 2026', 'start_date' => '2026-10-01', 'end_date' => '2026-10-31'],
            ],
            'room_types' => [['name' => 'SINGLE'], ['name' => 'DOUBLE']],
            'prices' => [['100.00', '120.00'], ['150.00', '180.00']],
        ], $overrides);
    }

    #[Test]
    public function teklif_olusturma_numara_matrix_ve_finansal_izolasyon(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $company = Company::factory()->create(['name' => 'Kuzey Seyahat']);
        $data = $this->payload(['company_id' => $company->id, 'customer_name_snapshot' => 'Kuzey Seyahat 2026']);
        $this->actingAs($admin)->post(route('offers.store'), $data)->assertRedirect()->assertSessionDoesntHaveErrors();

        $offer = Offer::query()->with(['periods', 'roomTypes', 'prices'])->firstOrFail();
        $this->assertMatchesRegularExpression('/^TKF-2026-\d{6}$/', $offer->offer_no);
        $this->assertSame($company->id, $offer->company_id);
        $this->assertSame('Kuzey Seyahat 2026', $offer->customer_name_snapshot);
        $this->assertCount(2, $offer->periods);
        $this->assertCount(2, $offer->roomTypes);
        $this->assertCount(4, $offer->prices);
        $this->assertSame('180.00', $offer->prices
            ->first(fn ($price) => $price->offer_room_type_id === $offer->roomTypes[1]->id
                && $price->offer_period_id === $offer->periods[1]->id)->price);
        $this->assertSame(0, LedgerEntry::query()->count());
        $this->assertSame(0, Payment::query()->count());
        $this->assertSame(0, DB::table('cash_movements')->count());

        $this->post(route('offers.store'), $this->payload())->assertSessionDoesntHaveErrors();
        $this->assertSame(2, Offer::query()->distinct()->count('offer_no'));
    }

    #[Test]
    public function cari_adi_bos_birakildiginda_snapshot_alinir_ve_sonradan_degismez(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $company = Company::factory()->create(['name' => 'İlk Cari Adı']);
        $this->actingAs($admin)->post(route('offers.store'), $this->payload([
            'company_id' => $company->id,
            'customer_name_snapshot' => '',
        ]))->assertSessionDoesntHaveErrors();
        $offer = Offer::query()->firstOrFail();
        $this->assertSame('İlk Cari Adı', $offer->customer_name_snapshot);

        $company->update(['name' => 'Yeni Cari Adı']);
        $this->get(route('offers.show', $offer))->assertOk()->assertSee('İlk Cari Adı')->assertDontSee('Yeni Cari Adı');
    }

    #[Test]
    public function liste_filtre_detay_ve_yazdirma_icerigi(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $this->actingAs($admin)->post(route('offers.store'), $this->payload())->assertSessionDoesntHaveErrors();
        $offer = Offer::query()->firstOrFail();
        $this->get(route('offers.index', ['status' => 'draft', 'q' => 'Deniz']))->assertOk()->assertSee($offer->offer_no);
        $this->get(route('offers.index', ['status' => 'sent']))->assertOk()->assertDontSee($offer->offer_no);
        $this->get(route('offers.show', $offer))->assertOk()
            ->assertSee('Deniz Turizm')->assertSee('LEMON HOTEL FİYATLAR')
            ->assertSee('Eylül 2026')->assertSee('DOUBLE')->assertSee('180,00 EUR')
            ->assertSee('Lemon Turizm');
        $this->get(route('offers.print', $offer))->assertOk()
            ->assertSee('Deniz Turizm')->assertSee('LEMON HOTEL FİYATLAR')
            ->assertSee('Eylül 2026')->assertSee('SINGLE')->assertSee('100,00 EUR');
    }

    #[Test]
    public function guncelleme_donem_oda_ve_fiyatlari_degistirir_numarayi_korur(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $this->actingAs($admin)->post(route('offers.store'), $this->payload())->assertSessionDoesntHaveErrors();
        $offer = Offer::query()->firstOrFail();
        $number = $offer->offer_no;
        $this->put(route('offers.update', $offer), $this->payload([
            'status' => OfferStatus::Sent->value,
            'periods' => [['label' => 'Kasım 2026', 'start_date' => '2026-11-01', 'end_date' => '2026-11-30']],
            'room_types' => [['name' => 'TRIPLE']],
            'prices' => [['225.50']],
        ]))->assertRedirect()->assertSessionDoesntHaveErrors();

        $offer->refresh()->load(['periods', 'roomTypes', 'prices']);
        $this->assertSame($number, $offer->offer_no);
        $this->assertSame(OfferStatus::Sent, $offer->status);
        $this->assertSame('Kasım 2026', $offer->periods->first()->label);
        $this->assertSame('TRIPLE', $offer->roomTypes->first()->name);
        $this->assertCount(1, $offer->prices);
        $this->assertSame('225.50', $offer->prices->first()->price);
    }

    #[Test]
    public function yetkiler_admin_patron_ve_personel_icin_uygulanir(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $patron = $this->userWithRole(RoleName::Patron);
        $personel = $this->userWithRole(RoleName::Personel);
        $this->actingAs($admin)->post(route('offers.store'), $this->payload())->assertSessionDoesntHaveErrors();
        $offer = Offer::query()->firstOrFail();

        $this->actingAs($patron)->get(route('offers.create'))->assertOk();
        $this->actingAs($patron)->get(route('offers.edit', $offer))->assertOk();
        $this->actingAs($patron)->get(route('offers.print', $offer))->assertOk();
        $this->actingAs($personel)->get(route('offers.index'))->assertForbidden();
        $this->get(route('offers.show', $offer))->assertForbidden();
        $this->get(route('offers.print', $offer))->assertForbidden();
        $this->get(route('offers.create'))->assertForbidden();
        $this->post(route('offers.store'), $this->payload())->assertForbidden();
        $this->get(route('offers.edit', $offer))->assertForbidden();
        $this->put(route('offers.update', $offer), $this->payload())->assertForbidden();
    }
}
