<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Models\User;
use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Companies\Models\Company;
use App\Modules\Payments\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentListingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        $this->admin = $this->userWithRole(RoleName::Admin);
    }

    /** @return array<int, string|null> */
    private function references(TestResponse $response): array
    {
        return $response->viewData('payments')->pluck('reference_no')->all();
    }

    #[Test]
    public function referans_no_ile_arama_yapilir(): void
    {
        Payment::factory()->create(['reference_no' => 'DEKONT-001']);
        Payment::factory()->create(['reference_no' => 'SLIP-002']);

        $response = $this->actingAs($this->admin)->get(route('payments.index', ['q' => 'dekont']));

        $this->assertSame(['DEKONT-001'], $this->references($response));
    }

    #[Test]
    public function aciklama_ile_arama_yapilir(): void
    {
        Payment::factory()->create(['reference_no' => 'A', 'description' => 'Eylül ayı ödemesi']);
        Payment::factory()->create(['reference_no' => 'B', 'description' => 'Ekim avansı']);

        $response = $this->actingAs($this->admin)->get(route('payments.index', ['q' => 'eylül']));

        $this->assertSame(['A'], $this->references($response));
    }

    #[Test]
    public function firmaya_gore_filtrelenir(): void
    {
        $a = Company::factory()->create(['name' => 'A Firma']);
        $b = Company::factory()->create(['name' => 'B Firma']);

        Payment::factory()->create(['company_id' => $a->id, 'reference_no' => 'A-REF']);
        Payment::factory()->create(['company_id' => $b->id, 'reference_no' => 'B-REF']);

        $response = $this->actingAs($this->admin)->get(route('payments.index', ['company_id' => $a->id]));

        $this->assertSame(['A-REF'], $this->references($response));
    }

    #[Test]
    public function tarih_araligina_gore_filtrelenir(): void
    {
        Payment::factory()->create(['reference_no' => 'OCAK', 'payment_date' => '2026-01-15']);
        Payment::factory()->create(['reference_no' => 'MART', 'payment_date' => '2026-03-10']);
        Payment::factory()->create(['reference_no' => 'HAZIRAN', 'payment_date' => '2026-06-01']);

        $response = $this->actingAs($this->admin)->get(route('payments.index', [
            'date_from' => '2026-02-01',
            'date_to' => '2026-04-30',
        ]));

        $this->assertSame(['MART'], $this->references($response));
    }

    #[Test]
    public function para_birimine_gore_filtrelenir(): void
    {
        Payment::factory()->create(['reference_no' => 'TRY-REF', 'currency' => 'TRY']);
        Payment::factory()->create(['reference_no' => 'EUR-REF', 'currency' => 'EUR']);

        $response = $this->actingAs($this->admin)->get(route('payments.index', ['currency' => 'EUR']));

        $this->assertSame(['EUR-REF'], $this->references($response));
    }

    #[Test]
    public function odeme_yontemine_gore_filtrelenir(): void
    {
        Payment::factory()->create(['reference_no' => 'NAKIT', 'payment_method' => 'cash']);
        Payment::factory()->create(['reference_no' => 'HAVALE', 'payment_method' => 'bank_transfer']);

        $response = $this->actingAs($this->admin)->get(route('payments.index', ['payment_method' => 'cash']));

        $this->assertSame(['NAKIT'], $this->references($response));
    }

    #[Test]
    public function duruma_gore_filtrelenir(): void
    {
        Payment::factory()->create(['reference_no' => 'AKTIF']);
        Payment::factory()->cancelled()->create(['reference_no' => 'IPTAL']);

        $response = $this->actingAs($this->admin)->get(route('payments.index', ['status' => 'cancelled']));

        $this->assertSame(['IPTAL'], $this->references($response));
    }

    #[Test]
    public function varsayilan_siralama_en_yeni_tarih_ustte(): void
    {
        Payment::factory()->create(['reference_no' => 'ESKI', 'payment_date' => '2026-01-01']);
        Payment::factory()->create(['reference_no' => 'YENI', 'payment_date' => '2026-06-01']);

        $response = $this->actingAs($this->admin)->get(route('payments.index'));

        $this->assertSame(['YENI', 'ESKI'], $this->references($response));
    }

    #[Test]
    public function tutara_gore_siralanabilir(): void
    {
        Payment::factory()->create(['reference_no' => 'KUCUK', 'amount' => '100.00']);
        Payment::factory()->create(['reference_no' => 'BUYUK', 'amount' => '9000.00']);

        $response = $this->actingAs($this->admin)
            ->get(route('payments.index', ['sort' => 'amount', 'direction' => 'asc']));

        $this->assertSame(['KUCUK', 'BUYUK'], $this->references($response));
    }

    #[Test]
    public function beyaz_liste_disi_siralama_alani_yok_sayilir(): void
    {
        Payment::factory()->count(2)->create();

        $this->actingAs($this->admin)
            ->get(route('payments.index', ['sort' => 'created_by; DROP TABLE payments']))
            ->assertOk();

        $this->assertSame(2, Payment::query()->count());
    }

    #[Test]
    public function varsayilan_sayfa_boyutu_25(): void
    {
        Payment::factory()->count(30)->create();

        $response = $this->actingAs($this->admin)->get(route('payments.index'));

        $this->assertCount(25, $response->viewData('payments')->items());
    }

    #[Test]
    public function sayfa_boyutu_secilebilir(): void
    {
        Payment::factory()->count(30)->create();

        $response = $this->actingAs($this->admin)->get(route('payments.index', ['per_page' => 50]));

        $this->assertCount(30, $response->viewData('payments')->items());
    }

    #[Test]
    public function izinsiz_sayfa_boyutu_varsayilana_duser(): void
    {
        Payment::factory()->count(30)->create();

        $response = $this->actingAs($this->admin)->get(route('payments.index', ['per_page' => 999]));

        $this->assertCount(25, $response->viewData('payments')->items());
    }

    #[Test]
    public function filtreler_sayfalama_baglantilarinda_korunur(): void
    {
        $company = Company::factory()->create(['name' => 'Monarch']);
        Payment::factory()->count(30)->create(['company_id' => $company->id]);

        $this->actingAs($this->admin)
            ->get(route('payments.index', ['company_id' => $company->id]))
            ->assertOk()
            ->assertSee('company_id='.$company->id, false);
    }

    /** Liste ilişkileri eager-load eder; lazy loading kapalı olduğu için N+1 patlar. */
    #[Test]
    public function liste_n_plus_bir_uretmez(): void
    {
        Payment::factory()->count(5)->create();

        $this->actingAs($this->admin)->get(route('payments.index'))->assertOk();
    }
}
