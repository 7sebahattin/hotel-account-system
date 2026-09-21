<?php

declare(strict_types=1);

namespace Tests\Feature\Statements;

use App\Models\User;
use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Companies\Models\Company;
use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
use App\Modules\Ledger\Models\LedgerEntry;
use App\Modules\Ledger\Services\LedgerService;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Services\PaymentService;
use App\Modules\Stays\Models\Stay;
use App\Modules\Stays\Services\StayService;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StatementScreenTest extends TestCase
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

    private function seedMovements(): void
    {
        app(StayService::class)->create([
            'company_id' => $this->company->id,
            'guest_name' => 'John Smith',
            'check_in_date' => '2026-08-29',
            'check_out_date' => '2026-09-01',
            'pricing_mode' => 'total',
            'amount' => '9000.00',
            'currency' => 'TRY',
        ], $this->admin);

        app(PaymentService::class)->create([
            'company_id' => $this->company->id,
            'payment_date' => '2026-09-02',
            'amount' => '5000.00',
            'currency' => 'TRY',
            'payment_method' => 'bank_transfer',
        ], $this->admin);
    }

    // ------------------------------------------------------------- yetkiler

    #[Test]
    #[DataProvider('yetkiliRoller')]
    public function yetkili_roller_ekstreyi_gorur(string $role): void
    {
        $this->seedMovements();

        $this->actingAs($this->userWithRole(RoleName::from($role)))
            ->get(route('statements.show', $this->company))
            ->assertOk()
            ->assertSee('Cari ekstre')
            ->assertSee('9.000,00 ₺')
            ->assertSee('4.000,00 ₺');

        $this->actingAs($this->userWithRole(RoleName::from($role)))
            ->get(route('statements.index'))
            ->assertOk()
            ->assertSee('Monarch');
    }

    /** @return array<string, array{string}> */
    public static function yetkiliRoller(): array
    {
        return ['admin' => ['admin'], 'patron' => ['patron']];
    }

    #[Test]
    public function personel_ekstre_ekranlarina_erisemez(): void
    {
        $personel = $this->userWithRole(RoleName::Personel);

        $this->actingAs($personel)->get(route('statements.index'))->assertForbidden();
        $this->actingAs($personel)->get(route('statements.print-index'))->assertForbidden();
        $this->actingAs($personel)->get(route('statements.show', $this->company))->assertForbidden();
        $this->actingAs($personel)->get(route('statements.print', $this->company))->assertForbidden();
    }

    #[Test]
    public function personel_menude_cari_ekstre_linkini_gormez(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Personel))
            ->get(route('home'))
            ->assertOk()
            ->assertDontSee(route('statements.index'));

        $this->actingAs($this->admin)
            ->get(route('home'))
            ->assertOk()
            ->assertSee(route('statements.index'));
    }

    #[Test]
    public function personel_firma_detayinda_ekstre_butonunu_gormez(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Personel))
            ->get(route('companies.show', $this->company))
            ->assertOk()
            ->assertDontSee('Cari Ekstre');

        $this->actingAs($this->admin)
            ->get(route('companies.show', $this->company))
            ->assertOk()
            ->assertSee('Cari Ekstre')
            ->assertSee(route('statements.show', $this->company), false);
    }

    #[Test]
    public function giris_yapmamis_kullanici_erisemez(): void
    {
        $this->get(route('statements.index'))->assertRedirect(route('login'));
        $this->get(route('statements.print-index'))->assertRedirect(route('login'));
        $this->get(route('statements.show', $this->company))->assertRedirect(route('login'));
    }

    // ------------------------------------------------------------------ UI

    #[Test]
    public function ekstre_listesi_masaustu_satiri_erisilebilir_ve_tiklanabilirdir(): void
    {
        $response = $this->actingAs($this->admin)->get(route('statements.index'));

        $dom = new DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new DOMXPath($dom);
        $rows = $xpath->query('//table//tbody/tr[@data-statement-row-url]');

        $this->assertNotFalse($rows);
        $this->assertSame(1, $rows->length);
        $row = $rows->item(0);
        $this->assertInstanceOf(DOMElement::class, $row);
        $this->assertSame(route('statements.show', $this->company), $row->getAttribute('data-statement-row-url'));
        $this->assertSame('0', $row->getAttribute('tabindex'));
        $this->assertSame('link', $row->getAttribute('role'));
        $this->assertSame(3, $xpath->query('//table//thead/tr/*')->length);
        $this->assertSame(0, $xpath->query('//table//tbody/tr[@data-statement-row-url]//a')->length);
        $this->assertSame(0, $xpath->query('//table//tbody/tr[@data-statement-row-url]//button')->length);
        $this->assertSame(1, $xpath->query('//div[contains(@class, "lg:hidden")]//a[@href="'.route('statements.show', $this->company).'"]')->length);
    }

    #[Test]
    public function sidebar_release_surumu_merkezi_config_kaynagindan_render_edilir(): void
    {
        $this->actingAs($this->admin)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Cari Takip')
            ->assertSee('v1');

        config()->set('release.version', 42);

        $this->actingAs($this->admin)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Cari Takip')
            ->assertSee('v42');
    }

    #[Test]
    public function ekstre_islem_turlerini_turkce_gosterir_ham_source_type_gostermez(): void
    {
        $this->seedMovements();

        $response = $this->actingAs($this->admin)->get(route('statements.show', $this->company));

        $response->assertOk()
            ->assertSee('Konaklama')
            ->assertSee('Tahsilat')
            ->assertDontSee('>stay<', false)
            ->assertDontSee('>payment<', false);
    }

    #[Test]
    public function iptal_edilmis_tahsilat_ekstrede_acikca_gorunur(): void
    {
        $this->seedMovements();
        $payment = Payment::query()->firstOrFail();
        app(PaymentService::class)->cancel($payment, 'Mükerrer', $this->admin);

        $this->actingAs($this->admin)
            ->get(route('statements.show', $this->company))
            ->assertOk()
            ->assertSee('İptal / Ters Kayıt')
            ->assertSee('[İPTAL]');
    }

    #[Test]
    public function ozet_kartlari_bakiye_anlamini_metinle_de_yazar(): void
    {
        $this->seedMovements();

        $this->actingAs($this->admin)
            ->get(route('statements.show', $this->company))
            ->assertOk()
            ->assertSee('Firma bize borçlu')
            ->assertSee('Toplam borç')
            ->assertSee('Toplam alacak');
    }

    #[Test]
    public function farkli_para_birimleri_ayri_kartlarda_gosterilir(): void
    {
        $ledger = app(LedgerService::class);

        foreach ([['9000.00', Currency::TRY], ['500.00', Currency::EUR], ['200.00', Currency::GBP]] as [$amount, $currency]) {
            $ledger->debit(
                companyId: $this->company->id,
                amount: Money::fromDecimal($amount),
                currency: $currency,
                entryDate: '2026-09-01',
                description: 'Konaklama',
                sourceType: 'stay',
                sourceId: 1,
                actor: $this->admin,
            );
        }

        $this->actingAs($this->admin)
            ->get(route('statements.show', $this->company))
            ->assertOk()
            ->assertSee('9.000,00 ₺')
            ->assertSee('500,00 €')
            ->assertSee('200,00 £');
    }

    #[Test]
    public function devreden_bakiye_ekranda_gosterilir(): void
    {
        $this->seedMovements();

        $this->actingAs($this->admin)
            ->get(route('statements.show', [$this->company, 'date_from' => '2026-09-02']))
            ->assertOk()
            ->assertSee('Devreden bakiye')
            ->assertSee('9.000,00 ₺');
    }

    #[Test]
    public function gosterim_filtresi_kullanildiginda_uyari_gosterilir(): void
    {
        $this->seedMovements();

        $this->actingAs($this->admin)
            ->get(route('statements.show', [$this->company, 'type' => 'payment']))
            ->assertOk()
            ->assertSee('yalnızca');
    }

    #[Test]
    public function bos_ekstre_nazik_mesaj_gosterir(): void
    {
        $this->actingAs($this->admin)
            ->get(route('statements.show', $this->company))
            ->assertOk()
            ->assertSee('Bu aralıkta cari hareket bulunmuyor.');
    }

    // -------------------------------------------------------- kaynak kayıt

    /** Ters kayıt da ORİJİNAL kaynağa bağlanır: "Tahsilat kaydı", "İptal kaydı" değil. */
    #[Test]
    public function ters_kayit_orijinal_kaynaga_baglanir(): void
    {
        $this->seedMovements();
        app(PaymentService::class)->cancel(Payment::query()->firstOrFail(), 'Mükerrer', $this->admin);

        $response = $this->actingAs($this->admin)->get(route('statements.show', $this->company));

        $response->assertOk()
            ->assertDontSee('İptal / Ters Kayıt kaydı')
            ->assertSee('Tahsilat kaydı')
            ->assertSee(route('payments.show', Payment::query()->firstOrFail()), false);
    }

    #[Test]
    public function kaynak_kayitlara_baglanti_verilir(): void
    {
        $this->seedMovements();

        $stay = Stay::query()->firstOrFail();
        $payment = Payment::query()->firstOrFail();

        $this->actingAs($this->admin)
            ->get(route('statements.show', $this->company))
            ->assertOk()
            ->assertSee(route('stays.show', $stay), false)
            ->assertSee(route('payments.show', $payment), false);
    }

    /** Ledger'ın doğruluğu kaynak kayda bağlı değildir: ekran çökmez. */
    #[Test]
    public function kaynak_kayit_yoksa_ekran_cokmez(): void
    {
        app(LedgerService::class)->debit(
            companyId: $this->company->id,
            amount: Money::fromDecimal('1000.00'),
            currency: Currency::TRY,
            entryDate: '2026-09-01',
            description: 'Kaynağı olmayan hareket',
            sourceType: 'stay',
            sourceId: 999999,
            actor: $this->admin,
        );

        $this->actingAs($this->admin)
            ->get(route('statements.show', $this->company))
            ->assertOk()
            ->assertSee('Kaynak kayıt bulunamadı')
            ->assertSee('1.000,00 ₺');
    }

    #[Test]
    public function bilinmeyen_kaynak_tipi_diger_olarak_etiketlenir(): void
    {
        app(LedgerService::class)->debit(
            companyId: $this->company->id,
            amount: Money::fromDecimal('1000.00'),
            currency: Currency::TRY,
            entryDate: '2026-09-01',
            description: 'Gelecekteki belge tipi',
            sourceType: 'invoice',
            sourceId: 1,
            actor: $this->admin,
        );

        $this->actingAs($this->admin)
            ->get(route('statements.show', $this->company))
            ->assertOk()
            ->assertSee('Diğer')
            ->assertSee('Kaynak kayıt bulunamadı');
    }

    // --------------------------------------------------------------- print

    #[Test]
    public function yazdirma_gorunumu_koyu_temayi_kullanmaz(): void
    {
        $this->seedMovements();

        $response = $this->actingAs($this->admin)->get(route('statements.print', $this->company));

        $response->assertOk()
            ->assertSee('Monarch')
            ->assertSee('9.000,00 ₺')
            ->assertSee('color-scheme: light', false)
            ->assertSee('background: #fff', false)
            ->assertDontSee('bg-bg', false)
            ->assertDontSee('Ana menü', false)   // kenar çubuğu yok
            ->assertDontSee('data-sidebar', false);
    }

    #[Test]
    public function yazdirma_gorunumu_filtreleri_korur(): void
    {
        $this->seedMovements();

        $this->actingAs($this->admin)
            ->get(route('statements.print', [$this->company, 'date_from' => '2026-09-02']))
            ->assertOk()
            ->assertSee('Devreden bakiye')
            ->assertSee('02.09.2026');
    }

    #[Test]
    public function ekstrede_yazdir_baglantisi_vardir(): void
    {
        $this->actingAs($this->admin)
            ->get(route('statements.show', $this->company))
            ->assertOk()
            ->assertSee('Yazdır')
            ->assertSee(route('statements.print', $this->company), false);
    }

    #[Test]
    public function bakiye_listesi_yazdirma_gorunumu_filtreleri_korur_ve_navigasyon_gostermez(): void
    {
        $this->seedMovements();
        $other = Company::factory()->create(['name' => 'Başka Firma']);
        $this->debit($other, '750.00', Currency::TRY);

        $response = $this->actingAs($this->admin)->get(route('statements.print-index', [
            'q' => 'monarch',
            'currency' => 'TRY',
            'has_balance' => '1',
        ]));

        $response->assertOk()
            ->assertSee('CARİ EKSTRE · BAKİYE LİSTESİ')
            ->assertSee('Monarch')
            ->assertSee('4.000,00 ₺')
            ->assertSee('02.09.2026')
            ->assertDontSee('Başka Firma')
            ->assertDontSee('Ana menü', false)
            ->assertDontSee('data-sidebar', false)
            ->assertDontSee('Ekstre</', false);
    }

    #[Test]
    public function bakiye_listesi_yazdirma_tum_filtrelenmis_firmalari_sayfalamadan_gosterir(): void
    {
        foreach (range(1, 30) as $i) {
            Company::factory()->create(['name' => sprintf('Yazdır Firma %02d', $i)]);
        }

        $response = $this->actingAs($this->admin)->get(route('statements.print-index', [
            'q' => 'Yazdır Firma',
            'per_page' => 25,
        ]));

        $response->assertOk()
            ->assertSee('Yazdır Firma 01')
            ->assertSee('Yazdır Firma 30');

        $companies = $response->viewData('companies');
        $this->assertCount(30, $companies);
    }

    #[Test]
    public function bakiye_listesinde_yazdir_baglantisi_aktif_filtreleri_korur(): void
    {
        $query = [
            'q' => 'Monarch',
            'currency' => 'TRY',
            'has_balance' => '1',
            'per_page' => '25',
        ];

        $this->actingAs($this->admin)
            ->get(route('statements.index', $query))
            ->assertOk()
            ->assertSee('Yazdır')
            ->assertSee(route('statements.print-index', [
                'q' => 'Monarch',
                'currency' => 'TRY',
                'has_balance' => '1',
            ]));
    }

    // ------------------------------------------------------- bakiye listesi

    #[Test]
    public function bakiye_listesi_para_birimi_bazinda_gosterir(): void
    {
        $this->seedMovements();

        $this->actingAs($this->admin)
            ->get(route('statements.index'))
            ->assertOk()
            ->assertSee('Monarch')
            ->assertSee('4.000,00 ₺')
            ->assertSee('borçlu')
            ->assertSee('02.09.2026');   // son hareket tarihi
    }

    #[Test]
    public function hareketi_olmayan_firma_bakiye_listesinde_isaretlenir(): void
    {
        $this->actingAs($this->admin)
            ->get(route('statements.index'))
            ->assertOk()
            ->assertSee('Hareket yok');
    }

    #[Test]
    public function bakiye_listesinde_firma_aranabilir(): void
    {
        Company::factory()->create(['name' => 'Başka Firma']);

        $response = $this->actingAs($this->admin)->get(route('statements.index', ['q' => 'monarch']));

        $response->assertOk();
        $this->assertSame(['Monarch'], $response->viewData('companies')->pluck('name')->all());
    }

    // ----------------------------------------------------------------- N+1

    /** Bakiye listesi firma başına sorgu atmamalı. */
    #[Test]
    public function bakiye_listesi_n_plus_bir_uretmez(): void
    {
        Company::factory()->count(4)->create();
        LedgerEntry::factory()->count(4)->create(['company_id' => $this->company->id]);

        $measure = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->admin)->get(route('statements.index'))->assertOk();
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $count;
        };

        $this->actingAs($this->admin)->get(route('statements.index'))->assertOk();  // ısınma
        $withFive = $measure();

        Company::factory()->count(5)->create();

        $this->assertSame($withFive, $measure(), 'Firma sayısı artınca sorgu sayısı artmamalı');
    }

    /** Ekstre satır sayısı arttıkça sorgu sayısı artmamalı. */
    #[Test]
    public function ekstre_n_plus_bir_uretmez(): void
    {
        $this->seedMovements();

        $measure = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->admin)->get(route('statements.show', $this->company))->assertOk();
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $count;
        };

        $this->actingAs($this->admin)->get(route('statements.show', $this->company))->assertOk();
        $withTwo = $measure();

        $this->seedMovements();

        $this->assertSame($withTwo, $measure(), 'Hareket sayısı artınca sorgu sayısı artmamalı');
    }

    // ------------------------------------------------- Gün 12: bakiye filtresi/sıralaması

    private function debit(Company $company, string $amount, Currency $currency): void
    {
        app(LedgerService::class)->debit(
            companyId: $company->id,
            amount: Money::fromDecimal($amount),
            currency: $currency,
            entryDate: '2026-09-01',
            description: 'Konaklama',
            sourceType: 'stay',
            sourceId: 1,
            actor: $this->admin,
        );
    }

    #[Test]
    public function para_birimi_filtresi_yalnizca_o_para_biriminde_bakiyesi_olanlari_getirir(): void
    {
        $tryCompany = Company::factory()->create(['name' => 'TRY Firma']);
        $eurCompany = Company::factory()->create(['name' => 'EUR Firma']);

        $this->debit($tryCompany, '500.00', Currency::TRY);
        $this->debit($eurCompany, '200.00', Currency::EUR);

        $response = $this->actingAs($this->admin)
            ->get(route('statements.index', ['currency' => 'EUR', 'has_balance' => '1']));

        $response->assertOk()->assertSee('EUR Firma');
        $response->assertDontSee('TRY Firma');
    }

    #[Test]
    public function yalnizca_bakiyesi_olanlar_filtresi_sifir_bakiyeli_firmayi_disarida_birakir(): void
    {
        $borclu = Company::factory()->create(['name' => 'Borçlu Firma']);
        $kapali = Company::factory()->create(['name' => 'Kapalı Firma']);

        $this->debit($borclu, '500.00', Currency::TRY);
        // Kapalı firma: borç + eşit tahsilat, net sıfır.
        $this->debit($kapali, '300.00', Currency::TRY);
        app(LedgerService::class)->credit(
            companyId: $kapali->id, amount: Money::fromDecimal('300.00'), currency: Currency::TRY,
            entryDate: '2026-09-02', description: 'Tahsilat', sourceType: 'payment', sourceId: 1, actor: $this->admin,
        );

        $response = $this->actingAs($this->admin)->get(route('statements.index', ['has_balance' => '1']));

        $response->assertOk()->assertSee('Borçlu Firma');
        $response->assertDontSee('Kapalı Firma');
    }

    #[Test]
    public function bakiye_sirasi_en_yuksekten_en_dusuge_dogru_calisir(): void
    {
        $az = Company::factory()->create(['name' => 'Az Borçlu']);
        $cok = Company::factory()->create(['name' => 'Çok Borçlu']);

        $this->debit($az, '100.00', Currency::TRY);
        $this->debit($cok, '9000.00', Currency::TRY);

        $html = (string) $this->actingAs($this->admin)
            ->get(route('statements.index', ['currency' => 'TRY', 'balance_sort' => 'highest']))
            ->getContent();

        $this->assertTrue(strpos($html, 'Çok Borçlu') < strpos($html, 'Az Borçlu'));
    }

    /**
     * Kullanıcı talimatı Gün 12 §6: para birimi seçilmeden bakiye sıralaması
     * ANLAMSIZDIR (para birimleri asla birleştirilmez) — sessizce isim
     * sırasına düşer, hata vermez.
     */
    #[Test]
    public function para_birimi_secilmeden_bakiye_sirasi_isme_doner(): void
    {
        $b = Company::factory()->create(['name' => 'B Firma']);
        $a = Company::factory()->create(['name' => 'A Firma']);

        $this->debit($b, '100.00', Currency::TRY);
        $this->debit($a, '9000.00', Currency::TRY);

        $html = (string) $this->actingAs($this->admin)
            ->get(route('statements.index', ['balance_sort' => 'highest']))
            ->getContent();

        $this->assertTrue(strpos($html, 'A Firma') < strpos($html, 'B Firma'));
    }

    #[Test]
    public function usd_rub_gel_para_birimleri_de_filtrelenebilir(): void
    {
        $usd = Company::factory()->create(['name' => 'USD Firma']);
        $rub = Company::factory()->create(['name' => 'RUB Firma']);
        $gel = Company::factory()->create(['name' => 'GEL Firma']);

        $this->debit($usd, '100.00', Currency::USD);
        $this->debit($rub, '5000.00', Currency::RUB);
        $this->debit($gel, '300.00', Currency::GEL);

        foreach (['USD' => 'USD Firma', 'RUB' => 'RUB Firma', 'GEL' => 'GEL Firma'] as $code => $expected) {
            $response = $this->actingAs($this->admin)
                ->get(route('statements.index', ['currency' => $code, 'has_balance' => '1']));

            $response->assertOk()->assertSee($expected);
        }
    }

    #[Test]
    public function bakiye_modunde_de_sayfalama_dogru_calisir(): void
    {
        foreach (range(1, 3) as $i) {
            $company = Company::factory()->create(['name' => "Firma {$i}"]);
            $this->debit($company, '100.00', Currency::TRY);
        }

        $response = $this->actingAs($this->admin)->get(route(
            'statements.index',
            ['currency' => 'TRY', 'has_balance' => '1', 'per_page' => 25],
        ));

        $response->assertOk();
        $response->assertSee('Firma 1');
        $response->assertSee('Firma 2');
        $response->assertSee('Firma 3');
    }
}
