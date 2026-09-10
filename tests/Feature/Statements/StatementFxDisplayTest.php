<?php

declare(strict_types=1);

namespace Tests\Feature\Statements;

use App\Models\User;
use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Companies\Models\Company;
use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
use App\Modules\Ledger\Services\LedgerService;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Services\PaymentService;
use App\Modules\Stays\Models\Stay;
use App\Modules\Stays\Services\StayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * v2.1.1-D §3 — yabancı para birimli tutarların altında TL karşılığı.
 *
 * Kullanıcı talimatı: "Cari ekstrelerde yabancı parabirimi ve altında küçük
 * yazıyla Türk Lirası olarak bakiye görünsün. Sistemin her yerinde döviz
 * cinsi alanları tara." Bu testler ekstre + firma bakiye listesi + firma
 * detayı + dashboard finans görünümünü kapsar.
 */
class StatementFxDisplayTest extends TestCase
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

    private function createEurStay(string $amount, string $rate): Stay
    {
        return app(StayService::class)->create([
            'company_id' => $this->company->id,
            'guest_name' => 'John Smith',
            'check_in_date' => '2026-08-29',
            'check_out_date' => '2026-09-01',
            'pricing_mode' => 'total',
            'amount' => $amount,
            'currency' => 'EUR',
            'exchange_rate_to_try' => $rate,
        ], $this->admin);
    }

    private function createEurPayment(string $amount, string $rate): Payment
    {
        return app(PaymentService::class)->create([
            'company_id' => $this->company->id,
            'payment_date' => '2026-09-02',
            'amount' => $amount,
            'currency' => 'EUR',
            'exchange_rate_to_try' => $rate,
            'payment_method' => 'bank_transfer',
        ], $this->admin);
    }

    #[Test]
    public function ekstrede_dovizli_hareketin_altinda_tl_karsiligi_gorunur(): void
    {
        $this->createEurStay('500.00', '51.20');

        $expected = Money::fromDecimal('500.00')->multiplyByRate('51.20')->format(Currency::TRY);

        $this->actingAs($this->admin)
            ->get(route('statements.show', $this->company))
            ->assertOk()
            ->assertSee('500,00 €')
            ->assertSee('≈ '.$expected);
    }

    #[Test]
    public function ekstre_kapanis_bakiyesi_dogru_net_tl_toplamini_gosterir(): void
    {
        $this->createEurStay('500.00', '50.00');
        $this->createEurPayment('200.00', '55.00');

        // 25.000,00 (borç) - 11.000,00 (alacak) = 14.000,00 net.
        $expectedNet = Money::fromDecimal('14000.00')->format(Currency::TRY);

        $this->actingAs($this->admin)
            ->get(route('statements.show', $this->company))
            ->assertOk()
            ->assertSee('≈ '.$expectedNet);
    }

    #[Test]
    public function iptal_edilen_tahsilatin_tl_katkisi_net_sifira_iner(): void
    {
        $this->createEurStay('500.00', '50.00');
        $payment = $this->createEurPayment('200.00', '55.00');

        app(PaymentService::class)->cancel($payment, 'Mükerrer', $this->admin);

        // Tahsilat tamamen ters kaydedildiği için kapanış yalnızca konaklamanın
        // TL karşılığını (25.000,00) taşımalıdır — reversal + orijinal AYNI
        // kayıtlı `try_equivalent`i kullandığı için net sıfıra iner.
        $expected = Money::fromDecimal('25000.00')->format(Currency::TRY);

        $this->actingAs($this->admin)
            ->get(route('statements.show', $this->company))
            ->assertOk()
            ->assertSee('≈ '.$expected);
    }

    #[Test]
    public function ekstre_devreden_bakiyenin_tl_karsiligi_gorunur(): void
    {
        $this->createEurStay('500.00', '51.20');

        $expected = Money::fromDecimal('25600.00')->format(Currency::TRY);

        $this->actingAs($this->admin)
            ->get(route('statements.show', [$this->company, 'date_from' => '2026-09-02']))
            ->assertOk()
            ->assertSee('Devreden bakiye')
            ->assertSee('≈ '.$expected);
    }

    /**
     * Bu turdan ÖNCEKİ, kuru hiç kaydedilmemiş yabancı para birimli bir
     * kayıt: TL zinciri o noktadan itibaren "eksik" işaretlenir, sessizce
     * yanlış bir rakam ÜRETİLMEZ (CLAUDE.md §13 dürüstlük ilkesi).
     */
    #[Test]
    public function kuru_eksik_gecmis_kayitta_eksik_veri_notu_gorunur(): void
    {
        $stay = Stay::factory()->for($this->company)->create([
            'currency' => 'EUR',
            'amount' => '500.00',
            'exchange_rate_to_try' => null,
            'try_equivalent' => null,
        ]);

        app(LedgerService::class)->debit(
            companyId: $this->company->id,
            amount: Money::fromDecimal('500.00'),
            currency: Currency::EUR,
            entryDate: '2026-09-01',
            description: 'Konaklama: '.$stay->guest_name,
            sourceType: 'stay',
            sourceId: $stay->id,
            actor: $this->admin,
        );

        $this->actingAs($this->admin)
            ->get(route('statements.show', $this->company))
            ->assertOk()
            ->assertSee('500,00 €')
            ->assertSee('eksik veri')
            ->assertDontSee('≈', false);
    }

    #[Test]
    public function firma_bakiye_listesinde_tl_karsiligi_gorunur(): void
    {
        $this->createEurStay('500.00', '51.20');

        $expected = Money::fromDecimal('25600.00')->format(Currency::TRY);

        $this->actingAs($this->admin)
            ->get(route('statements.index'))
            ->assertOk()
            ->assertSee('Monarch')
            ->assertSee('≈ '.$expected);
    }

    #[Test]
    public function firma_detayinda_cari_bakiye_tl_karsiligi_gorunur(): void
    {
        $this->createEurStay('500.00', '51.20');

        $expected = Money::fromDecimal('25600.00')->format(Currency::TRY);

        $this->actingAs($this->admin)
            ->get(route('companies.show', $this->company))
            ->assertOk()
            ->assertSee('Cari bakiye')
            ->assertSee('≈ '.$expected);
    }

    #[Test]
    public function dashboard_finans_gorunumunde_net_bakiyenin_tl_karsiligi_gorunur(): void
    {
        $this->createEurStay('500.00', '51.20');

        $expected = Money::fromDecimal('25600.00')->format(Currency::TRY);

        $this->actingAs($this->admin)
            ->get(route('home', ['view' => 'finance']))
            ->assertOk()
            ->assertSee('Net bakiye')
            ->assertSee('≈ '.$expected);
    }

    #[Test]
    public function try_bakiyenin_altinda_tl_karsiligi_gosterilmez(): void
    {
        app(LedgerService::class)->debit(
            companyId: $this->company->id,
            amount: Money::fromDecimal('1000.00'),
            currency: Currency::TRY,
            entryDate: '2026-09-01',
            description: 'Konaklama',
            sourceType: 'stay',
            sourceId: 1,
            actor: $this->admin,
        );

        $this->actingAs($this->admin)
            ->get(route('statements.show', $this->company))
            ->assertOk()
            ->assertSee('1.000,00 ₺')
            ->assertDontSee('≈', false);
    }
}
