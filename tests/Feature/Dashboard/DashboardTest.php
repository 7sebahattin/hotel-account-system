<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Models\User;
use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Companies\Models\Company;
use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
use App\Modules\Dashboard\Services\DashboardService;
use App\Modules\Dashboard\Support\CurrencyTotals;
use App\Modules\Dashboard\Support\FinancialSummary;
use App\Modules\Ledger\Services\LedgerService;
use App\Modules\Payments\Services\PaymentService;
use App\Modules\Stays\Services\StayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Pano: para birimi ayrımı, rol görünürlüğü ve sorgu bütçesi.
 *
 * Gün 11: pano ikiye ayrıldı — Admin/Patron "Operasyon" (kasa) ve "Finans"
 * (cari/ledger) görünümleri arasında `?view=` ile geçer; Personel hâlâ kendi
 * panosunu görür. Pano hiçbir tutarı saklamaz; her rakam kaynağından yeniden
 * hesaplanır. En kritik değişmez: hiçbir para birimi bir başkasıyla
 * birleştirilmez (CLAUDE.md §6/L5).
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private LedgerService $ledger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        $this->actor = $this->userWithRole(RoleName::Admin);
        $this->ledger = app(LedgerService::class);
    }

    private function debit(Company $company, string $amount, Currency $currency, string $date): void
    {
        $this->ledger->debit(
            companyId: $company->id,
            amount: Money::fromDecimal($amount),
            currency: $currency,
            entryDate: $date,
            description: 'Konaklama',
            sourceType: 'stay',
            sourceId: 1,
            actor: $this->actor,
        );
    }

    private function credit(Company $company, string $amount, Currency $currency, string $date): void
    {
        $this->ledger->credit(
            companyId: $company->id,
            amount: Money::fromDecimal($amount),
            currency: $currency,
            entryDate: $date,
            description: 'Tahsilat',
            sourceType: 'payment',
            sourceId: 1,
            actor: $this->actor,
        );
    }

    private function summary(): FinancialSummary
    {
        return app(DashboardService::class)->finance(Carbon::today());
    }

    /** @return array<string, CurrencyTotals> */
    private function byCurrency(FinancialSummary $summary): array
    {
        $indexed = [];

        foreach ($summary->currencies as $totals) {
            $indexed[$totals->currency->value] = $totals;
        }

        return $indexed;
    }

    // ------------------------------------------------- para birimi ayrımı

    #[Test]
    public function farkli_para_birimleri_ayri_toplanir_ve_birlestirilmez(): void
    {
        $company = Company::factory()->create();

        $this->debit($company, '1000.00', Currency::TRY, '2026-09-05');
        $this->debit($company, '200.00', Currency::EUR, '2026-09-05');
        $this->debit($company, '50.00', Currency::GBP, '2026-09-05');

        $totals = $this->byCurrency($this->summary());

        $this->assertSame('1000.00', $totals['TRY']->receivable->toDecimal());
        $this->assertSame('200.00', $totals['EUR']->receivable->toDecimal());
        $this->assertSame('50.00', $totals['GBP']->receivable->toDecimal());

        // Hiçbir yerde 1250 (1000+200+50) gibi birleşik bir toplam üretilmez.
        foreach ($totals as $currencyTotals) {
            $this->assertNotSame('1250.00', $currencyTotals->receivable->toDecimal());
            $this->assertNotSame('1250.00', $currencyTotals->net->toDecimal());
        }
    }

    #[Test]
    public function acik_alacak_ve_acik_borc_firma_bazinda_netlenip_toplanir(): void
    {
        // Bir firma bize borçlu, diğerine biz borçluyuz. İkisi birbirini
        // GÖTÜRMEZ: alacak 1000, borç 300 olarak ayrı ayrı görünür.
        $borclu = Company::factory()->create();
        $alacakli = Company::factory()->create();

        $this->debit($borclu, '1000.00', Currency::TRY, '2026-09-01');
        $this->credit($alacakli, '300.00', Currency::TRY, '2026-09-01');

        $try = $this->byCurrency($this->summary())['TRY'];

        $this->assertSame('1000.00', $try->receivable->toDecimal());
        $this->assertSame('300.00', $try->payable->toDecimal());
        $this->assertSame('700.00', $try->net->toDecimal());
    }

    #[Test]
    public function ayni_firmanin_borc_ve_alacagi_once_netlenir(): void
    {
        $company = Company::factory()->create();

        $this->debit($company, '1000.00', Currency::TRY, '2026-09-01');
        $this->credit($company, '400.00', Currency::TRY, '2026-09-02');

        $try = $this->byCurrency($this->summary())['TRY'];

        $this->assertSame('600.00', $try->receivable->toDecimal());
        $this->assertSame('0.00', $try->payable->toDecimal());
        $this->assertSame('600.00', $try->net->toDecimal());
    }

    #[Test]
    public function fazla_tahsilat_acik_borc_olarak_gorunur(): void
    {
        $company = Company::factory()->create();

        $this->debit($company, '100.00', Currency::TRY, '2026-09-01');
        $this->credit($company, '250.00', Currency::TRY, '2026-09-02');

        $try = $this->byCurrency($this->summary())['TRY'];

        $this->assertSame('0.00', $try->receivable->toDecimal());
        $this->assertSame('150.00', $try->payable->toDecimal());
        $this->assertSame('-150.00', $try->net->toDecimal());
    }

    #[Test]
    public function en_yuksek_bakiyeli_firmalar_para_birimi_bazinda_ayrisir(): void
    {
        $tryCompany = Company::factory()->create(['name' => 'TRY Firma']);
        $eurCompany = Company::factory()->create(['name' => 'EUR Firma']);

        $this->debit($tryCompany, '900.00', Currency::TRY, '2026-09-01');
        $this->debit($eurCompany, '100.00', Currency::EUR, '2026-09-01');

        $top = $this->summary()->topCompanies;

        $this->assertSame('TRY Firma', $top['TRY'][0]->companyName);
        $this->assertSame(Currency::TRY, $top['TRY'][0]->currency);
        $this->assertSame('EUR Firma', $top['EUR'][0]->companyName);

        // Yüksek TRY tutarı EUR listesine sızmaz.
        $this->assertCount(1, $top['EUR']);
    }

    #[Test]
    public function en_yuksek_bakiyeli_listesinde_bize_borclu_olmayan_firma_yer_almaz(): void
    {
        $borclu = Company::factory()->create(['name' => 'Borçlu']);
        $kapali = Company::factory()->create(['name' => 'Kapalı']);
        $alacakli = Company::factory()->create(['name' => 'Alacaklı']);

        $this->debit($borclu, '500.00', Currency::TRY, '2026-09-01');

        $this->debit($kapali, '100.00', Currency::TRY, '2026-09-01');
        $this->credit($kapali, '100.00', Currency::TRY, '2026-09-02');

        $this->credit($alacakli, '80.00', Currency::TRY, '2026-09-01');

        $names = array_map(
            static fn ($row): string => $row->companyName,
            $this->summary()->topCompanies['TRY'],
        );

        $this->assertSame(['Borçlu'], $names);
    }

    // --------------------------------------------------- aylık toplamlar

    #[Test]
    public function bu_ayki_konaklama_ve_tahsilat_toplamlari_para_birimi_bazindadir(): void
    {
        $company = Company::factory()->create();
        $today = Carbon::create(2026, 9, 15);
        Carbon::setTestNow($today);

        $stays = app(StayService::class);
        $payments = app(PaymentService::class);

        // Bu ay: çıkış tarihi ay içinde.
        $stays->create([
            'company_id' => $company->id,
            'guest_name' => 'Bu Ay',
            'check_in_date' => '2026-09-10',
            'check_out_date' => '2026-09-12',
            'pricing_mode' => 'total',
            'amount' => '1000.00',
            'currency' => 'TRY',
        ], $this->actor);

        // Geçen ay: aynı firmaya ama aralık dışında.
        $stays->create([
            'company_id' => $company->id,
            'guest_name' => 'Geçen Ay',
            'check_in_date' => '2026-08-10',
            'check_out_date' => '2026-08-12',
            'pricing_mode' => 'total',
            'amount' => '7777.00',
            'currency' => 'TRY',
        ], $this->actor);

        $payments->create([
            'company_id' => $company->id,
            'payment_date' => '2026-09-14',
            'amount' => '400.00',
            'currency' => 'EUR',
        ], $this->actor);

        $totals = $this->byCurrency(app(DashboardService::class)->finance($today));

        $this->assertSame('1000.00', $totals['TRY']->staysThisMonth->toDecimal());
        $this->assertSame('0.00', $totals['TRY']->paymentsThisMonth->toDecimal());
        $this->assertSame('400.00', $totals['EUR']->paymentsThisMonth->toDecimal());
        $this->assertSame('0.00', $totals['EUR']->staysThisMonth->toDecimal());

        Carbon::setTestNow();
    }

    #[Test]
    public function iptal_edilen_kayitlar_aylik_toplamlara_girmez(): void
    {
        $company = Company::factory()->create();
        $today = Carbon::create(2026, 9, 15);
        Carbon::setTestNow($today);

        $stays = app(StayService::class);
        $payments = app(PaymentService::class);

        $stay = $stays->create([
            'company_id' => $company->id,
            'guest_name' => 'İptal',
            'check_in_date' => '2026-09-10',
            'check_out_date' => '2026-09-12',
            'pricing_mode' => 'total',
            'amount' => '1000.00',
            'currency' => 'TRY',
        ], $this->actor);

        $payment = $payments->create([
            'company_id' => $company->id,
            'payment_date' => '2026-09-14',
            'amount' => '400.00',
            'currency' => 'TRY',
        ], $this->actor);

        $stays->cancel($stay, $this->actor);
        $payments->cancel($payment, 'Yanlış giriş', $this->actor);

        $totals = $this->byCurrency(app(DashboardService::class)->finance($today));

        // Ay toplamları sıfırlanır ve bakiye de net sıfıra döner.
        $this->assertSame('0.00', ($totals['TRY'] ?? null)?->staysThisMonth->toDecimal() ?? '0.00');
        $this->assertSame('0.00', ($totals['TRY'] ?? null)?->paymentsThisMonth->toDecimal() ?? '0.00');
        $this->assertSame('0.00', ($totals['TRY'] ?? null)?->net->toDecimal() ?? '0.00');

        Carbon::setTestNow();
    }

    #[Test]
    public function aktif_firma_sayisi_pasif_ve_silinmis_firmalari_saymaz(): void
    {
        Company::factory()->count(2)->create(['is_active' => true]);
        Company::factory()->create(['is_active' => false]);
        Company::factory()->create()->delete();

        $this->assertSame(2, $this->summary()->activeCompanyCount);
    }

    // -------------------------------------------------- rol görünürlüğü

    #[Test]
    public function personel_finansal_pano_yerine_kendi_panosunu_gorur(): void
    {
        $company = Company::factory()->create(['name' => 'Gizli Firma']);
        $this->debit($company, '9999.00', Currency::TRY, '2026-09-01');

        $response = $this->actingAs($this->userWithRole(RoleName::Personel))->get(route('home'));

        $response->assertOk();
        $response->assertSee('Bugün girdiğim kayıtlar');
        $response->assertSee('Bugün girdiğim kayıtlar');
        $response->assertDontSee('Açık alacak');
        $response->assertDontSee('Net bakiye');
        $response->assertDontSee('En yüksek bakiyeli firmalar');
        $response->assertDontSee('9.999,00');
    }

    #[Test]
    public function admin_ve_patron_varsayilan_olarak_finans_gorunumunu_gorur(): void
    {
        // Kullanıcı talimatı: Ana Sayfa'nın varsayılanı Finans'tır; Operasyon
        // yalnızca ?view=operations ile açıkça istenir. Sistemde firma
        // yokken finans boş-durum ekranını ("Sistem henüz boş") gösterir —
        // bu da operasyona özgü "Şu an" başlığının GÖRÜNMEDİĞİNİ kanıtlar.
        foreach ([RoleName::Admin, RoleName::Patron] as $role) {
            $this->actingAs($this->userWithRole($role))->get(route('home'))
                ->assertOk()
                ->assertSee('Sistem henüz boş')
                ->assertDontSee('Şu an');
        }
    }

    #[Test]
    public function eski_operasyon_baglantisi_admin_ve_patron_icin_cari_panoyu_acar(): void
    {
        foreach ([RoleName::Admin, RoleName::Patron] as $role) {
            $this->actingAs($this->userWithRole($role))->get(route('home', ['view' => 'operations']))
                ->assertOk()
                ->assertViewIs('dashboard::finance')
                ->assertDontSee('Kasa Defteri');
        }
    }

    #[Test]
    public function admin_ve_patron_finans_gorunumunu_view_parametresiyle_gorur(): void
    {
        $company = Company::factory()->create(['name' => 'Görünür Firma']);
        $this->debit($company, '1500.00', Currency::TRY, '2026-09-01');

        foreach ([RoleName::Admin, RoleName::Patron] as $role) {
            $this->actingAs($this->userWithRole($role))->get(route('home', ['view' => 'finance']))
                ->assertOk()
                ->assertSee('Cari durum')
                ->assertSee('Açık alacak')
                ->assertSee('Net bakiye')
                ->assertSee('1.500,00 ₺')
                ->assertSee('Görünür Firma');
        }
    }

    /**
     * Kullanıcı talimatı §18: personel `?view=finance` yazarak patron/admin
     * finansal verisine ULAŞAMAZ. Kontrol UI'da gizleme değil, controller'daki
     * `canSeeFinancials()` dalının kendisidir — dal seçimi `view` OKUNMADAN
     * ÖNCE yapılır.
     */
    #[Test]
    public function personel_view_finance_parametresiyle_finansal_veriye_erisemez(): void
    {
        $company = Company::factory()->create(['name' => 'Gizli Firma']);
        $this->debit($company, '9999.00', Currency::TRY, '2026-09-01');

        $response = $this->actingAs($this->userWithRole(RoleName::Personel))
            ->get(route('home', ['view' => 'finance']));

        $response->assertOk();
        $response->assertSee('Bugün girdiğim kayıtlar');
        $response->assertDontSee('Cari durum');
        $response->assertDontSee('Açık alacak');
        $response->assertDontSee('9.999,00');
        $response->assertDontSee('Gizli Firma');
    }

    #[Test]
    public function admin_operasyon_panosunda_hizli_islem_menusu_gosterilmez(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Admin))->get(route('home', ['view' => 'operations']))
            ->assertOk()
            ->assertDontSee('data-quick-action', false)
            ->assertDontSee('id="hizli-islem-menu"', false)
            ->assertDontSee('Hızlı işlem');
    }

    #[Test]
    public function personel_panosu_bugun_katmanini_da_gosterir(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Personel))->get(route('home'))
            ->assertOk()
            ->assertSee('Bugün girdiğim kayıtlar')
            ->assertDontSee('Cari durum');
    }

    #[Test]
    public function personel_panosu_hic_finansal_sorgu_calistirmaz(): void
    {
        $company = Company::factory()->create();
        $this->debit($company, '1000.00', Currency::TRY, '2026-09-01');

        $personel = $this->userWithRole(RoleName::Personel);

        // Yetki önbelleğini ısıt: ölçüm yalnızca pano sorgularını görsün.
        $this->actingAs($personel)->get(route('home'));

        // enableQueryLog() var olan günlüğü TEMİZLEMEZ; ısınma isteğinin
        // sorguları sayıma karışmasın diye açıkça boşaltılır.
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->actingAs($personel)->get(route('home'));
        $queries = DB::getRawQueryLog();
        DB::disableQueryLog();

        $ledgerQueries = array_filter(
            $queries,
            static fn (array $query): bool => str_contains((string) $query['raw_query'], 'ledger_entries'),
        );

        $this->assertSame([], $ledgerQueries, 'Personel panosu cari defteri sorgulamamalı.');
    }

    // ------------------------------------------------------- performans

    /** Ters yön: Finans görünümündeyken kasa sorguları hiç çalışmamalı. */
    #[Test]
    public function finans_gorunumu_kasa_sorgusu_calistirmaz(): void
    {
        $x = $this->userWithRole(RoleName::Personel, ['name' => 'X']);

        $admin = $this->userWithRole(RoleName::Admin);
        $this->actingAs($admin)->get(route('home', ['view' => 'finance']));

        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->actingAs($admin)->get(route('home', ['view' => 'finance']))->assertOk();
        $queries = DB::getRawQueryLog();
        DB::disableQueryLog();

        $cashQueries = array_filter(
            $queries,
            static fn (array $query): bool => str_contains((string) $query['raw_query'], 'cash_')
        );

        $this->assertSame([], $cashQueries, 'Finans görünümü kasa tablolarını sorgulamamalı.');
    }

    #[Test]
    public function finans_gorunumu_sorgu_sayisi_firma_sayisiyla_birlikte_buyumez(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $measure = function () use ($admin): int {
            $this->actingAs($admin)->get(route('home', ['view' => 'finance']));

            DB::enableQueryLog();
            DB::flushQueryLog();
            $this->actingAs($admin)->get(route('home', ['view' => 'finance']))->assertOk();
            $count = count(DB::getRawQueryLog());
            DB::disableQueryLog();

            return $count;
        };

        foreach (Company::factory()->count(3)->create() as $company) {
            $this->debit($company, '100.00', Currency::TRY, '2026-09-01');
        }

        $withThree = $measure();

        foreach (Company::factory()->count(12)->create() as $company) {
            $this->debit($company, '250.00', Currency::TRY, '2026-09-02');
            $this->credit($company, '50.00', Currency::EUR, '2026-09-03');
        }

        $withFifteen = $measure();

        $this->assertSame(
            $withThree,
            $withFifteen,
            "Sorgu sayısı firma sayısıyla arttı ({$withThree} → {$withFifteen}): N+1 var."
        );

        // v2.1.1-D §3: TL karşılığı hesapları (sistem çapında + en yüksek
        // bakiyeli firmalar) dört yeni TOPLU sorgu ekledi (13 → 17); bunlar da
        // kayıt sayısıyla BÜYÜMEZ (yukarıdaki assertSame zaten bunu kanıtlar).
        $this->assertLessThanOrEqual(17, $withFifteen, "Finans görünümü {$withFifteen} sorgu çalıştırdı; bütçe 17.");
    }

    // ------------------------------------------------------- mobil dokunma

    #[Test]
    public function panel_satirlari_tutari_da_kapsayan_tek_baglantidir(): void
    {
        // Gün 9.5 mobil turunda düzeltilen gerçek hata: satırın yalnızca
        // firma adı tıklanabilirdi, tutar ve dolgu alanı değildi (CLAUDE.md
        // §11.6 "Satır/card tamamı kolay dokunulabilir olsun"). Bu test,
        // <a href="…"> etiketinin adı VE tutarı aynı anda sarmaladığını —
        // ikisinin arasında </a> ile bölünmediğini — doğrular.
        $company = Company::factory()->create(['name' => 'Dokunma Testi A.Ş.']);
        $this->debit($company, '4321.00', Currency::TRY, '2026-09-01');

        $html = $this->actingAs($this->userWithRole(RoleName::Admin))
            ->get(route('home', ['view' => 'finance']))
            ->getContent();

        $href = route('companies.show', $company->id);
        $pattern = '#<a\s+href="'.preg_quote($href, '#').'"[^>]*>((?:(?!</a>).)*)</a>#s';

        $this->assertMatchesRegularExpression($pattern, (string) $html);
        preg_match($pattern, (string) $html, $matches);

        $this->assertStringContainsString('Dokunma Testi A.Ş.', $matches[1]);
        $this->assertStringContainsString('4.321,00', $matches[1]);
    }

    // ------------------------------------------------------- boş sistem

    #[Test]
    public function bos_sistemde_finans_gorunumunde_yonlendirici_bir_bos_durum_gosterilir(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Admin))->get(route('home', ['view' => 'finance']))
            ->assertOk()
            ->assertSee('Sistem henüz boş')
            ->assertSee('Firma ekle');
    }

    #[Test]
    public function bos_sistemde_ozet_bos_kabul_edilir(): void
    {
        $summary = $this->summary();

        $this->assertTrue($summary->isEmpty());
    }

    // ---------------------------------------------- Operasyon görünümü

    #[Test]
    public function sorun_yokken_dikkat_gerekenler_bolumu_hic_gosterilmez(): void
    {
        $x = $this->userWithRole(RoleName::Personel, ['name' => 'X']);

        $this->actingAs($this->userWithRole(RoleName::Admin))->get(route('home', ['view' => 'operations']))
            ->assertOk()
            ->assertDontSee('Dikkat gerekenler');
    }

    // ---------------------------------------------- Personel — Vardiyam/Kasam

    // ------------------------------------------------------- Hızlı işlem

    #[Test]
    public function personel_panosunda_hizli_islem_menusu_gosterilmez(): void
    {
        $html = $this->actingAs($this->userWithRole(RoleName::Personel))->get(route('home'))->getContent();

        $this->assertStringNotContainsString('data-quick-action', (string) $html);
        $this->assertStringNotContainsString('id="hizli-islem-menu"', (string) $html);
        $this->assertStringNotContainsString('Hızlı işlem', (string) $html);
    }

    #[Test]
    public function hizli_islem_menusunde_yetkisiz_aksiyon_gorunmez(): void
    {
        // Hızlı işlem menüsü layout'tan kaldırıldığı için rolü olmayan
        // kullanıcıda da widget işaretleri çizilmez.
        $html = $this->actingAs(User::factory()->create())->get(route('home'))->getContent();

        $this->assertStringNotContainsString('id="hizli-islem-menu"', (string) $html);
        $this->assertStringNotContainsString('Kasa Hareketi', (string) $html);
        $this->assertStringNotContainsString('Yeni Cari Hesap', (string) $html);
    }
}
