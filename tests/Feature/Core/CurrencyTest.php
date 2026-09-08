<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Companies\Models\Company;
use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
use App\Modules\Dashboard\Services\DashboardService;
use App\Modules\Ledger\Services\LedgerService;
use App\Modules\Ledger\Services\LedgerStatementService;
use App\Modules\Ledger\Support\StatementCriteria;
use App\Modules\Payments\Services\PaymentService;
use App\Modules\Stays\Services\StayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

/**
 * Gün 9.6 — çoklu para birimi mimarisi.
 *
 * Amaç: iş modelinin yalnızca TRY/EUR/GBP bildiği varsayımına son vermek.
 * `Currency` enum TEKNİK geçerlilik kümesidir (config/hotel.php'deki yorum);
 * bu test hem yeni kodların (USD/RUB/GEL) uçtan uca çalıştığını hem de
 * mevcut TRY/EUR/GBP davranışının bozulmadığını, hem de "yalnızca üç kod"
 * varsayımının koddan tamamen silindiğini doğrular.
 */
class CurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    // --------------------------------------------------- enum / config bütünlüğü

    #[Test]
    public function ilk_destek_listesindeki_alti_para_birimi_de_enumda_mevcut(): void
    {
        // CLAUDE.md ilk destek listesi: TRY, EUR, GBP, USD, RUB, GEL.
        foreach (['TRY', 'EUR', 'GBP', 'USD', 'RUB', 'GEL'] as $code) {
            $this->assertContains($code, Currency::values(), "{$code} enum'da yok.");
        }
    }

    #[Test]
    public function config_currencies_listesi_enumun_alt_kumesidir(): void
    {
        // İş kararı (config) teknik kümeyi (enum) GENİŞLETEMEZ — yalnızca
        // daraltabilir/sıralayabilir. Bu, config'e yanlışlıkla geçersiz bir
        // ISO kodu yazılırsa CI'da hemen görülmesini garanti eder.
        $configured = config('hotel.currencies');
        $this->assertIsArray($configured);

        foreach ($configured as $code) {
            $this->assertNotNull(
                Currency::tryFrom($code),
                "config('hotel.currencies') içindeki '{$code}' Currency enum'unda yok."
            );
        }
    }

    #[Test]
    public function active_config_sirasini_yansitir(): void
    {
        $this->assertSame(
            ['TRY', 'EUR', 'GBP', 'USD', 'RUB', 'GEL'],
            array_map(fn (Currency $c) => $c->value, Currency::active()),
        );
    }

    #[Test]
    public function active_gecersiz_config_kodunu_sessizce_atlar(): void
    {
        config(['hotel.currencies' => ['TRY', 'NOTREAL', 'EUR']]);

        $this->assertSame(['TRY', 'EUR'], array_map(fn (Currency $c) => $c->value, Currency::active()));
    }

    #[Test]
    public function active_config_bossa_tum_kumeye_duser(): void
    {
        config(['hotel.currencies' => []]);

        $this->assertSame(Currency::cases(), Currency::active());
    }

    #[Test]
    public function tum_para_birimleri_iki_ondalik_varsayar(): void
    {
        // Money hâlâ sabit × 100 (kuruş) varsayımıyla çalışıyor (CLAUDE.md §13).
        // Bu, ancak desteklenen TÜM para birimleri 2 ondalıklıysa güvenlidir —
        // ISO 4217'de JPY (0) veya BHD (3) gibi istisnalar vardır. Bu test o
        // varsayımı adlandırır: biri false dönerse Money'nin genelleştirilmesi
        // gerektiği anlamına gelir, sessizce yanlış hesap üretmez.
        foreach (Currency::cases() as $currency) {
            $this->assertSame(2, $currency->decimalPlaces(), "{$currency->value} 2 ondalıklı değil.");
        }
    }

    #[Test]
    public function desteklenmeyen_kod_reddedilir(): void
    {
        $this->assertNotContains('CHF', Currency::values());
        $this->assertNotContains('JPY', Currency::values());
        $this->assertNotContains('XYZ', Currency::values());
    }

    // ------------------------------------------------------------ sembol/etiket

    #[Test]
    public function her_para_biriminin_etiketi_ve_sembolu_vardir(): void
    {
        foreach (Currency::cases() as $currency) {
            $this->assertNotSame('', $currency->label());
            $this->assertNotSame('', $currency->symbol());
        }
    }

    #[Test]
    public function az_taninan_semboller_iso_kodunu_dogrudan_kullanir(): void
    {
        // CLAUDE.md §7: sembole güvenilmez; RUB/GEL için sembol yerine ISO
        // kod gösterilir ("35.000,00 RUB" gibi — kullanıcı örneğiyle birebir).
        $this->assertSame('RUB', Currency::RUB->symbol());
        $this->assertSame('GEL', Currency::GEL->symbol());

        $formatted = Money::fromDecimal('35000.00')->format(Currency::RUB);
        $this->assertSame('35.000,00 RUB', $formatted);
    }

    // ---------------------------------------------------------- ledger uçtan uca

    #[TestWith([Currency::USD])]
    #[TestWith([Currency::RUB])]
    #[TestWith([Currency::GEL])]
    #[TestWith([Currency::TRY])]
    #[TestWith([Currency::EUR])]
    #[TestWith([Currency::GBP])]
    #[Test]
    public function her_para_biriminde_cari_hareket_olusturulabilir(Currency $currency): void
    {
        $company = Company::factory()->create();
        $actor = $this->userWithRole(RoleName::Admin);
        $ledger = app(LedgerService::class);

        $entry = $ledger->debit(
            companyId: $company->id,
            amount: Money::fromDecimal('1000.00'),
            currency: $currency,
            entryDate: '2026-09-01',
            description: 'Test',
            sourceType: 'stay',
            sourceId: 1,
            actor: $actor,
        );

        $this->assertSame($currency, $entry->currency);
        $this->assertSame(
            '1000.00',
            $ledger->balanceForCompany($company->id, $currency)->toDecimal(),
        );
    }

    #[Test]
    public function rub_ve_gel_konaklama_ve_tahsilat_ile_uctan_uca_calisir(): void
    {
        $company = Company::factory()->create();
        $actor = $this->userWithRole(RoleName::Admin);

        $stay = app(StayService::class)->create([
            'company_id' => $company->id,
            'guest_name' => 'Rusça Misafir',
            'check_in_date' => '2026-09-01',
            'check_out_date' => '2026-09-03',
            'pricing_mode' => 'total',
            'amount' => '25000.00',
            'currency' => 'RUB',
        ], $actor);

        $payment = app(PaymentService::class)->create([
            'company_id' => $company->id,
            'payment_date' => '2026-09-02',
            'amount' => '450.00',
            'currency' => 'GEL',
        ], $actor);

        $this->assertSame(Currency::RUB, $stay->currency);
        $this->assertSame(Currency::GEL, $payment->currency);

        $ledger = app(LedgerService::class);
        $this->assertSame('25000.00', $ledger->balanceForCompany($company->id, Currency::RUB)->toDecimal());
        $this->assertSame('-450.00', $ledger->balanceForCompany($company->id, Currency::GEL)->toDecimal());
    }

    #[Test]
    public function farkli_para_birimleri_birbirine_eklenmez(): void
    {
        $company = Company::factory()->create();
        $actor = $this->userWithRole(RoleName::Admin);
        $ledger = app(LedgerService::class);

        $ledger->debit(
            companyId: $company->id, amount: Money::fromDecimal('1000.00'), currency: Currency::TRY,
            entryDate: '2026-09-01', description: 'TRY', sourceType: 'stay', sourceId: 1, actor: $actor,
        );
        $ledger->debit(
            companyId: $company->id, amount: Money::fromDecimal('1000.00'), currency: Currency::USD,
            entryDate: '2026-09-01', description: 'USD', sourceType: 'stay', sourceId: 2, actor: $actor,
        );
        $ledger->debit(
            companyId: $company->id, amount: Money::fromDecimal('1000.00'), currency: Currency::RUB,
            entryDate: '2026-09-01', description: 'RUB', sourceType: 'stay', sourceId: 3, actor: $actor,
        );

        $balances = $ledger->balancesForCompanyByCurrency($company->id);

        $this->assertSame('1000.00', $balances['TRY']->toDecimal());
        $this->assertSame('1000.00', $balances['USD']->toDecimal());
        $this->assertSame('1000.00', $balances['RUB']->toDecimal());

        // Hiçbir yerde 3000 (1000+1000+1000) gibi birleşik bir değer üretilmez.
        foreach ($balances as $balance) {
            $this->assertNotSame('3000.00', $balance->toDecimal());
        }
    }

    // --------------------------------------------------------------- ekstre

    #[Test]
    public function cari_ekstre_yeni_para_birimini_ayri_grup_olarak_gosterir(): void
    {
        $company = Company::factory()->create();
        $actor = $this->userWithRole(RoleName::Admin);
        $ledger = app(LedgerService::class);

        $ledger->debit(
            companyId: $company->id, amount: Money::fromDecimal('5000.00'), currency: Currency::USD,
            entryDate: '2026-09-01', description: 'Konaklama', sourceType: 'stay', sourceId: 1, actor: $actor,
        );

        $statement = app(LedgerStatementService::class)->forCompany(
            $company->id,
            new StatementCriteria,
        );

        $usdRows = array_filter($statement->rows, fn ($row) => $row->currency() === Currency::USD);
        $this->assertCount(1, $usdRows);
    }

    // -------------------------------------------------------------- dashboard

    #[Test]
    public function dashboard_yeni_para_birimlerini_ayri_ayri_gosterir(): void
    {
        $company = Company::factory()->create(['name' => 'Rus Turizm']);
        $actor = $this->userWithRole(RoleName::Admin);
        $ledger = app(LedgerService::class);

        $ledger->debit(
            companyId: $company->id, amount: Money::fromDecimal('12000.00'), currency: Currency::RUB,
            entryDate: '2026-09-01', description: 'Konaklama', sourceType: 'stay', sourceId: 1, actor: $actor,
        );

        $summary = app(DashboardService::class)->finance(Carbon::create(2026, 9, 15));

        $codes = array_map(fn ($t) => $t->currency->value, $summary->currencies);
        $this->assertContains('RUB', $codes);

        foreach ($summary->currencies as $totals) {
            if ($totals->currency === Currency::RUB) {
                $this->assertSame('12000.00', $totals->receivable->toDecimal());
            }
        }
    }

    // -------------------------------------------------------------- formlar

    #[Test]
    public function konaklama_formu_yeni_para_birimlerini_sunar(): void
    {
        $company = Company::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::Admin))
            ->get(route('stays.create', ['company_id' => $company->id]))
            ->assertOk()
            ->assertSee('USD — ABD Doları')
            ->assertSee('RUB — Rus Rublesi')
            ->assertSee('GEL — Gürcistan Larisi');
    }

    #[Test]
    public function tahsilat_formu_yeni_para_birimlerini_sunar(): void
    {
        $company = Company::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::Admin))
            ->get(route('payments.create', ['company_id' => $company->id]))
            ->assertOk()
            ->assertSee('USD — ABD Doları')
            ->assertSee('RUB — Rus Rublesi')
            ->assertSee('GEL — Gürcistan Larisi');
    }

    #[Test]
    public function usd_ile_konaklama_formdan_kaydedilebilir(): void
    {
        $company = Company::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::Admin))->post(route('stays.store'), [
            'company_id' => $company->id,
            'guest_name' => 'Dolarlı Misafir',
            'check_in_date' => '2026-09-01',
            'check_out_date' => '2026-09-03',
            'pricing_mode' => 'total',
            'amount' => '500,00',
            'currency' => 'USD',
        ])->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('stays', ['guest_name' => 'Dolarlı Misafir', 'currency' => 'USD']);
    }

    // ----------------------------------------------------------- veri güvenliği

    #[Test]
    public function veritabani_herhangi_bir_uc_harfli_kodu_kabul_eder(): void
    {
        // Şema düzeyinde doğrulama: currency kolonları CHAR(3)'tür ve hiçbir
        // CHECK kısıtı belirli kodlarla sınırlamaz (audit ile doğrulandı).
        // Bu test, gelecekte biri yanlışlıkla bir CHECK eklerse kırılır.
        $company = Company::factory()->create();

        DB::table('ledger_entries')->insert([
            'company_id' => $company->id,
            'entry_date' => '2026-09-01',
            'description' => 'Şema testi',
            'debit' => 10,
            'credit' => 0,
            'currency' => 'GEL',
            'is_reversal' => false,
            'created_by' => $this->userWithRole(RoleName::Admin)->id,
            'created_at' => now(),
        ]);

        $this->assertDatabaseHas('ledger_entries', ['currency' => 'GEL']);
    }

    #[Test]
    public function try_eur_gbp_mevcut_davranisi_bozulmadi(): void
    {
        // Gün 9.6 öncesi davranışın regresyon güvencesi.
        $company = Company::factory()->create();
        $actor = $this->userWithRole(RoleName::Admin);
        $ledger = app(LedgerService::class);

        foreach ([Currency::TRY, Currency::EUR, Currency::GBP] as $currency) {
            $ledger->debit(
                companyId: $company->id, amount: Money::fromDecimal('100.00'), currency: $currency,
                entryDate: '2026-09-01', description: 'Regresyon', sourceType: 'stay', sourceId: 1, actor: $actor,
            );
        }

        $balances = $ledger->balancesForCompanyByCurrency($company->id);
        $this->assertSame('100.00', $balances['TRY']->toDecimal());
        $this->assertSame('100.00', $balances['EUR']->toDecimal());
        $this->assertSame('100.00', $balances['GBP']->toDecimal());

        $this->assertSame('₺', Currency::TRY->symbol());
        $this->assertSame('€', Currency::EUR->symbol());
        $this->assertSame('£', Currency::GBP->symbol());
    }
}
