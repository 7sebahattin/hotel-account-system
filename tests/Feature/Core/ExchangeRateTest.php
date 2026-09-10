<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Companies\Models\Company;
use App\Modules\Core\Support\Money;
use App\Modules\Payments\Models\Payment;
use App\Modules\Stays\Models\Stay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * v2.1.1-C §2 — yeni kayıtlarda kur yakalama (Stays + Payments).
 *
 * Ledger'a yazılan tutar HER ZAMAN orijinal para birimindedir; bu testler
 * ayrıca `LedgerEntry.currency`nin TRY'ye ÇEVRİLMEDİĞİNİ doğrular (§6/L5).
 */
class ExchangeRateTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        $this->company = Company::factory()->create();
    }

    #[Test]
    public function try_konaklamada_kur_zorunlu_degildir(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $this->actingAs($admin)->post(route('stays.store'), [
            'company_id' => $this->company->id,
            'guest_name' => 'Ahmet Yılmaz',
            'check_in_date' => '2026-09-01',
            'check_out_date' => '2026-09-03',
            'pricing_mode' => 'total',
            'amount' => '1000,00',
            'currency' => 'TRY',
        ])->assertSessionDoesntHaveErrors();

        $stay = Stay::query()->firstOrFail();
        $this->assertNull($stay->exchange_rate_to_try);
        $this->assertNull($stay->try_equivalent);
    }

    #[Test]
    public function yabanci_para_birimli_konaklamada_kur_zorunludur(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $this->actingAs($admin)->post(route('stays.store'), [
            'company_id' => $this->company->id,
            'guest_name' => 'Ahmet Yılmaz',
            'check_in_date' => '2026-09-01',
            'check_out_date' => '2026-09-03',
            'pricing_mode' => 'total',
            'amount' => '500,00',
            'currency' => 'EUR',
        ])->assertSessionHasErrors('exchange_rate_to_try');

        $this->assertSame(0, Stay::query()->count());
    }

    #[Test]
    public function konaklama_tl_karsiligini_ornekteki_gibi_hesaplar(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $this->actingAs($admin)->post(route('stays.store'), [
            'company_id' => $this->company->id,
            'guest_name' => 'Ahmet Yılmaz',
            'check_in_date' => '2026-09-01',
            'check_out_date' => '2026-09-03',
            'pricing_mode' => 'total',
            'amount' => '500,00',
            'currency' => 'EUR',
            'exchange_rate_to_try' => '51,20',
        ])->assertSessionDoesntHaveErrors();

        $stay = Stay::query()->firstOrFail();
        $this->assertSame('51.2000', $stay->exchange_rate_to_try);
        $this->assertSame('25600.00', $stay->try_equivalent);
    }

    #[Test]
    public function konaklama_kur_gecmis_deger_zaman_gectikce_degismez(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $this->actingAs($admin)->post(route('stays.store'), [
            'company_id' => $this->company->id,
            'guest_name' => 'Ahmet Yılmaz',
            'check_in_date' => '2026-09-01',
            'check_out_date' => '2026-09-03',
            'pricing_mode' => 'total',
            'amount' => '500,00',
            'currency' => 'EUR',
            'exchange_rate_to_try' => '51,20',
        ]);

        $stay = Stay::query()->firstOrFail();
        $original = $stay->try_equivalent;

        Carbon::setTestNow(now()->addDays(60));
        $reloaded = Stay::query()->findOrFail($stay->id);

        $this->assertSame($original, $reloaded->try_equivalent);
        $this->assertSame('25600.00', $reloaded->try_equivalent);

        Carbon::setTestNow();
    }

    #[Test]
    public function try_tahsilatta_kur_zorunlu_degildir(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $this->actingAs($admin)->post(route('payments.store'), [
            'company_id' => $this->company->id,
            'payment_date' => '2026-09-08',
            'amount' => '2000,00',
            'currency' => 'TRY',
        ])->assertSessionDoesntHaveErrors();

        $payment = Payment::query()->firstOrFail();
        $this->assertNull($payment->exchange_rate_to_try);
        $this->assertNull($payment->try_equivalent);
    }

    #[Test]
    public function yabanci_para_birimli_tahsilatta_kur_zorunludur(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $this->actingAs($admin)->post(route('payments.store'), [
            'company_id' => $this->company->id,
            'payment_date' => '2026-09-08',
            'amount' => '500,00',
            'currency' => 'USD',
        ])->assertSessionHasErrors('exchange_rate_to_try');

        $this->assertSame(0, Payment::query()->count());
    }

    #[Test]
    public function tahsilat_tl_karsiligini_dogru_hesaplar(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $this->actingAs($admin)->post(route('payments.store'), [
            'company_id' => $this->company->id,
            'payment_date' => '2026-09-08',
            'amount' => '200,00',
            'currency' => 'USD',
            'exchange_rate_to_try' => '34,10',
        ])->assertSessionDoesntHaveErrors();

        $payment = Payment::query()->firstOrFail();
        $this->assertSame('34.1000', $payment->exchange_rate_to_try);
        $this->assertSame(
            Money::fromDecimal('200.00')->multiplyByRate('34.10')->toDecimal(),
            $payment->try_equivalent,
        );
    }

    #[Test]
    public function ledger_kaydi_her_zaman_orijinal_para_biriminde_kalir(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $this->actingAs($admin)->post(route('payments.store'), [
            'company_id' => $this->company->id,
            'payment_date' => '2026-09-08',
            'amount' => '200,00',
            'currency' => 'USD',
            'exchange_rate_to_try' => '34,10',
        ]);

        $this->assertDatabaseHas('ledger_entries', [
            'company_id' => $this->company->id,
            'currency' => 'USD',
            'credit' => '200.00',
        ]);
        $this->assertDatabaseMissing('ledger_entries', ['currency' => 'TRY']);
    }
}
