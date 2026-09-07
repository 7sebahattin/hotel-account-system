<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Models\User;
use App\Modules\AuditLog\Enums\AuditEvent;
use App\Modules\AuditLog\Models\AuditLog;
use App\Modules\AuditLog\Services\AuditLogger;
use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Companies\Models\Company;
use App\Modules\Ledger\Models\LedgerEntry;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Services\PaymentService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tahsilatın denetim kaydı PaymentService tarafından AÇIKÇA yazılır:
 * ürettiği ya da ters kaydettiği cari hareketlerin kimliklerini taşımak
 * zorundadır (CLAUDE.md §8). Tutarlar maskelenmez.
 */
class PaymentAuditTest extends TestCase
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

    private function logFor(string $event): AuditLog
    {
        return AuditLog::query()
            ->where('auditable_type', 'payment')
            ->where('event', $event)
            ->latest('id')
            ->firstOrFail();
    }

    #[Test]
    public function olusturma_denetim_kaydi_yazilir(): void
    {
        $payment = $this->makePayment();
        $entry = LedgerEntry::query()->firstOrFail();

        $log = $this->logFor('created');

        $this->assertSame($payment->id, $log->auditable_id);
        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertSame($this->admin->name, $log->user_name);
        $this->assertSame('5000.00', $log->new_values['amount'], 'Tutar maskelenmez');
        $this->assertSame('TRY', $log->new_values['currency']);
        $this->assertSame('bank_transfer', $log->new_values['payment_method']);
        $this->assertSame('2026-09-08', $log->new_values['payment_date']);
        $this->assertSame($this->company->id, $log->new_values['company_id']);
        $this->assertSame($entry->id, $log->new_values['ledger_entry_id']);
    }

    #[Test]
    public function metadata_guncellemesi_yalnizca_degisen_alanlari_tasir(): void
    {
        $payment = $this->makePayment();

        $this->payments->updateMetadata($payment, [
            'reference_no' => 'YENI-REF',
            'description' => null,
        ], $this->admin);

        $log = $this->logFor('updated');

        $this->assertSame(['reference_no' => 'ABC123'], $log->old_values);
        $this->assertSame(['reference_no' => 'YENI-REF'], $log->new_values);
        $this->assertArrayNotHasKey('amount', $log->new_values, 'Değişmeyen alan kayda girmez');
    }

    #[Test]
    public function iptal_denetim_kaydi_ters_kayit_zincirini_tasir(): void
    {
        $payment = $this->makePayment();
        $original = LedgerEntry::query()->firstOrFail();

        $this->payments->cancel($payment, 'Yanlış firmaya işlendi', $this->admin);

        $reversal = LedgerEntry::query()->where('is_reversal', true)->firstOrFail();
        $log = $this->logFor('cancelled');

        $this->assertSame($payment->id, $log->auditable_id);
        $this->assertSame(['status' => 'active'], $log->old_values);
        $this->assertSame('cancelled', $log->new_values['status']);
        $this->assertSame('Yanlış firmaya işlendi', $log->new_values['cancellation_reason']);
        $this->assertSame($this->admin->id, $log->new_values['cancelled_by']);
        $this->assertNotNull($log->new_values['cancelled_at']);
        $this->assertSame($original->id, $log->new_values['reversed_ledger_entry_id']);
        $this->assertSame($reversal->id, $log->new_values['reversal_ledger_entry_id']);
        $this->assertSame('5000.00', $log->new_values['amount'], 'İptal edilen tutar görünür kalmalı');
    }

    #[Test]
    public function denetim_ekraninda_tahsilat_kayitlari_gorunur(): void
    {
        $payment = $this->makePayment();
        $this->payments->cancel($payment, 'Mükerrer', $this->admin);

        $this->actingAs($this->admin)
            ->get(route('audit.index'))
            ->assertOk()
            ->assertSee('Oluşturuldu')
            ->assertSee('İptal edildi');
    }

    /** Cari hareketin kendi denetim kaydı da LedgerService tarafından yazılır. */
    #[Test]
    public function cari_hareketin_denetim_kaydi_da_yazilir(): void
    {
        $this->makePayment();

        $this->assertSame(
            1,
            AuditLog::query()->where('auditable_type', 'ledger_entry')->where('event', 'created')->count(),
        );
    }

    #[Test]
    public function denetim_kaydi_ile_ters_kayit_ayni_transaction_icindedir(): void
    {
        $payment = $this->makePayment();
        $auditBefore = AuditLog::query()->count();

        $this->app->bind(AuditLogger::class, fn () => new class extends AuditLogger
        {
            public function log(
                AuditEvent $event,
                ?Model $subject = null,
                ?array $oldValues = null,
                ?array $newValues = null,
                ?User $user = null,
                ?string $subjectLabel = null,
            ): AuditLog {
                throw new \RuntimeException('Denetim kaydı yazılamadı');
            }
        });

        try {
            app(PaymentService::class)->cancel($payment->fresh(), 'Mükerrer', $this->admin);
            $this->fail('İstisna beklenir');
        } catch (\RuntimeException) {
            // beklenen
        }

        $this->assertTrue($payment->fresh()->isActive(), 'İptal geri alınmalı');
        $this->assertSame(1, LedgerEntry::query()->count(), 'Ters kayıt da geri alınmalı');
        $this->assertSame($auditBefore, AuditLog::query()->count());
    }
}
