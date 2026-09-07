<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Models\User;
use App\Modules\AuditLog\Services\AuditLogger;
use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Companies\Models\Company;
use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
use App\Modules\Ledger\Models\LedgerEntry;
use App\Modules\Ledger\Services\LedgerService;
use App\Modules\Payments\Exceptions\PaymentAlreadyCancelled;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Services\PaymentService;
use App\Modules\Stays\Services\StayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * DEĞİŞMEZ: aktif bir tahsilatın tam olarak BİR geçerli alacak hareketi vardır;
 * iptal edilmiş tahsilatın hiç yoktur.
 */
class PaymentLedgerIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $actor;

    private PaymentService $payments;

    private LedgerService $ledger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        $this->company = Company::factory()->create(['name' => 'Monarch']);
        $this->actor = $this->userWithRole(RoleName::Admin);
        $this->payments = app(PaymentService::class);
        $this->ledger = app(LedgerService::class);
    }

    /** @param array<string, mixed> $overrides */
    private function makePayment(array $overrides = []): Payment
    {
        return $this->payments->create(array_merge([
            'company_id' => $this->company->id,
            'payment_date' => '2026-09-08',
            'amount' => '5000.00',
            'currency' => 'TRY',
            'payment_method' => 'bank_transfer',
        ], $overrides), $this->actor);
    }

    private function balance(Currency $currency = Currency::TRY): string
    {
        return $this->ledger->balanceForCompany($this->company->id, $currency)->toDecimal();
    }

    private function assertExactlyOneActiveCredit(Payment $payment, string $amount, Currency $currency = Currency::TRY): void
    {
        $active = $this->ledger->activeEntriesForSource(Payment::LEDGER_SOURCE_TYPE, $payment->getKey());

        $this->assertCount(1, $active, 'Tahsilatın tam olarak bir geçerli hareketi olmalı');
        $this->assertSame($amount, $active->first()->credit);
        $this->assertSame('0.00', $active->first()->debit);
        $this->assertSame($currency, $active->first()->currency);
    }

    #[Test]
    public function tahsilat_olusturuldugunda_alacak_hareketi_dogar(): void
    {
        $payment = $this->makePayment();

        $entry = LedgerEntry::query()->firstOrFail();

        $this->assertSame($this->company->id, $entry->company_id);
        $this->assertSame('payment', $entry->source_type);
        $this->assertSame($payment->id, $entry->source_id);
        $this->assertSame('5000.00', $entry->credit);
        $this->assertSame('0.00', $entry->debit);
        $this->assertSame('2026-09-08', $entry->entry_date->toDateString());
        $this->assertFalse($entry->is_reversal);
    }

    #[Test]
    public function tahsilat_bakiyeyi_dusurur(): void
    {
        // Konaklama 9.000 borç, tahsilat 5.000 alacak → 4.000 borç.
        app(StayService::class)->create([
            'company_id' => $this->company->id,
            'guest_name' => 'John Smith',
            'check_in_date' => '2026-09-04',
            'check_out_date' => '2026-09-07',
            'pricing_mode' => 'total',
            'amount' => '9000.00',
            'currency' => 'TRY',
        ], $this->actor);

        $this->assertSame('9000.00', $this->balance());

        $this->makePayment();

        $this->assertSame('4000.00', $this->balance());
    }

    /** İstenen tam senaryo: 9.000 borç → 5.000 tahsilat → iptal → tekrar 9.000. */
    #[Test]
    public function tahsilat_iptali_bakiyeyi_eski_haline_dondurur(): void
    {
        app(StayService::class)->create([
            'company_id' => $this->company->id,
            'guest_name' => 'John Smith',
            'check_in_date' => '2026-09-04',
            'check_out_date' => '2026-09-07',
            'pricing_mode' => 'total',
            'amount' => '9000.00',
            'currency' => 'TRY',
        ], $this->actor);

        $payment = $this->makePayment();
        $this->assertSame('4000.00', $this->balance());

        $this->payments->cancel($payment, 'Mükerrer kayıt', $this->actor);

        $this->assertSame('9000.00', $this->balance());
        $this->assertSame(3, LedgerEntry::query()->count(), 'Hiçbir satır silinmez: 9000 D, 5000 C, 5000 D');

        $reversal = LedgerEntry::query()->where('is_reversal', true)->firstOrFail();
        $this->assertSame('5000.00', $reversal->debit);
        $this->assertSame('0.00', $reversal->credit);
        $this->assertSame('2026-09-08', $reversal->entry_date->toDateString(), 'Ters kayıt orijinalin tarihini taşır');
    }

    #[Test]
    public function iptal_sonrasi_gecerli_hareket_kalmaz(): void
    {
        $payment = $this->makePayment();
        $this->assertExactlyOneActiveCredit($payment, '5000.00');

        $this->payments->cancel($payment, 'Yanlış firma', $this->actor);

        $this->assertCount(
            0,
            $this->ledger->activeEntriesForSource(Payment::LEDGER_SOURCE_TYPE, $payment->getKey()),
            'İptal edilmiş tahsilatın geçerli hareketi kalmamalı',
        );
    }

    #[Test]
    public function farkli_para_birimleri_ayri_bakiye_uretir(): void
    {
        $this->makePayment(['amount' => '5000.00', 'currency' => 'TRY']);
        $this->makePayment(['amount' => '300.00', 'currency' => 'EUR']);
        $this->makePayment(['amount' => '200.00', 'currency' => 'GBP']);

        $this->assertSame('-5000.00', $this->balance(Currency::TRY));
        $this->assertSame('-300.00', $this->balance(Currency::EUR));
        $this->assertSame('-200.00', $this->balance(Currency::GBP));

        $byCurrency = $this->ledger->balancesForCompanyByCurrency($this->company->id);

        $this->assertSame('-5000.00', $byCurrency['TRY']->toDecimal());
        $this->assertSame('-300.00', $byCurrency['EUR']->toDecimal());
        $this->assertSame('-200.00', $byCurrency['GBP']->toDecimal());
    }

    #[Test]
    public function metadata_guncellemesi_cari_hareketi_uretmez(): void
    {
        $payment = $this->makePayment();

        $this->payments->updateMetadata($payment, [
            'reference_no' => 'YENI-REF',
            'description' => 'Düzeltilmiş açıklama',
        ], $this->actor);

        $this->assertSame(1, LedgerEntry::query()->count());
        $this->assertSame('-5000.00', $this->balance());
        $this->assertExactlyOneActiveCredit($payment->fresh(), '5000.00');
    }

    /** Cari yazılamazsa tahsilat da oluşmaz — tek transaction (CLAUDE.md §6/L6). */
    #[Test]
    public function ledger_yazilamazsa_tahsilat_da_olusmaz(): void
    {
        $this->app->bind(LedgerService::class, fn () => new class(app(AuditLogger::class)) extends LedgerService
        {
            public function credit(
                int $companyId, Money $amount, Currency $currency,
                \DateTimeInterface|string $entryDate, string $description,
                ?string $sourceType = null, ?int $sourceId = null, ?User $actor = null,
            ): LedgerEntry {
                throw new RuntimeException('Cari yazılamadı');
            }
        });

        try {
            app(PaymentService::class)->create([
                'company_id' => $this->company->id,
                'payment_date' => '2026-09-08',
                'amount' => '5000.00',
                'currency' => 'TRY',
            ], $this->actor);
            $this->fail('Cari yazılamadığında istisna beklenir');
        } catch (RuntimeException) {
            // beklenen
        }

        $this->assertSame(0, Payment::query()->count(), 'Tahsilat geri alınmalı');
        $this->assertSame(0, LedgerEntry::query()->count());
    }

    #[Test]
    public function ikinci_kez_iptal_edilemez(): void
    {
        $payment = $this->makePayment();
        $this->payments->cancel($payment, 'İlk iptal', $this->actor);

        $this->expectException(PaymentAlreadyCancelled::class);

        $this->payments->cancel($payment->fresh(), 'İkinci iptal', $this->actor);
    }

    #[Test]
    public function ikinci_iptal_denemesi_ek_ters_kayit_uretmez(): void
    {
        $payment = $this->makePayment();
        $this->payments->cancel($payment, 'İlk iptal', $this->actor);

        try {
            $this->payments->cancel($payment->fresh(), 'İkinci iptal', $this->actor);
        } catch (PaymentAlreadyCancelled) {
            // beklenen
        }

        $this->assertSame(2, LedgerEntry::query()->count());
        $this->assertSame('0.00', $this->balance());
    }
}
