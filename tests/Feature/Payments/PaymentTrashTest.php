<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Models\User;
use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Companies\Models\Company;
use App\Modules\Ledger\Models\LedgerEntry;
use App\Modules\Payments\Exceptions\PaymentHasLinkedAllocations;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Services\PaymentService;
use App\Modules\Stays\Services\StayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tahsilat çöp kutusu (4 Kasım 2026): iptal edilmiş tahsilatı herkes çöpe
 * taşır, kalıcı silme yalnızca Admin'dedir; ledger'a hiç dokunulmaz.
 */
class PaymentTrashTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Company $company;

    private PaymentService $payments;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->admin = $this->userWithRole(RoleName::Admin);
        $this->company = Company::factory()->create();
        $this->payments = app(PaymentService::class);
    }

    private function cancelledPayment(): Payment
    {
        $payment = $this->payments->create([
            'company_id' => $this->company->id,
            'payment_date' => '2026-09-08',
            'amount' => '5000.00',
            'currency' => 'TRY',
            'payment_method' => 'bank_transfer',
        ], $this->admin);

        return $this->payments->cancel($payment, 'Yanlış kayıt', $this->admin);
    }

    #[Test]
    public function personel_dahil_herkes_iptal_edilmis_tahsilati_cope_tasiyabilir(): void
    {
        foreach ([RoleName::Admin, RoleName::Patron, RoleName::Personel] as $role) {
            $payment = $this->cancelledPayment();
            $user = $this->userWithRole($role);
            $ledgerCount = LedgerEntry::count();

            $this->actingAs($user)->patch(route('payments.move-to-trash', $payment))
                ->assertRedirect(route('payments.index'));

            $this->assertTrue($payment->fresh()->isTrashed());
            $this->assertSame($user->id, $payment->fresh()->trashed_by);
            $this->assertSame($ledgerCount, LedgerEntry::count(), 'Ledger değişmemeli.');
        }
    }

    #[Test]
    public function aktif_tahsilat_cope_tasinamaz(): void
    {
        $payment = $this->payments->create([
            'company_id' => $this->company->id,
            'payment_date' => '2026-09-08',
            'amount' => '5000.00',
            'currency' => 'TRY',
        ], $this->admin);

        $this->actingAs($this->userWithRole(RoleName::Patron))
            ->patch(route('payments.move-to-trash', $payment))->assertForbidden();
        $this->assertFalse($payment->fresh()->isTrashed());
    }

    #[Test]
    public function coptaki_tahsilat_listeden_kalkar_ve_cop_kutusunda_gorunur(): void
    {
        $payment = $this->cancelledPayment();
        $this->payments->trash($payment, $this->admin);

        $index = $this->actingAs($this->admin)->get(route('payments.index'));
        $this->assertCount(0, $index->viewData('payments')->items());

        $trash = $this->actingAs($this->userWithRole(RoleName::Personel))->get(route('payments.trash'));
        $trash->assertOk()->assertDontSee('Çöp kutusu boş.', false);
        $this->assertCount(1, $trash->viewData('payments')->items());
    }

    #[Test]
    public function herkes_geri_alabilir(): void
    {
        $payment = $this->cancelledPayment();
        $this->payments->trash($payment, $this->admin);

        $this->actingAs($this->userWithRole(RoleName::Personel))
            ->patch(route('payments.untrash', $payment))->assertRedirect(route('payments.trash'));

        $this->assertFalse($payment->fresh()->isTrashed());
    }

    #[Test]
    public function kalici_silme_yalnizca_adminde_ve_ledger_korunur(): void
    {
        $payment = $this->cancelledPayment();
        $this->payments->trash($payment, $this->admin);
        $ledgerCount = LedgerEntry::count();

        foreach ([RoleName::Patron, RoleName::Personel] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->delete(route('payments.force-delete', $payment))->assertForbidden();
        }
        $this->assertDatabaseHas('payments', ['id' => $payment->id]);

        $this->actingAs($this->admin)->delete(route('payments.force-delete', $payment))
            ->assertRedirect(route('payments.trash'));

        $this->assertDatabaseMissing('payments', ['id' => $payment->id]);
        $this->assertSame($ledgerCount, LedgerEntry::count());
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => 'payment', 'auditable_id' => $payment->id, 'event' => 'deleted']);
    }

    #[Test]
    public function cope_atilmamis_tahsilat_kalici_silinemez(): void
    {
        $payment = $this->cancelledPayment();

        $this->actingAs($this->admin)->delete(route('payments.force-delete', $payment))->assertForbidden();
        $this->assertDatabaseHas('payments', ['id' => $payment->id]);
    }

    #[Test]
    public function dagilimi_olan_tahsilat_kalici_silinemez_ve_kayit_coepte_kalir(): void
    {
        app(StayService::class)->create([
            'company_id' => $this->company->id,
            'guest_name' => 'John',
            'check_in_date' => '2026-09-07',
            'check_out_date' => '2026-09-10',
            'pricing_mode' => 'total',
            'amount' => '9000.00',
            'currency' => 'TRY',
        ], $this->admin);
        $payment = $this->payments->create([
            'company_id' => $this->company->id,
            'payment_date' => '2026-09-08',
            'amount' => '9000.00',
            'currency' => 'TRY',
        ], $this->admin);
        $this->payments->cancel($payment->fresh(), 'Yanlış', $this->admin);
        $this->payments->trash($payment->fresh(), $this->admin);

        $this->assertThrows(
            fn () => $this->payments->forceDelete($payment->fresh(), $this->admin),
            PaymentHasLinkedAllocations::class,
        );
        $this->assertTrue($payment->fresh()->isTrashed());
    }
}
