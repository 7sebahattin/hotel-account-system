<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Models\User;
use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Companies\Models\Company;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Firma detayındaki "Tahsilatlar" bölümü artık gerçek veriden okur. */
class CompanyPaymentsPanelTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        $this->company = Company::factory()->create(['name' => 'Monarch']);
        $this->admin = $this->userWithRole(RoleName::Admin);
    }

    #[Test]
    public function firma_detayinda_tahsilatlar_listelenir(): void
    {
        app(PaymentService::class)->create([
            'company_id' => $this->company->id,
            'payment_date' => '2026-09-08',
            'amount' => '5000.00',
            'currency' => 'TRY',
            'payment_method' => 'bank_transfer',
            'reference_no' => 'DEKONT-77',
        ], $this->admin);

        $this->actingAs($this->admin)
            ->get(route('companies.show', $this->company))
            ->assertOk()
            ->assertSee('Tahsilatlar')
            ->assertSee('DEKONT-77')
            ->assertSee('5.000,00 ₺')
            ->assertSee('Havale / EFT');
    }

    #[Test]
    public function tahsilati_olmayan_firmada_bos_durum_gosterilir(): void
    {
        $this->actingAs($this->admin)
            ->get(route('companies.show', $this->company))
            ->assertOk()
            ->assertSee('Bu firmaya ait tahsilat kaydı yok.');
    }

    #[Test]
    public function baska_firmanin_tahsilati_gosterilmez(): void
    {
        $other = Company::factory()->create(['name' => 'Başka Firma']);
        Payment::factory()->create(['company_id' => $other->id, 'reference_no' => 'BASKA-REF']);

        $this->actingAs($this->admin)
            ->get(route('companies.show', $this->company))
            ->assertOk()
            ->assertDontSee('BASKA-REF');
    }

    #[Test]
    public function sayac_firmanin_tum_tahsilatlarini_gosterir(): void
    {
        Payment::factory()->count(7)->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->admin)->get(route('companies.show', $this->company));

        $this->assertSame(7, $response->viewData('paymentCount'));
        $this->assertCount(5, $response->viewData('recentPayments'), 'Kartta yalnızca son 5 kayıt gösterilir');
    }

    /**
     * N+1 kontrolü: kart 5 tahsilat + 10 konaklama gösterirken sorgu sayısı
     * kayıt sayısıyla birlikte BÜYÜMEMELİ.
     */
    #[Test]
    public function firma_detayi_n_plus_bir_uretmez(): void
    {
        Payment::factory()->count(5)->create(['company_id' => $this->company->id]);

        // Isınma isteği: yetki önbelleği dolsun, ölçüme karışmasın.
        $this->actingAs($this->admin)->get(route('companies.show', $this->company))->assertOk();

        $measure = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->admin)->get(route('companies.show', $this->company))->assertOk();
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $count;
        };

        $withFive = $measure();

        // Fabrika INSERT'leri ölçüme karışmasın diye kayıtlar log KAPALIYKEN eklenir.
        Payment::factory()->count(5)->create(['company_id' => $this->company->id]);

        $withTen = $measure();

        $this->assertSame(
            $withFive,
            $withTen,
            "Kayıt sayısı artınca sorgu sayısı artmamalı ({$withFive} → {$withTen})",
        );
    }

    #[Test]
    public function tahsilat_ekle_baglantisi_firmayi_onceden_secer(): void
    {
        $this->actingAs($this->admin)
            ->get(route('companies.show', $this->company))
            ->assertOk()
            ->assertSee(route('payments.create', ['company_id' => $this->company->id]), false);

        $this->actingAs($this->admin)
            ->get(route('payments.create', ['company_id' => $this->company->id]))
            ->assertOk()
            ->assertSee('value="'.$this->company->id.'"', false)
            ->assertSee('selected', false);
    }

    #[Test]
    public function silinmis_firmada_tahsilat_ekle_gosterilmez(): void
    {
        $this->company->delete();

        // Not: sayfa genelindeki "+ Hızlı İşlem" widget'ı `payments.create`
        // yetkisi olan herkes için "Cari Tahsilat" metnini HER sayfada
        // gösterir (v2.1.1-B, firmadan bağımsız genel kısayol) — bu yüzden
        // burada FİRMAYA ÖZGÜ (company_id sorgu parametreli) bağlantının
        // yokluğu doğrulanır, genel metnin yokluğu değil.
        $this->actingAs($this->admin)
            ->get(route('companies.show', $this->company->id))
            ->assertOk()
            ->assertDontSee(route('payments.create', ['company_id' => $this->company->id]), false);
    }
}
