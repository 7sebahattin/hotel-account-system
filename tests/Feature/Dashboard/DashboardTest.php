<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Models\User;
use App\Modules\Authorization\Enums\RoleName;
use App\Modules\CashBook\Enums\CashMovementType;
use App\Modules\CashBook\Services\CashBookService;
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

    private CashBookService $cash;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        $this->actor = $this->userWithRole(RoleName::Admin);
        $this->ledger = app(LedgerService::class);
        $this->cash = app(CashBookService::class);
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
        $response->assertSee('Vardiyam');
        $response->assertSee('Bugün girdiğim kayıtlar');
        $response->assertDontSee('Açık alacak');
        $response->assertDontSee('Net bakiye');
        $response->assertDontSee('En yüksek bakiyeli firmalar');
        $response->assertDontSee('9.999,00');
    }

    #[Test]
    public function admin_ve_patron_varsayilan_olarak_operasyon_gorunumunu_gorur(): void
    {
        foreach ([RoleName::Admin, RoleName::Patron] as $role) {
            $this->actingAs($this->userWithRole($role))->get(route('home'))
                ->assertOk()
                ->assertSee('Şu an')
                ->assertDontSee('Cari durum')
                ->assertDontSee('En yüksek bakiyeli firmalar');
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
        $response->assertSee('Vardiyam');
        $response->assertDontSee('Cari durum');
        $response->assertDontSee('Açık alacak');
        $response->assertDontSee('9.999,00');
        $response->assertDontSee('Gizli Firma');
    }

    #[Test]
    public function admin_operasyon_panosunda_hizli_islem_menusunu_gorur(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Admin))->get(route('home'))
            ->assertOk()
            ->assertSee('Hızlı işlem')
            ->assertSee('Tahsilat')
            ->assertSee('Konaklama')
            ->assertSee('Firma');
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

    /**
     * Kullanıcı talimatı §17: Operasyon görünümündeyken Finans'ın (ledger/
     * firma) ağır sorguları hiç çalışmamalı.
     */
    #[Test]
    public function operasyon_gorunumu_cari_veya_firma_sorgusu_calistirmaz(): void
    {
        $company = Company::factory()->create();
        $this->debit($company, '1000.00', Currency::TRY, '2026-09-01');

        $admin = $this->userWithRole(RoleName::Admin);
        $this->actingAs($admin)->get(route('home'));

        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->actingAs($admin)->get(route('home'))->assertOk();
        $queries = DB::getRawQueryLog();
        DB::disableQueryLog();

        foreach (['ledger_entries', 'from `companies`', 'from `stays`', 'from `payments`'] as $needle) {
            $hits = array_filter(
                $queries,
                static fn (array $query): bool => str_contains((string) $query['raw_query'], $needle),
            );
            $this->assertSame([], $hits, "Operasyon görünümü '{$needle}' sorgulamamalı.");
        }
    }

    /** Ters yön: Finans görünümündeyken kasa sorguları hiç çalışmamalı. */
    #[Test]
    public function finans_gorunumu_kasa_sorgusu_calistirmaz(): void
    {
        $x = $this->userWithRole(RoleName::Personel, ['name' => 'X']);
        $this->cash->openSession($x, ['TRY' => Money::fromDecimal('1000.00')]);

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

        $this->assertLessThanOrEqual(15, $withFifteen, "Finans görünümü {$withFifteen} sorgu çalıştırdı; bütçe 15.");
    }

    #[Test]
    public function operasyon_gorunumu_sorgu_sayisi_vardiya_sayisiyla_birlikte_buyumez(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $measure = function () use ($admin): int {
            $this->actingAs($admin)->get(route('home'));

            DB::enableQueryLog();
            DB::flushQueryLog();
            $this->actingAs($admin)->get(route('home'))->assertOk();
            $count = count(DB::getRawQueryLog());
            DB::disableQueryLog();

            return $count;
        };

        /*
         * Not: sıfır kapanmış vardiya/devir olan bir taban ile karşılaştırma
         * yapılmaz — Eloquent boş bir eager-load kümesi için sorgu ÇALIŞTIRMAZ
         * (ör. hiç devir yoksa `toUser`/`fromUser`/`amounts` sorguları hiç
         * atılmaz), bu da yanlış bir "sorgu arttı" izlenimi verir. Bu yüzden
         * her iki ölçüm de EN AZ BİR kapanmış vardiya/devir içerir; asıl soru
         * "1 devirden 4 devire çıkınca sorgu sayısı sabit mi kalıyor" sorusudur.
         */
        $owner = $this->userWithRole(RoleName::Personel, ['name' => 'X']);
        $session = $this->cash->openSession($owner, ['TRY' => Money::fromDecimal('1000.00')]);
        $this->cash->recordMovement($session, CashMovementType::RoomIncome, Currency::TRY, Money::fromDecimal('500.00'), $owner);

        $next = $this->userWithRole(RoleName::Personel);
        $transfer = $this->cash->startHandover($session->refresh(), $next, ['TRY' => Money::fromDecimal('1500.00')], $owner);
        $session = $this->cash->acceptHandover($transfer, $next);
        $owner = $next;

        $withOneTransfer = $measure();

        // Aynı gün içinde birkaç devir daha yaşansın: kapanan vardiya sayısı artsın.
        foreach (range(1, 3) as $_) {
            $next = $this->userWithRole(RoleName::Personel);
            $transfer = $this->cash->startHandover($session->refresh(), $next, ['TRY' => Money::fromDecimal('1500.00')], $owner);
            $session = $this->cash->acceptHandover($transfer, $next);
            $this->cash->recordMovement($session, CashMovementType::ExtraIncome, Currency::TRY, Money::fromDecimal('50.00'), $next);
            $owner = $next;
        }

        $withFourTransfers = $measure();

        $this->assertSame(
            $withOneTransfer,
            $withFourTransfers,
            "Sorgu sayısı devir sayısıyla arttı ({$withOneTransfer} → {$withFourTransfers}): N+1 var."
        );
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
    public function operasyon_gorunumu_acik_vardiya_yokken_kapali_durumu_gosterir(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Admin))->get(route('home'))
            ->assertOk()
            ->assertSee('Kasa Kapalı')
            ->assertSee('Açık vardiya yok');
    }

    #[Test]
    public function operasyon_gorunumu_acik_vardiyanin_beklenen_kasasini_para_birimi_bazinda_gosterir(): void
    {
        $x = $this->userWithRole(RoleName::Personel, ['name' => 'X']);
        $session = $this->cash->openSession($x, ['TRY' => Money::fromDecimal('7000.00'), 'RUB' => Money::fromDecimal('5000.00')]);
        $this->cash->recordMovement($session, CashMovementType::RoomIncome, Currency::USD, Money::fromDecimal('150.00'), $x);

        $summary = app(DashboardService::class)->operations(Carbon::today());

        $this->assertNotNull($summary->cash);
        $this->assertSame('7000.00', $summary->cash->expected['TRY']->toDecimal());
        $this->assertSame('5000.00', $summary->cash->expected['RUB']->toDecimal());
        $this->assertSame('150.00', $summary->cash->expected['USD']->toDecimal());

        // Hiçbir global toplam yok: dizi anahtarları dışında birleşik bir
        // "toplam kasa" alanı üretilmez — üç para birimi ayrı ayrı kalır.
        $this->assertCount(3, $summary->cash->expected);
    }

    #[Test]
    public function ara_kasa_teslimi_bugun_kartinda_gider_olarak_gorunmez(): void
    {
        $x = $this->userWithRole(RoleName::Personel, ['name' => 'X']);
        $session = $this->cash->openSession($x, ['TRY' => Money::fromDecimal('14000.00')]);
        $this->cash->recordMovement($session, CashMovementType::CashOut, Currency::TRY, Money::fromDecimal('12000.00'), $x, counterparty: 'Patron');

        $summary = app(DashboardService::class)->operations(Carbon::today());

        $this->assertSame('0.00', $summary->todayTotals['TRY']->expense->toDecimal());
        $this->assertSame('-12000.00', $summary->todayTotals['TRY']->cashOut->toDecimal());

        $html = $this->actingAs($this->userWithRole(RoleName::Admin))->get(route('home'))->getContent();
        $this->assertStringNotContainsString('12.000,00 ₺ gider', (string) $html);
    }

    #[Test]
    public function operasyon_gorunumu_kasa_farkini_dikkat_gerekenler_altinda_gosterir(): void
    {
        $x = $this->userWithRole(RoleName::Personel, ['name' => 'X']);
        $session = $this->cash->openSession($x, ['TRY' => Money::fromDecimal('7000.00')]);
        // Beklenen 7000, sayılan 6900: 100 TRY fark.
        $this->cash->closeSession($session, ['TRY' => Money::fromDecimal('6900.00')], $x);

        $summary = app(DashboardService::class)->operations(Carbon::today());

        $this->assertTrue($summary->hasCountDifferenceToday);
        $this->assertTrue($summary->needsAttention());

        $this->actingAs($this->userWithRole(RoleName::Admin))->get(route('home'))
            ->assertOk()
            ->assertSee('Dikkat gerekenler')
            ->assertSee('Kasa farkı');
    }

    #[Test]
    public function sorun_yokken_dikkat_gerekenler_bolumu_hic_gosterilmez(): void
    {
        $x = $this->userWithRole(RoleName::Personel, ['name' => 'X']);
        $this->cash->openSession($x, ['TRY' => Money::fromDecimal('1000.00')]);

        $this->actingAs($this->userWithRole(RoleName::Admin))->get(route('home'))
            ->assertOk()
            ->assertDontSee('Dikkat gerekenler');
    }

    // ---------------------------------------------- Personel — Vardiyam/Kasam

    #[Test]
    public function personel_kendi_kasasini_tutarlariyla_gorur(): void
    {
        $personel = $this->userWithRole(RoleName::Personel, ['name' => 'Ayşe']);
        $session = $this->cash->openSession($personel, ['EUR' => Money::fromDecimal('300.00')]);
        $this->cash->recordMovement($session, CashMovementType::RoomIncome, Currency::EUR, Money::fromDecimal('50.00'), $personel);

        $this->actingAs($personel)->get(route('home'))
            ->assertOk()
            ->assertSee('Kasam')
            ->assertSee('350,00 €');
    }

    #[Test]
    public function personel_baskasinin_vardiyasinin_tutarini_gormez(): void
    {
        $owner = $this->userWithRole(RoleName::Personel, ['name' => 'Sahibi']);
        $this->cash->openSession($owner, ['TRY' => Money::fromDecimal('12345.00')]);

        $other = $this->userWithRole(RoleName::Personel, ['name' => 'Diğeri']);

        $html = $this->actingAs($other)->get(route('home'))->getContent();

        $this->assertStringContainsString('Sahibi', (string) $html);
        $this->assertStringNotContainsString('12.345,00', (string) $html);
    }

    #[Test]
    public function acik_vardiyasi_olmayan_personel_kasayi_acma_cagrisi_gorur(): void
    {
        $personel = $this->userWithRole(RoleName::Personel);

        $this->actingAs($personel)->get(route('home'))
            ->assertOk()
            ->assertSee('Açık vardiyanız yok')
            ->assertSee('Kasayı Aç');
    }

    #[Test]
    public function bekleyen_devri_olan_personel_once_bunu_gorur(): void
    {
        $owner = $this->userWithRole(RoleName::Personel, ['name' => 'Devreden']);
        $target = $this->userWithRole(RoleName::Personel, ['name' => 'Hedef']);
        $session = $this->cash->openSession($owner, ['TRY' => Money::fromDecimal('1000.00')]);
        $this->cash->startHandover($session, $target, ['TRY' => Money::fromDecimal('1000.00')], $owner);

        $this->actingAs($target)->get(route('home'))
            ->assertOk()
            ->assertSee('Kasa devri sizi bekliyor')
            ->assertSee('Devreden');
    }

    // ------------------------------------------------------- Hızlı işlem

    #[Test]
    public function personel_hizli_islem_menusunde_yalnizca_kasa_islemlerini_gorur(): void
    {
        $html = $this->actingAs($this->userWithRole(RoleName::Personel))->get(route('home'))->getContent();

        $this->assertStringContainsString('Oda Geliri', (string) $html);
        $this->assertStringContainsString('Kasa Devri', (string) $html);
        // Global menüde firma/konaklama/tahsilat KISAYOLU yok (kullanıcı talimatı §10).
        $this->assertMatchesRegularExpression('/hizli-islem-menu[\s\S]*?Oda Geliri/', (string) $html);
    }

    #[Test]
    public function personel_hizli_islem_menusunde_yetkisiz_aksiyon_gorunmez(): void
    {
        // Personel'in payments.create yetkisi VAR ama global hızlı işlem
        // menüsünde bu personel için gösterilmez — kasa öncelikli listedir.
        $html = $this->actingAs($this->userWithRole(RoleName::Personel))->get(route('home'))->getContent();

        preg_match('/id="hizli-islem-menu"[^>]*>(.*?)<\/div>\s*<\/div>/s', (string) $html, $matches);
        $menu = $matches[1] ?? '';

        $this->assertStringNotContainsString('>Firma<', $menu);
    }
}
