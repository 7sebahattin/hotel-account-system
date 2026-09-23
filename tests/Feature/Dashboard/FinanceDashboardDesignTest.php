<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Models\User;
use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Authorization\Models\Role;
use App\Modules\Companies\Models\Company;
use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
use App\Modules\Dashboard\Services\DashboardService;
use App\Modules\Dashboard\Support\ChartMonth;
use App\Modules\Dashboard\Support\MonthlyChart;
use App\Modules\Ledger\Services\LedgerService;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Services\PaymentService;
use App\Modules\Stays\Services\StayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Finans panosu yeni tasarımı (23 Eylül 2026): KPI alt satırları, aylık
 * grafik, cari özeti, sekmeli alt tablo ve son güncellemeler.
 *
 * En kritik değişmez yine para birimi ayrımıdır (CLAUDE.md §6/L5): grafik,
 * "Genel bakiye" kolonu ve borç/alacak listesi yalnızca seçili para birimini
 * gösterir.
 */
class FinanceDashboardDesignTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        $this->actor = $this->userWithRole(RoleName::Admin);
        Carbon::setTestNow(Carbon::create(2026, 9, 15, 10));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @param  array<string, mixed>  $overrides */
    private function stay(Company $company, string $in, string $out, string $amount, string $currency = 'TRY', array $overrides = []): void
    {
        app(StayService::class)->create(array_merge([
            'company_id' => $company->id,
            'guest_name' => 'Misafir',
            'check_in_date' => $in,
            'check_out_date' => $out,
            'pricing_mode' => 'total',
            'amount' => $amount,
            'currency' => $currency,
        ], $currency === 'TRY' ? [] : ['exchange_rate_to_try' => '40.0000'], $overrides), $this->actor);
    }

    private function payment(Company $company, string $date, string $amount, string $currency = 'TRY'): Payment
    {
        return app(PaymentService::class)->create(array_merge([
            'company_id' => $company->id,
            'payment_date' => $date,
            'amount' => $amount,
            'currency' => $currency,
        ], $currency === 'TRY' ? [] : ['exchange_rate_to_try' => '40.0000']), $this->actor);
    }

    // ------------------------------------------------------ KPI alt satırları

    #[Test]
    public function bu_ay_konaklama_adedi_gece_ve_tahsilat_adedi_hesaplanir(): void
    {
        $company = Company::factory()->create();

        $this->stay($company, '2026-09-01', '2026-09-04', '3000.00'); // 3 gece
        $this->stay($company, '2026-09-10', '2026-09-12', '1000.00'); // 2 gece
        $this->stay($company, '2026-08-01', '2026-08-03', '9999.00'); // geçen ay
        $this->payment($company, '2026-09-05', '500.00');

        $try = app(DashboardService::class)->finance(Carbon::today())->selected();

        $this->assertNotNull($try);
        $this->assertSame(2, $try->staysCountThisMonth);
        $this->assertSame(5, $try->nightsThisMonth);
        $this->assertSame(1, $try->paymentsCountThisMonth);
        $this->assertSame('4000.00', $try->staysThisMonth->toDecimal());
    }

    // -------------------------------------------------------------- grafik

    #[Test]
    public function aylik_grafik_para_birimi_bazinda_ve_ay_sonu_net_bakiyeyle_uretilir(): void
    {
        $company = Company::factory()->create();

        // Aralık öncesi (devreden): Mart'ta 1000 borç.
        $this->stay($company, '2026-03-01', '2026-03-02', '1000.00');
        // Nisan: 2000 konaklama; Mayıs: 500 tahsilat.
        $this->stay($company, '2026-04-10', '2026-04-12', '2000.00');
        $this->payment($company, '2026-05-03', '500.00');
        // Eylül: iptal edilen tahsilat sayılmaz.
        $cancelled = $this->payment($company, '2026-09-02', '300.00');
        app(PaymentService::class)->cancel($cancelled, 'Yanlış giriş', $this->actor);
        // Başka para birimi grafiğe sızmaz.
        $this->stay($company, '2026-09-01', '2026-09-03', '777.00', 'EUR');

        $chart = app(DashboardService::class)->finance(Carbon::today(), months: 6)->chart;

        $this->assertNotNull($chart);
        $this->assertSame(Currency::TRY, $chart->currency);
        $this->assertSame(
            ['2026-04', '2026-05', '2026-06', '2026-07', '2026-08', '2026-09'],
            array_map(static fn (ChartMonth $month): string => $month->key, $chart->months),
        );
        $this->assertSame('Nis 2026', $chart->months[0]->label);

        $byKey = [];
        foreach ($chart->months as $month) {
            $byKey[$month->key] = $month;
        }

        $this->assertSame('2000.00', $byKey['2026-04']->stays->toDecimal());
        $this->assertSame('500.00', $byKey['2026-05']->payments->toDecimal());
        $this->assertSame('0.00', $byKey['2026-09']->payments->toDecimal());
        $this->assertSame('0.00', $byKey['2026-09']->stays->toDecimal(), 'EUR konaklama TRY grafiğine girmez.');

        // Ay sonu net = devreden 1000 + Nisan 2000 = 3000; Mayıs sonrası 2500.
        $this->assertSame('3000.00', $byKey['2026-04']->net->toDecimal());
        $this->assertSame('2500.00', $byKey['2026-05']->net->toDecimal());
        $this->assertSame('2500.00', $byKey['2026-09']->net->toDecimal());
    }

    #[Test]
    public function grafik_donemi_yalnizca_6_veya_12_ay_olabilir(): void
    {
        $company = Company::factory()->create();
        $this->stay($company, '2026-09-01', '2026-09-02', '100.00');

        $service = app(DashboardService::class);

        $this->assertCount(12, $service->finance(Carbon::today(), months: 12)->chart->months);
        $this->assertCount(6, $service->finance(Carbon::today(), months: 99)->chart->months);
    }

    #[Test]
    public function grafik_olcegi_tamsayi_ve_guzel_adimlidir(): void
    {
        $months = [
            new ChartMonth('2026-08', 'Ağu 2026', Money::fromDecimal('120000.00'), Money::fromDecimal('60000.00'), Money::fromDecimal('180000.00')),
            new ChartMonth('2026-09', 'Eyl 2026', Money::fromDecimal('80000.00'), Money::fromDecimal('40000.00'), Money::fromDecimal('-20000.00')),
        ];

        $chart = new MonthlyChart(Currency::TRY, $months);

        // Aralık -20.000 … 180.000 → adım 50.000; eksen -50.000 … 200.000.
        $this->assertSame([-5_000_000, 0, 5_000_000, 10_000_000, 15_000_000, 20_000_000], $chart->tickValues);
        $this->assertSame('20.00%', $chart->zeroPosition());
        $this->assertSame('100.00%', $chart->position(20_000_000));
        $this->assertSame('60.00%', $chart->barHeight(Money::fromDecimal('120000.00')));
        $this->assertSame('200.000', $chart->tickLabel(20_000_000));
        $this->assertSame('-50.000', $chart->tickLabel(-5_000_000));
    }

    #[Test]
    public function bos_grafik_sifir_ekseniyle_cizilir(): void
    {
        $chart = new MonthlyChart(Currency::TRY, [
            new ChartMonth('2026-09', 'Eyl 2026', Money::zero(), Money::zero(), Money::zero()),
        ]);

        $this->assertTrue($chart->isEmpty());
        $this->assertSame('0.00%', $chart->zeroPosition());
        $this->assertSame('0.00%', $chart->barHeight(Money::zero()));
    }

    // --------------------------------------------------------- cari özeti

    #[Test]
    public function cari_ozeti_aktif_pasif_hesap_gece_ve_ortalama_gecelik_tutari_verir(): void
    {
        $active = Company::factory()->create(['is_active' => true]);
        Company::factory()->create(['is_active' => false]);
        Company::factory()->create()->delete();

        $this->stay($active, '2026-09-01', '2026-09-04', '1000.00'); // 3 gece
        $this->stay($active, '2026-08-01', '2026-08-02', '500.00'); // 1 gece
        $this->stay($active, '2026-08-05', '2026-08-07', '200.00', 'EUR'); // 2 gece
        $this->payment($active, '2026-09-05', '250.00');
        $this->payment($active, '2026-06-05', '150.00');

        $summary = app(DashboardService::class)->finance(Carbon::today());

        $this->assertSame(1, $summary->activeCompanyCount);
        $this->assertSame(1, $summary->passiveCompanyCount);
        $this->assertSame(3, $summary->totalStayCount);
        $this->assertSame(6, $summary->totalNightCount);
        $this->assertSame('400.00', $summary->lifetimePayments?->toDecimal());
        $this->assertSame(2, $summary->lifetimePaymentCount);
        // TRY: 1500 / 4 gece = 375,00 — EUR konaklaması ortalamaya girmez.
        $this->assertSame('375.00', $summary->averageNightly?->toDecimal());
    }

    // ------------------------------------------------- para birimi seçimi

    #[Test]
    public function varsayilan_para_birimi_try_gecersiz_secim_de_try_ye_duser(): void
    {
        $company = Company::factory()->create();
        $this->stay($company, '2026-09-01', '2026-09-02', '100.00', 'EUR');
        $this->stay($company, '2026-09-01', '2026-09-02', '100.00');

        $service = app(DashboardService::class);

        $this->assertSame(Currency::TRY, $service->finance(Carbon::today())->selectedCurrency);
        $this->assertSame(Currency::EUR, $service->finance(Carbon::today(), currency: 'EUR')->selectedCurrency);
        $this->assertSame(Currency::TRY, $service->finance(Carbon::today(), currency: 'XYZ')->selectedCurrency);
        // Verisi olmayan para birimi seçilemez.
        $this->assertSame(Currency::TRY, $service->finance(Carbon::today(), currency: 'GBP')->selectedCurrency);
    }

    #[Test]
    public function yalnizca_doviz_varsa_ilk_para_birimi_secilir(): void
    {
        $company = Company::factory()->create();
        $this->stay($company, '2026-09-01', '2026-09-02', '100.00', 'EUR');

        $this->assertSame(Currency::EUR, app(DashboardService::class)->finance(Carbon::today())->selectedCurrency);
    }

    // ------------------------------------------------ son hareketler sekmesi

    #[Test]
    public function son_hareketlerde_genel_bakiye_sayfalar_boyunca_dogru_yurur(): void
    {
        $a = Company::factory()->create();
        $b = Company::factory()->create();
        $ledger = app(LedgerService::class);

        // 12 TRY hareket (+100 borç, -30 alacak dönüşümlü) + araya karışan EUR.
        for ($i = 1; $i <= 12; $i++) {
            $date = sprintf('2026-09-%02d', $i);
            $company = $i % 2 === 0 ? $a : $b;

            if ($i % 3 === 0) {
                $ledger->credit(companyId: $company->id, amount: Money::fromDecimal('30.00'), currency: Currency::TRY,
                    entryDate: $date, description: 'Tahsilat', sourceType: 'payment', sourceId: $i, actor: $this->actor);
            } else {
                $ledger->debit(companyId: $company->id, amount: Money::fromDecimal('100.00'), currency: Currency::TRY,
                    entryDate: $date, description: 'Konaklama', sourceType: 'stay', sourceId: $i, actor: $this->actor);
            }

            $ledger->debit(companyId: $a->id, amount: Money::fromDecimal('5000.00'), currency: Currency::EUR,
                entryDate: $date, description: 'EUR', sourceType: 'stay', sourceId: 100 + $i, actor: $this->actor);
        }

        // Toplam net: 8 × 100 − 4 × 30 = 680.
        $service = app(DashboardService::class);

        $first = $service->finance(Carbon::today(), perPage: 5);
        $entries = $first->ledgerEntries;
        $this->assertNotNull($entries);
        $this->assertSame(12, $entries->total(), 'EUR hareketleri TRY sekmesine girmez.');

        $firstEntry = $entries->items()[0];
        $this->assertSame('2026-09-12', $firstEntry->entry_date->toDateString());
        $this->assertSame('680.00', $first->ledgerBalances[$firstEntry->id]->toDecimal());

        // 2. sayfanın ilk satırı: 12..8. günler (5 satır) sonrası.
        // Günler 12(-30),11(+100),10(+100),9(-30),8(+100) → net +240 → 680 − 240 = 440.
        request()->merge(['sayfa' => 2]);
        $second = $service->finance(Carbon::today(), perPage: 5);
        $secondFirst = $second->ledgerEntries->items()[0];
        $this->assertSame('2026-09-07', $secondFirst->entry_date->toDateString());
        $this->assertSame('440.00', $second->ledgerBalances[$secondFirst->id]->toDecimal());

        // Son satırın bakiyesi o satırın kendi neti olmalı (ondan eski hareket yok).
        request()->merge(['sayfa' => 3]);
        $third = $service->finance(Carbon::today(), perPage: 5);
        $items = $third->ledgerEntries->items();
        $last = end($items);
        $this->assertSame('2026-09-01', $last->entry_date->toDateString());
        $this->assertSame('100.00', $third->ledgerBalances[$last->id]->toDecimal());
    }

    #[Test]
    public function borc_alacak_sekmesi_iki_yonu_de_mutlak_tutara_gore_listeler(): void
    {
        $owes = Company::factory()->create(['name' => 'Bize Borçlu']);
        $credit = Company::factory()->create(['name' => 'Avanslı']);
        $zero = Company::factory()->create(['name' => 'Kapalı']);
        $ledger = app(LedgerService::class);

        $ledger->debit(companyId: $owes->id, amount: Money::fromDecimal('100.00'), currency: Currency::TRY,
            entryDate: '2026-09-01', description: 'K', sourceType: 'stay', sourceId: 1, actor: $this->actor);
        $ledger->credit(companyId: $credit->id, amount: Money::fromDecimal('250.00'), currency: Currency::TRY,
            entryDate: '2026-09-01', description: 'T', sourceType: 'payment', sourceId: 2, actor: $this->actor);
        $ledger->debit(companyId: $zero->id, amount: Money::fromDecimal('50.00'), currency: Currency::TRY,
            entryDate: '2026-09-01', description: 'K', sourceType: 'stay', sourceId: 3, actor: $this->actor);
        $ledger->credit(companyId: $zero->id, amount: Money::fromDecimal('50.00'), currency: Currency::TRY,
            entryDate: '2026-09-02', description: 'T', sourceType: 'payment', sourceId: 4, actor: $this->actor);

        $rows = app(DashboardService::class)->finance(Carbon::today(), tab: 'borc-alacak')->accountBalances;

        $this->assertSame(['Avanslı', 'Bize Borçlu'], array_map(static fn ($row) => $row->companyName, $rows));
        $this->assertSame('-250.00', $rows[0]->balance->toDecimal());

        $this->actingAs($this->actor)->get(route('home', ['tab' => 'borc-alacak']))
            ->assertOk()
            ->assertSee('Biz borçluyuz')
            ->assertSee('Bize borçlu')
            ->assertDontSee('Kapalı');
    }

    // --------------------------------------------------------------- ekran

    #[Test]
    public function finans_panosu_yeni_bloklari_render_eder(): void
    {
        $company = Company::factory()->create(['name' => 'Görünür Otel Müşterisi']);
        $this->stay($company, '2026-09-01', '2026-09-03', '1500.00');
        $this->payment($company, '2026-09-05', '400.00');

        $this->actingAs($this->actor)->get(route('home'))
            ->assertOk()
            ->assertSee('data-dashboard-chart', false)
            ->assertSee('Son 6 Ay Cari Hareketler')
            ->assertSee('Para Birimi Bazında Durum')
            ->assertSee('Cari Özeti')
            ->assertSee('En yüksek bakiyeli firmalar')
            ->assertSee('Son Güncellemeler')
            ->assertSee('Tahsilat kaydı oluşturuldu')
            ->assertSee('1 konaklama · 2 gece')
            ->assertSee('1 tahsilat')
            ->assertSee('Genel bakiye')
            ->assertSee('Görünür Otel Müşterisi')
            // Hızlı işlemler kartı kullanıcı kararıyla YOKTUR; Notlar/Belgeler sekmesi de yok.
            ->assertDontSee('Hızlı İşlemler')
            ->assertDontSee('Belgeler');
    }

    #[Test]
    public function son_guncellemeler_denetim_yetkisi_olmayan_finans_kullanicisina_gosterilmez(): void
    {
        $company = Company::factory()->create();
        $this->stay($company, '2026-09-01', '2026-09-03', '1500.00');

        // Denetim yetkisi alınmış ama finans panosunu gören bir rol.
        Role::byName(RoleName::Patron->value)->revokePermissionTo('audit.view');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($this->userWithRole(RoleName::Patron))->get(route('home'))
            ->assertOk()
            ->assertSee('Cari Özeti')
            ->assertDontSee('Son Güncellemeler');
    }

    #[Test]
    public function sekmeler_yalnizca_kendi_verisini_sorgular_ve_render_eder(): void
    {
        $company = Company::factory()->create(['name' => 'Sekme Firması']);
        $this->stay($company, '2026-09-20', '2026-09-25', '800.00', 'TRY', ['guest_name' => 'Yaklaşan Misafir']);
        $this->payment($company, '2026-09-05', '400.00');

        $this->actingAs($this->actor)->get(route('home', ['tab' => 'konaklamalar']))
            ->assertOk()
            ->assertSee('Yaklaşan Misafir')
            ->assertSee('Tüm konaklamalar')
            ->assertDontSee('Genel bakiye');

        $this->actingAs($this->actor)->get(route('home', ['tab' => 'tahsilatlar']))
            ->assertOk()
            ->assertSee('400,00')
            ->assertDontSee('Yaklaşan Misafir');

        // Geçersiz sekme varsayılana düşer.
        $this->actingAs($this->actor)->get(route('home', ['tab' => 'belgeler']))
            ->assertOk()
            ->assertSee('Genel bakiye');
    }

    #[Test]
    public function personel_yeni_finans_bloklarini_goremez(): void
    {
        $company = Company::factory()->create();
        $this->stay($company, '2026-09-01', '2026-09-03', '1500.00');

        $this->actingAs($this->userWithRole(RoleName::Personel))
            ->get(route('home', ['tab' => 'borc-alacak', 'currency' => 'TRY']))
            ->assertOk()
            ->assertDontSee('data-dashboard-chart', false)
            ->assertDontSee('Cari Özeti')
            ->assertDontSee('Son Güncellemeler')
            ->assertDontSee('1.500,00');
    }
}
