<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Models\User;
use App\Modules\AuditLog\Models\AuditLog;
use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Companies\Models\Company;
use App\Modules\Core\Enums\Currency;
use App\Modules\Ledger\Models\LedgerEntry;
use App\Modules\Ledger\Services\LedgerService;
use App\Modules\Payments\Enums\PaymentMethod;
use App\Modules\Payments\Exceptions\CannotUpdateCancelledPayment;
use App\Modules\Payments\Exceptions\PaymentFinancialFieldsAreImmutable;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tahsilatın finansal alanları oluşturulduktan sonra DEĞİŞTİRİLEMEZ.
 * Koruma üç katmandadır: FormRequest, PaymentService ve Payment modeli.
 */
class PaymentImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $admin;

    private PaymentService $payments;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        $this->company = Company::factory()->create(['name' => 'Monarch']);
        $this->admin = $this->userWithRole(RoleName::Admin);
        $this->payments = app(PaymentService::class);
    }

    private function makePayment(): Payment
    {
        return $this->payments->create([
            'company_id' => $this->company->id,
            'payment_date' => '2026-09-08',
            'amount' => '5000.00',
            'currency' => 'TRY',
            'payment_method' => 'bank_transfer',
            'reference_no' => 'ABC123',
        ], $this->admin);
    }

    // ------------------------------------------------- katman 1: FormRequest

    /**
     * HTTP katmanı: finansal alanlar UpdatePaymentRequest'te hiç tanımlı
     * değildir, dolayısıyla gönderilseler bile servise ULAŞMAZLAR.
     */
    #[Test]
    #[DataProvider('finansalAlanlar')]
    public function http_uzerinden_finansal_alan_degistirilemez(string $field, mixed $forgedValue): void
    {
        $payment = $this->makePayment();
        $other = Company::factory()->create(['name' => 'Başka Firma']);

        $value = $field === 'company_id' ? $other->id : $forgedValue;

        $this->actingAs($this->admin)
            ->put(route('payments.update', $payment), [
                'reference_no' => 'ABC123',
                'description' => null,
                $field => $value,
            ])
            ->assertSessionHasNoErrors();

        $fresh = $payment->fresh();

        $this->assertSame($this->company->id, $fresh->company_id);
        $this->assertSame('2026-09-08', $fresh->payment_date->toDateString());
        $this->assertSame('5000.00', $fresh->amount);
        $this->assertSame(Currency::TRY, $fresh->currency);
        $this->assertSame(PaymentMethod::BankTransfer, $fresh->payment_method);

        // Cari hareketi de dokunulmamış olmalı.
        $this->assertSame(1, LedgerEntry::query()->count());
        $this->assertSame('5000.00', LedgerEntry::query()->firstOrFail()->credit);
    }

    /** @return array<string, array{string, mixed}> */
    public static function finansalAlanlar(): array
    {
        return [
            'firma' => ['company_id', 0],
            'tarih' => ['payment_date', '2020-01-01'],
            'tutar' => ['amount', '999999.00'],
            'para birimi' => ['currency', 'EUR'],
            'ödeme yöntemi' => ['payment_method', 'cash'],
        ];
    }

    // ---------------------------------------------------- katman 2: Service

    /**
     * Servis doğrudan çağrılsa (konsol, ileride import kodu) bile finansal
     * alan değişikliği açık bir istisna ile reddedilir.
     */
    #[Test]
    public function servis_finansal_alan_degisimini_reddeder(): void
    {
        $payment = $this->makePayment();

        $this->expectException(PaymentFinancialFieldsAreImmutable::class);

        $this->payments->updateMetadata($payment, [
            'amount' => '9999.00',
            'reference_no' => 'YENI',
        ], $this->admin);
    }

    #[Test]
    public function servis_reddettiginde_hicbir_alan_degismez(): void
    {
        $payment = $this->makePayment();

        try {
            $this->payments->updateMetadata($payment, [
                'amount' => '9999.00',
                'reference_no' => 'YENI',
            ], $this->admin);
        } catch (PaymentFinancialFieldsAreImmutable) {
            // beklenen
        }

        $fresh = $payment->fresh();

        $this->assertSame('5000.00', $fresh->amount);
        $this->assertSame('ABC123', $fresh->reference_no, 'Metadata da yazılmamalı');
    }

    /** Aynı değerin tekrar gönderilmesi (form gizli alanı) hata üretmez. */
    #[Test]
    public function degismemis_finansal_alan_gonderimi_kabul_edilir(): void
    {
        $payment = $this->makePayment();

        $this->payments->updateMetadata($payment, [
            'company_id' => (string) $this->company->id,
            'payment_date' => '2026-09-08',
            'amount' => '5000,00',
            'currency' => 'TRY',
            'payment_method' => 'bank_transfer',
            'reference_no' => 'YENI-REF',
        ], $this->admin);

        $this->assertSame('YENI-REF', $payment->fresh()->reference_no);
    }

    // ------------------------------------------------------ katman 3: Model

    /**
     * Model seviyesinde de korunur: servis atlanıp doğrudan `update()`
     * çağrılsa bile finansal alan değişmez.
     */
    #[Test]
    #[DataProvider('modelSeviyesiAlanlar')]
    public function model_dogrudan_guncellemeyi_reddeder(string $field, mixed $value): void
    {
        $payment = $this->makePayment();

        $this->expectException(PaymentFinancialFieldsAreImmutable::class);

        $payment->update([$field => $value]);
    }

    /** @return array<string, array{string, mixed}> */
    public static function modelSeviyesiAlanlar(): array
    {
        return [
            'tutar' => ['amount', '1.00'],
            'tarih' => ['payment_date', '2020-01-01'],
            'para birimi' => ['currency', 'EUR'],
            'ödeme yöntemi' => ['payment_method', 'cash'],
        ];
    }

    #[Test]
    public function model_reddettiginde_veritabani_degismez(): void
    {
        $payment = $this->makePayment();

        try {
            $payment->update(['amount' => '1.00']);
        } catch (PaymentFinancialFieldsAreImmutable) {
            // beklenen
        }

        $this->assertSame('5000.00', Payment::query()->findOrFail($payment->id)->amount);
    }

    /** İptal akışı yalnızca durum alanlarını değiştirir; korumadan geçer. */
    #[Test]
    public function iptal_akisi_degismezlik_korumasina_takilmaz(): void
    {
        $payment = $this->makePayment();

        $this->payments->cancel($payment, 'Mükerrer', $this->admin);

        $this->assertTrue($payment->fresh()->isCancelled());
    }

    // ------------------------------------------------------------- metadata

    #[Test]
    public function referans_no_duzeltilebilir(): void
    {
        $payment = $this->makePayment();

        $this->actingAs($this->admin)
            ->put(route('payments.update', $payment), ['reference_no' => 'DUZELTILDI', 'description' => null])
            ->assertRedirect(route('payments.show', $payment));

        $this->assertSame('DUZELTILDI', $payment->fresh()->reference_no);
    }

    #[Test]
    public function aciklama_duzeltilebilir(): void
    {
        $payment = $this->makePayment();

        $this->actingAs($this->admin)
            ->put(route('payments.update', $payment), [
                'reference_no' => 'ABC123',
                'description' => 'Ağustos ayına ait',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Ağustos ayına ait', $payment->fresh()->description);
    }

    #[Test]
    public function iptal_edilmis_tahsilat_duzenlenemez(): void
    {
        $payment = $this->makePayment();
        $this->payments->cancel($payment, 'Mükerrer', $this->admin);

        $this->expectException(CannotUpdateCancelledPayment::class);

        $this->payments->updateMetadata($payment->fresh(), ['reference_no' => 'X'], $this->admin);
    }

    #[Test]
    public function iptal_edilmis_tahsilatin_duzenleme_ekrani_kapalidir(): void
    {
        $payment = $this->makePayment();
        $this->payments->cancel($payment, 'Mükerrer', $this->admin);

        $this->actingAs($this->admin)->get(route('payments.edit', $payment))->assertForbidden();
        $this->actingAs($this->admin)
            ->put(route('payments.update', $payment), ['reference_no' => 'X'])
            ->assertForbidden();
    }

    /** Metadata değişmediyse gereksiz denetim kaydı yazılmaz. */
    #[Test]
    public function ayni_degerle_kaydetmek_denetim_kaydi_uretmez(): void
    {
        $payment = $this->makePayment();
        $before = AuditLog::query()->count();

        $this->payments->updateMetadata($payment, [
            'reference_no' => 'ABC123',
            'description' => null,
        ], $this->admin);

        $this->assertSame($before, AuditLog::query()->count());
    }

    #[Test]
    public function iptal_ledger_hareketini_degistirmez_ters_kayit_ekler(): void
    {
        $payment = $this->makePayment();
        $original = LedgerEntry::query()->firstOrFail();

        $this->payments->cancel($payment, 'Mükerrer', $this->admin);

        $this->assertSame('5000.00', $original->fresh()->credit, 'Orijinal satır asla düzenlenmez');
        $this->assertTrue(app(LedgerService::class)->isReversed($original->fresh()));
    }
}
