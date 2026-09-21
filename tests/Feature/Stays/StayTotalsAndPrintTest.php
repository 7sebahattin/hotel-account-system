<?php

declare(strict_types=1);

namespace Tests\Feature\Stays;

use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Companies\Models\Company;
use App\Modules\Stays\Models\Stay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Konaklamalar listesindeki filtreye göre para birimi bazlı toplam ve
 * yazdırma görünümü. Para birimleri asla birleştirilmez (CLAUDE.md §6/L5);
 * iptal edilen tutarlar normal toplamdan ayrı ama görünür tutulur.
 */
class StayTotalsAndPrintTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    #[Test]
    public function toplam_para_birimi_bazinda_ayri_hesaplanir(): void
    {
        Stay::factory()->create(['currency' => 'TRY', 'amount' => '1000.00']);
        Stay::factory()->create(['currency' => 'TRY', 'amount' => '2000.00']);
        Stay::factory()->create(['currency' => 'EUR', 'amount' => '300.00']);

        $totals = $this->actingAs($this->userWithRole(RoleName::Admin))
            ->get(route('stays.index'))
            ->viewData('totals');

        $this->assertSame('3000.00', $totals['TRY']['total']->toDecimal());
        $this->assertSame(2, $totals['TRY']['count']);
        $this->assertSame('300.00', $totals['EUR']['total']->toDecimal());
        $this->assertSame(1, $totals['EUR']['count']);
    }

    #[Test]
    public function toplam_iptal_edilenleri_ayri_gosterir(): void
    {
        Stay::factory()->create(['currency' => 'TRY', 'amount' => '1000.00']);
        Stay::factory()->cancelled()->create(['currency' => 'TRY', 'amount' => '500.00']);

        $totals = $this->actingAs($this->userWithRole(RoleName::Admin))
            ->get(route('stays.index'))
            ->viewData('totals');

        $this->assertSame('1500.00', $totals['TRY']['total']->toDecimal(), 'Toplam, filtrelenen TÜM kayıtları kapsar');
        $this->assertSame('1000.00', $totals['TRY']['activeTotal']->toDecimal());
        $this->assertSame('500.00', $totals['TRY']['cancelledTotal']->toDecimal());
        $this->assertSame(1, $totals['TRY']['cancelledCount']);
    }

    #[Test]
    public function toplam_filtreye_gore_degisir(): void
    {
        $a = Company::factory()->create();
        $b = Company::factory()->create();
        Stay::factory()->create(['company_id' => $a->id, 'currency' => 'TRY', 'amount' => '1000.00']);
        Stay::factory()->create(['company_id' => $b->id, 'currency' => 'TRY', 'amount' => '9000.00']);

        $totals = $this->actingAs($this->userWithRole(RoleName::Admin))
            ->get(route('stays.index', ['company_id' => $a->id]))
            ->viewData('totals');

        $this->assertSame('1000.00', $totals['TRY']['total']->toDecimal());
    }

    #[Test]
    public function yazdirma_sayfasi_filtrelenmis_tum_kayitlari_sayfalamadan_gosterir(): void
    {
        $company = Company::factory()->create();
        Stay::factory()->count(30)->create(['company_id' => $company->id, 'currency' => 'TRY']);
        Stay::factory()->create(['currency' => 'TRY']); // başka firma, filtre dışı

        $response = $this->actingAs($this->userWithRole(RoleName::Admin))
            ->get(route('stays.print', ['company_id' => $company->id]));

        $response->assertOk();
        $this->assertCount(30, $response->viewData('stays'), 'Yazdırma sayfalanmaz, filtrelenen tüm kayıtları döner');
    }

    #[Test]
    public function yazdirma_yetkisiz_kullaniciya_kapalidir(): void
    {
        $user = $this->userWithRole(RoleName::Personel);
        $user->syncRoles([]);

        $this->actingAs($user->fresh())->get(route('stays.print'))->assertForbidden();
    }

    #[Test]
    public function yazdirma_sayfasi_herkese_ayni_stays_view_yetkisiyle_acik(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Personel))
            ->get(route('stays.print'))
            ->assertOk();
    }

    #[Test]
    public function yazdirma_islem_zamanini_gosterir_tarihleri_korur_ve_toplamlari_degistirmez(): void
    {
        Stay::factory()->create([
            'check_in_date' => '2026-09-08',
            'check_out_date' => '2026-09-09',
            'created_at' => '2026-09-08 15:42:00',
            'currency' => 'TRY',
            'amount' => '1250.00',
        ]);

        $response = $this->actingAs($this->userWithRole(RoleName::Admin))
            ->get(route('stays.print'))
            ->assertOk()
            ->assertSee('İşlem zamanı')
            ->assertSee('08.09.2026')
            ->assertSee('09.09.2026')
            ->assertSee('08.09.2026 15:42');

        $totals = $response->viewData('totals');
        $this->assertSame('1250.00', $totals['TRY']['total']->toDecimal());
        $this->assertSame(1, $totals['TRY']['count']);
    }
}
