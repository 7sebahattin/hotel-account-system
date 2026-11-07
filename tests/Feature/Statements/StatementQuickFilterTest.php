<?php

declare(strict_types=1);

namespace Tests\Feature\Statements;

use App\Models\User;
use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Companies\Models\Company;
use App\Modules\Ledger\Support\StatementCriteria;
use App\Modules\Stays\Services\StayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cari ekstre hızlı dönem filtreleri (7 Kasım 2026): "Bu ay" / "Geçen ay" tarih aralığını
 * sunucuda belirler ve özet yalnızca CARİ BAKİYEYİ gösterir (ekran + yazdırma).
 */
class StatementQuickFilterTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Carbon::setTestNow(Carbon::create(2026, 11, 7, 12));

        $this->company = Company::factory()->create(['name' => 'Monarch']);
        $this->admin = $this->userWithRole(RoleName::Admin);

        // Eylül (önceki aylar), Ekim (geçen ay) ve Kasım (bu ay) için birer konaklama.
        $this->stay('Eylül Misafir', '2026-09-10', '2026-09-12', '1000.00');
        $this->stay('Ekim Misafir', '2026-10-10', '2026-10-12', '2000.00');
        $this->stay('Kasım Misafir', '2026-11-03', '2026-11-05', '4000.00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function stay(string $guest, string $in, string $out, string $amount): void
    {
        app(StayService::class)->create([
            'company_id' => $this->company->id,
            'guest_name' => $guest,
            'check_in_date' => $in,
            'check_out_date' => $out,
            'pricing_mode' => 'total',
            'amount' => $amount,
            'currency' => 'TRY',
        ], $this->admin);
    }

    #[Test]
    public function hizli_donem_tarih_araligini_sunucuda_belirler(): void
    {
        $thisMonth = StatementCriteria::fromRequest(Request::create('/', 'GET', ['hizli' => 'bu-ay']));
        $this->assertSame('2026-11-01', $thisMonth->dateFrom?->toDateString());
        $this->assertSame('2026-11-30', $thisMonth->dateTo?->toDateString());
        $this->assertTrue($thisMonth->isBalanceOnly());

        $lastMonth = StatementCriteria::fromRequest(Request::create('/', 'GET', ['hizli' => 'gecen-ay', 'date_from' => '2020-01-01']));
        $this->assertSame('2026-10-01', $lastMonth->dateFrom?->toDateString(), 'hizli, elle girilen tarihin önüne geçer');
        $this->assertSame('2026-10-31', $lastMonth->dateTo?->toDateString());

        $unknown = StatementCriteria::fromRequest(Request::create('/', 'GET', ['hizli' => 'sacma']));
        $this->assertNull($unknown->quick);
        $this->assertFalse($unknown->isBalanceOnly());
    }

    #[Test]
    public function hizli_filtre_ozeti_yalnizca_cari_bakiye_olarak_gosterir(): void
    {
        $page = $this->actingAs($this->admin)->get(route('statements.show', [$this->company, 'hizli' => 'bu-ay']))->assertOk();

        $page->assertSee('Cari bakiye')
            ->assertDontSee('Toplam borç')
            ->assertDontSee('Toplam alacak')
            ->assertSee('Kasım Misafir')
            ->assertDontSee('Ekim Misafir');

        // Bakiye devreden dahildir: 1000 + 2000 + 4000.
        $page->assertSee('7.000,00');
    }

    #[Test]
    public function hizli_filtre_olmadan_ayrintili_ozet_korunur(): void
    {
        $this->actingAs($this->admin)->get(route('statements.show', $this->company))
            ->assertOk()->assertSee('Toplam borç')->assertSee('Toplam alacak')->assertDontSee('Cari bakiye');
    }

    #[Test]
    public function yazdirma_da_yalnizca_cari_bakiyeyi_gosterir(): void
    {
        $this->actingAs($this->admin)->get(route('statements.print', [$this->company, 'hizli' => 'gecen-ay']))
            ->assertOk()
            ->assertSee('Cari bakiye')
            ->assertDontSee('Toplam borç')
            ->assertDontSee('Devreden bakiye')
            ->assertSee('Ekim Misafir');
    }
}
