<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Modules\AuditLog\Enums\AuditEvent;
use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Companies\Models\Company;
use App\Modules\Ledger\Models\LedgerEntry;
use App\Modules\Payments\Exceptions\ExchangeRateAlreadyRecorded as PaymentExchangeRateAlreadyRecorded;
use App\Modules\Payments\Services\PaymentService;
use App\Modules\Stays\Exceptions\ExchangeRateAlreadyRecorded as StayExchangeRateAlreadyRecorded;
use App\Modules\Stays\Models\Stay;
use App\Modules\Stays\Services\StayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Yönetim → Geçmiş Döviz Düzeltme (v2.1.1-C §3).
 *
 * Bu araç yalnızca EKSİK kur bilgisini doldurur: orijinal tutar/para birimi
 * değişmez, ikinci bir finansal işlem oluşmaz, cari hareketi ASLA ters
 * kaydedilmez/yeniden işlenmez.
 */
class HistoricalFxCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private StayService $stays;

    private PaymentService $payments;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        $this->company = Company::factory()->create();
        $this->stays = app(StayService::class);
        $this->payments = app(PaymentService::class);
    }

    /** Eski (bu özellikten önce oluşturulmuş) bir EUR konaklaması — kur hiç yakalanmadı. */
    private function legacyStay(): Stay
    {
        $stay = Stay::factory()->create([
            'company_id' => $this->company->id,
            'currency' => 'EUR',
            'amount' => '500.00',
            'exchange_rate_to_try' => null,
            'try_equivalent' => null,
        ]);

        // Factory zaten kendi ledger kaydını üretmez; testin odağı burada
        // yalnızca düzeltme aracının ledger'a HİÇ dokunmadığını KANITLAMAKTIR.
        return $stay->fresh();
    }

    #[Test]
    public function eksik_kurlu_konaklamalar_kuyrukta_listelenir(): void
    {
        $missing = $this->legacyStay();
        Stay::factory()->create([
            'company_id' => $this->company->id,
            'currency' => 'TRY',
        ]);
        Stay::factory()->create([
            'company_id' => $this->company->id,
            'currency' => 'USD',
            'exchange_rate_to_try' => '32.5000',
            'try_equivalent' => '1000.00',
        ]);

        $queue = $this->stays->missingExchangeRate();

        $this->assertCount(1, $queue);
        $this->assertSame($missing->id, $queue->first()->id);
    }

    #[Test]
    public function konaklama_kuru_duzeltilince_yalnizca_metadata_yazilir(): void
    {
        $stay = $this->legacyStay();
        $ledgerCountBefore = LedgerEntry::query()->count();
        $admin = $this->userWithRole(RoleName::Admin);

        $this->stays->correctHistoricalExchangeRate($stay, '51,20', $admin);

        $stay->refresh();
        $this->assertSame('51.2000', $stay->exchange_rate_to_try);
        $this->assertSame('25600.00', $stay->try_equivalent);
        // Orijinal tutar/para birimi DEĞİŞMEDİ.
        $this->assertSame('500.00', $stay->amount);
        $this->assertSame('EUR', $stay->currency->value);
        // İkinci bir finansal işlem / ters kayıt OLUŞMADI.
        $this->assertSame($ledgerCountBefore, LedgerEntry::query()->count());

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => 'stay',
            'auditable_id' => $stay->id,
            'event' => AuditEvent::ExchangeRateCorrected->value,
        ]);
    }

    #[Test]
    public function konaklama_kuru_ikinci_kez_duzeltilemez(): void
    {
        $stay = $this->legacyStay();
        $admin = $this->userWithRole(RoleName::Admin);

        $this->stays->correctHistoricalExchangeRate($stay, '51,20', $admin);

        $this->expectException(StayExchangeRateAlreadyRecorded::class);
        $this->stays->correctHistoricalExchangeRate($stay->fresh(), '60,00', $admin);
    }

    #[Test]
    public function tahsilat_kuru_duzeltilince_yalnizca_metadata_yazilir(): void
    {
        $payment = $this->payments->create([
            'company_id' => $this->company->id,
            'payment_date' => '2026-09-08',
            'amount' => '200.00',
            'currency' => 'USD',
        ], $this->userWithRole(RoleName::Admin));

        $ledgerCountBefore = LedgerEntry::query()->count();
        $admin = $this->userWithRole(RoleName::Admin);

        $this->payments->correctHistoricalExchangeRate($payment, '34,10', $admin);

        $payment->refresh();
        $this->assertSame('34.1000', $payment->exchange_rate_to_try);
        $this->assertSame('200.00', $payment->amount);
        $this->assertSame('USD', $payment->currency->value);
        $this->assertSame($ledgerCountBefore, LedgerEntry::query()->count());
    }

    #[Test]
    public function tahsilat_kuru_ikinci_kez_duzeltilemez(): void
    {
        $payment = $this->payments->create([
            'company_id' => $this->company->id,
            'payment_date' => '2026-09-08',
            'amount' => '200.00',
            'currency' => 'USD',
        ], $this->userWithRole(RoleName::Admin));

        $admin = $this->userWithRole(RoleName::Admin);
        $this->payments->correctHistoricalExchangeRate($payment, '34,10', $admin);

        $this->expectException(PaymentExchangeRateAlreadyRecorded::class);
        $this->payments->correctHistoricalExchangeRate($payment->fresh(), '40,00', $admin);
    }

    #[Test]
    public function personel_duzeltme_ekranina_erisemez(): void
    {
        $stay = $this->legacyStay();
        $personel = $this->userWithRole(RoleName::Personel);

        $this->actingAs($personel)->get(route('fx-correction.index'))->assertForbidden();

        $this->actingAs($personel)
            ->patch(route('fx-correction.stays.update', $stay), ['exchange_rate_to_try' => '51,20'])
            ->assertForbidden();

        $this->assertNull($stay->fresh()->exchange_rate_to_try);
    }

    #[Test]
    public function admin_ve_patron_duzeltme_ekranina_erisir(): void
    {
        $stay = $this->legacyStay();

        foreach ([RoleName::Admin, RoleName::Patron] as $role) {
            $user = $this->userWithRole($role);
            $this->actingAs($user)->get(route('fx-correction.index'))->assertOk();
        }

        $admin = $this->userWithRole(RoleName::Admin);
        $this->actingAs($admin)
            ->patch(route('fx-correction.stays.update', $stay), ['exchange_rate_to_try' => '51,20'])
            ->assertRedirect();

        $this->assertSame('51.2000', $stay->fresh()->exchange_rate_to_try);
    }
}
