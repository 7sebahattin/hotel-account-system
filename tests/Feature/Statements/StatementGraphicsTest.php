<?php

declare(strict_types=1);

namespace Tests\Feature\Statements;

use App\Models\User;
use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Companies\Models\Company;
use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
use App\Modules\Ledger\Services\LedgerService;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StatementGraphicsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-22 12:00:00');
        $this->seedRoles();
        $this->admin = $this->userWithRole(RoleName::Admin);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function grafik_rotalari_auth_ve_ledger_view_all_yetkisini_korur(): void
    {
        $this->get(route('statements.graphics'))->assertRedirect(route('login'));
        $this->get(route('statements.graphics-print'))->assertRedirect(route('login'));

        $personel = $this->userWithRole(RoleName::Personel);

        $this->actingAs($personel)->get(route('statements.graphics'))->assertForbidden();
        $this->actingAs($personel)->get(route('statements.graphics-print'))->assertForbidden();

        $this->actingAs($this->admin)->get(route('statements.graphics'))->assertOk();
        $this->actingAs($this->admin)->get(route('statements.graphics-print'))->assertOk();
    }

    #[Test]
    public function grafik_menu_linki_yalniz_yetkili_kullaniciya_gorunur_ve_aktiflik_cakismasi_yoktur(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Personel))
            ->get(route('home'))
            ->assertOk()
            ->assertDontSee(route('statements.graphics'), false);

        $response = $this->actingAs($this->admin)->get(route('statements.graphics'));
        $response->assertOk()->assertSee(route('statements.graphics'), false);

        $dom = new DOMDocument;
        @$dom->loadHTML((string) $response->getContent());
        $xpath = new DOMXPath($dom);

        $graphics = $xpath->query('//nav[@aria-label="Ana menü"]//a[@href="'.route('statements.graphics').'"]');
        $statement = $xpath->query('//nav[@aria-label="Ana menü"]//a[@href="'.route('statements.index').'"]');

        $this->assertNotFalse($graphics);
        $this->assertNotFalse($statement);
        $this->assertSame(1, $graphics->length);
        $this->assertSame(1, $statement->length);

        $graphicsLink = $graphics->item(0);
        $statementLink = $statement->item(0);
        $this->assertInstanceOf(DOMElement::class, $graphicsLink);
        $this->assertInstanceOf(DOMElement::class, $statementLink);
        $this->assertSame('page', $graphicsLink->getAttribute('aria-current'));
        $this->assertSame('', $statementLink->getAttribute('aria-current'));
    }

    #[Test]
    public function yalniz_pozitif_bakiyeler_doviz_bazinda_ayri_ve_buyukten_kucuge_gosterilir(): void
    {
        $alpha = Company::factory()->create(['name' => 'Alpha Turizm']);
        $beta = Company::factory()->create(['name' => 'Beta Travel']);
        $creditor = Company::factory()->create(['name' => 'Credit Hotel']);
        $closed = Company::factory()->create(['name' => 'Closed Cari']);

        $this->debit($alpha, '1000.00', Currency::TRY, '2026-09-21');
        $this->debit($alpha, '300.00', Currency::EUR, '2026-09-21');
        $this->debit($beta, '2000.00', Currency::TRY, '2026-07-01');
        $this->credit($creditor, '700.00', Currency::EUR, '2026-09-10');
        $this->debit($closed, '100.00', Currency::TRY, '2026-09-15');
        $this->credit($closed, '100.00', Currency::TRY, '2026-09-16');

        $response = $this->actingAs($this->admin)->get(route('statements.graphics'));
        $response->assertOk()
            ->assertSee('data-unique-debtor-count="2"', false)
            ->assertSee('data-currency-group="TRY"', false)
            ->assertSee('data-currency-group="EUR"', false)
            ->assertSee('2.000,00 ₺')
            ->assertSee('1.000,00 ₺')
            ->assertSee('300,00 €')
            ->assertDontSee('Credit Hotel')
            ->assertDontSee('Closed Cari');

        $html = (string) $response->getContent();
        $betaPosition = strpos($html, 'data-debtor-bar="TRY:'.$beta->id.'"');
        $alphaPosition = strpos($html, 'data-debtor-bar="TRY:'.$alpha->id.'"');

        $this->assertNotFalse($betaPosition);
        $this->assertNotFalse($alphaPosition);
        $this->assertLessThan($alphaPosition, $betaPosition);

        // Tekil borçlu sayısı Alpha'nın TRY + EUR borcuyla iki kez artmamalı.
        $response->assertSee('data-activity-bucket="0" data-count="1"', false);
        $response->assertSee('data-activity-bucket="1" data-count="1"', false);
    }

    #[Test]
    public function arama_ve_para_birimi_filtreleri_tum_dashboardu_ve_printi_suzer(): void
    {
        $alpha = Company::factory()->create(['name' => 'Alpha Turizm']);
        $beta = Company::factory()->create(['name' => 'Beta Travel']);

        $this->debit($alpha, '1000.00', Currency::TRY, '2026-09-20');
        $this->debit($alpha, '300.00', Currency::EUR, '2026-09-20');
        $this->debit($beta, '2000.00', Currency::TRY, '2026-09-20');

        $this->actingAs($this->admin)
            ->get(route('statements.graphics', ['q' => 'Beta']))
            ->assertOk()
            ->assertSee('Beta Travel')
            ->assertDontSee('Alpha Turizm')
            ->assertSee('data-unique-debtor-count="1"', false);

        $this->actingAs($this->admin)
            ->get(route('statements.graphics', ['currency' => 'EUR']))
            ->assertOk()
            ->assertSee('Alpha Turizm')
            ->assertDontSee('Beta Travel')
            ->assertSee('data-currency-group="EUR"', false)
            ->assertDontSee('data-currency-group="TRY"', false);

        $this->actingAs($this->admin)
            ->get(route('statements.graphics-print', ['q' => 'Alpha', 'currency' => 'EUR']))
            ->assertOk()
            ->assertSee('Cari: Alpha · Para birimi: EUR')
            ->assertSee('Alpha Turizm')
            ->assertSee('300,00 €')
            ->assertDontSee('Beta Travel');
    }

    #[Test]
    public function print_grafik_cubuklari_arka_plan_grafikleri_ayarindan_bagimsiz_svg_olarak_render_edilir(): void
    {
        $company = Company::factory()->create(['name' => 'Yazdirma Grafik Firma']);
        $this->debit($company, '1250.00', Currency::TRY, '2026-09-20');

        $this->actingAs($this->admin)
            ->get(route('statements.graphics-print', ['currency' => 'TRY']))
            ->assertOk()
            ->assertSee('class="graphics-bar-svg"', false)
            ->assertSee('fill="currentColor"', false)
            ->assertSee('width="100"', false)
            ->assertDontSee('graphics-bar-fill', false)
            ->assertDontSee('background: currentColor', false);
    }

    #[Test]
    public function print_sayfalama_uygulamadan_tum_eslesen_borclulari_yazdirir(): void
    {
        foreach (range(1, 28) as $index) {
            $company = Company::factory()->create(['name' => sprintf('Grafik Firma %02d', $index)]);
            $this->debit($company, (string) (100 + $index).'.00', Currency::TRY, '2026-09-20');
        }

        $this->actingAs($this->admin)
            ->get(route('statements.graphics-print', ['currency' => 'TRY']))
            ->assertOk()
            ->assertSee('Grafik Firma 01')
            ->assertSee('Grafik Firma 28')
            ->assertSee('28 cari');
    }

    #[Test]
    public function borclu_yokken_guvenli_bos_durum_render_edilir_ve_mevcut_ekstre_bozulmaz(): void
    {
        $company = Company::factory()->create(['name' => 'Alacaklı Cari']);
        $this->credit($company, '500.00', Currency::TRY, '2026-09-20');

        $this->actingAs($this->admin)
            ->get(route('statements.graphics'))
            ->assertOk()
            ->assertSee('Bu filtrelerle borçlu cari bulunamadı.')
            ->assertDontSee('data-currency-group=', false);

        $this->actingAs($this->admin)
            ->get(route('statements.index'))
            ->assertOk()
            ->assertSee('Cari Ekstre')
            ->assertSee('Alacaklı Cari');
    }

    private function debit(Company $company, string $amount, Currency $currency, string $date): void
    {
        app(LedgerService::class)->debit(
            companyId: $company->id,
            amount: Money::fromDecimal($amount),
            currency: $currency,
            entryDate: $date,
            description: 'Grafik test borcu',
            actor: $this->admin,
        );
    }

    private function credit(Company $company, string $amount, Currency $currency, string $date): void
    {
        app(LedgerService::class)->credit(
            companyId: $company->id,
            amount: Money::fromDecimal($amount),
            currency: $currency,
            entryDate: $date,
            description: 'Grafik test alacağı',
            actor: $this->admin,
        );
    }
}
