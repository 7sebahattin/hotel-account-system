<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Models\User;
use App\Modules\AuditLog\Services\AuditLogger;
use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Companies\Models\Company;
use App\Modules\Ledger\Models\LedgerEntry;
use App\Modules\Ledger\Services\LedgerService;
use App\Modules\Payments\Enums\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentCancellationTest extends TestCase
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
        ], $this->admin);
    }

    #[Test]
    public function iptal_durum_ve_iptal_alanlarini_doldurur(): void
    {
        $payment = $this->makePayment();

        $this->actingAs($this->admin)
            ->patch(route('payments.cancel', $payment), ['cancellation_reason' => 'Yanlış firmaya işlendi'])
            ->assertRedirect(route('payments.show', $payment));

        $fresh = $payment->fresh();

        $this->assertSame(PaymentStatus::Cancelled, $fresh->status);
        $this->assertSame($this->admin->id, $fresh->cancelled_by);
        $this->assertNotNull($fresh->cancelled_at);
        $this->assertSame('Yanlış firmaya işlendi', $fresh->cancellation_reason);
    }

    #[Test]
    public function iptal_gerekcesi_zorunludur(): void
    {
        $payment = $this->makePayment();

        $this->actingAs($this->admin)
            ->patch(route('payments.cancel', $payment), ['cancellation_reason' => ''])
            ->assertSessionHasErrors('cancellation_reason');

        $this->assertTrue($payment->fresh()->isActive());
        $this->assertSame(1, LedgerEntry::query()->count(), 'Gerekçesiz iptal cariye dokunmamalı');
    }

    #[Test]
    public function iptal_kaydi_silmez_listede_kalir(): void
    {
        $payment = $this->makePayment();
        $this->payments->cancel($payment, 'Mükerrer', $this->admin);

        $this->assertSame(1, Payment::query()->count());

        $this->actingAs($this->admin)
            ->get(route('payments.index'))
            ->assertOk()
            ->assertSee('İptal');
    }

    #[Test]
    public function ikinci_iptal_http_uzerinden_de_engellenir(): void
    {
        $payment = $this->makePayment();
        $this->payments->cancel($payment, 'İlk', $this->admin);

        // Policy::cancel iptal edilmiş kayıtta false döner.
        $this->actingAs($this->admin)
            ->patch(route('payments.cancel', $payment), ['cancellation_reason' => 'İkinci'])
            ->assertForbidden();

        $this->assertSame('İlk', $payment->fresh()->cancellation_reason);
        $this->assertSame(2, LedgerEntry::query()->count());
    }

    #[Test]
    public function iptal_geri_alinamaz(): void
    {
        $payment = $this->makePayment();
        $this->payments->cancel($payment, 'Mükerrer', $this->admin);

        $this->assertFalse(Route::has('payments.restore'));
        $this->assertFalse(method_exists(PaymentService::class, 'restore'));
        $this->assertFalse(method_exists(PaymentService::class, 'reactivate'));

        // Model seviyesinde durum geri alınsa bile bunu yapan bir akış yoktur;
        // arayüzde iptal edilmiş kaydın hiçbir aksiyonu görünmez.
        $this->actingAs($this->admin)
            ->get(route('payments.show', $payment))
            ->assertOk()
            ->assertDontSee('Bilgileri düzenle')
            ->assertDontSee('Tahsilatı iptal et');
    }

    #[Test]
    public function iptal_islemi_tek_transaction_icindedir(): void
    {
        $payment = $this->makePayment();

        // Ters kayıt yazılamazsa tahsilat da iptal edilmiş görünmemeli.
        $this->app->bind(LedgerService::class, fn () => new class(app(AuditLogger::class)) extends LedgerService
        {
            public function reverse(LedgerEntry $entry, ?string $description = null, ?User $actor = null): LedgerEntry
            {
                throw new \RuntimeException('Ters kayıt yazılamadı');
            }
        });

        try {
            app(PaymentService::class)->cancel($payment->fresh(), 'Mükerrer', $this->admin);
            $this->fail('İstisna beklenir');
        } catch (\RuntimeException) {
            // beklenen
        }

        $this->assertTrue($payment->fresh()->isActive(), 'İptal geri alınmalı');
        $this->assertNull($payment->fresh()->cancelled_at);
        $this->assertSame(1, LedgerEntry::query()->count());
    }
}
