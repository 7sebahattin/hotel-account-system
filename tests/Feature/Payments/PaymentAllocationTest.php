<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Models\User;
use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Companies\Models\Company;
use App\Modules\Core\Enums\Currency;
use App\Modules\Ledger\Services\LedgerService;
use App\Modules\Payments\Exceptions\PaymentAlreadyCancelled;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Services\PaymentAllocationService;
use App\Modules\Payments\Services\PaymentService;
use App\Modules\Stays\Models\Stay;
use App\Modules\Stays\Services\StayService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentAllocationTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $actor;

    private int $staySequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->company = Company::factory()->create();
        $this->actor = $this->userWithRole(RoleName::Admin);
        $this->actingAs($this->actor);
    }

    private function stay(array $data = []): Stay
    {
        $guestName = 'Misafir '.(++$this->staySequence);

        return app(StayService::class)->create(array_replace([
            'company_id' => $this->company->id, 'guest_name' => $guestName,
            'check_in_date' => '2026-09-01', 'check_out_date' => '2026-09-02',
            'pricing_mode' => 'total', 'amount' => '1000.00', 'currency' => 'TRY',
        ], $data), $this->actor);
    }

    private function payment(array $data = []): Payment
    {
        return app(PaymentService::class)->create(array_replace([
            'company_id' => $this->company->id, 'payment_date' => '2026-09-11',
            'amount' => '1000.00', 'currency' => 'TRY', 'payment_method' => 'bank_transfer',
        ], $data), $this->actor);
    }

    private function paid(Stay $stay): string
    {
        return app(PaymentAllocationService::class)->summaries([$stay->id])[$stay->id]['paid']->toDecimal();
    }

    #[Test]
    public function oldest_first_closes_two_stays_and_partially_pays_third(): void
    {
        $stays = [];
        for ($i = 0; $i < 5; $i++) {
            $stays[] = $this->stay();
        }
        $payment = $this->payment(['amount' => '2500.00']);
        $this->assertSame(['1000.00', '1000.00', '500.00', '0.00', '0.00'], array_map(fn ($stay) => $this->paid($stay), $stays));
        $this->assertSame('Kısmen ödendi', app(PaymentAllocationService::class)->summaries([$stays[2]->id])[$stays[2]->id]['label']);
        $this->assertSame('0.00', app(PaymentAllocationService::class)->available($payment)->toDecimal());
        $this->assertDatabaseCount('ledger_entries', 6);
    }

    #[Test]
    public function older_check_in_wins_over_creation_order(): void
    {
        $later = $this->stay(['check_in_date' => '2026-09-10', 'check_out_date' => '2026-09-11']);
        $earlier = $this->stay();
        $this->payment();
        $this->assertSame('1000.00', $this->paid($earlier));
        $this->assertSame('0.00', $this->paid($later));
    }

    #[Test]
    public function selected_personal_payment_leaves_first_company_stay_unpaid(): void
    {
        $first = $this->stay();
        $second = $this->stay();
        $third = $this->stay();
        $payment = $this->payment(['amount' => '1500.00', 'allocation_mode' => 'selected',
            'stay_ids' => [$third->id, $second->id], 'payer_type' => 'individual', 'payer_name' => 'Ayşe']);
        $this->assertSame(['0.00', '1000.00', '500.00'], [$this->paid($first), $this->paid($second), $this->paid($third)]);
        $this->assertSame('Ayşe', $payment->payer_name);
        $this->assertSame($this->company->id, $payment->company_id);
        $this->assertDatabaseCount('companies', 1);
        $this->get(route('payments.show', $payment))->assertOk()->assertSee('Ayşe')->assertSee('Şahsi ödeme');
        $this->get(route('payments.receipt', $payment))->assertOk()->assertSee('Ayşe');
    }

    #[Test]
    public function overpayment_stays_as_advance_and_is_used_on_new_stay(): void
    {
        $this->stay();
        $payment = $this->payment(['amount' => '1500.00']);
        $this->assertSame('500.00', app(PaymentAllocationService::class)->available($payment)->toDecimal());
        $next = $this->stay();
        $this->assertSame('500.00', $this->paid($next));
        $this->assertSame('0.00', app(PaymentAllocationService::class)->available($payment)->toDecimal());
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('ledger_entries', 3);
    }

    #[Test]
    public function payment_cancellation_reopens_stays_without_deleting_history(): void
    {
        $stay = $this->stay();
        $payment = $this->payment();
        app(PaymentService::class)->cancel($payment, 'Hatalı ödeme', $this->actor);
        $this->assertSame('0.00', $this->paid($stay));
        $this->assertDatabaseCount('payment_allocations', 2);
        $this->assertSame('1000.00', app(LedgerService::class)->balanceForCompany($this->company->id, Currency::TRY)->toDecimal());
    }

    #[Test]
    public function stay_cancellation_preserves_receipt_and_releases_advance(): void
    {
        $stay = $this->stay();
        $payment = $this->payment();
        app(StayService::class)->cancel($stay, $this->actor);
        $this->assertSame('1000.00', app(PaymentAllocationService::class)->available($payment)->toDecimal());
        $this->assertFalse($payment->fresh()->isCancelled());
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('payment_allocations', 2);
    }

    #[Test]
    public function decreasing_stay_amount_releases_only_excess(): void
    {
        $stay = $this->stay();
        $payment = $this->payment();
        app(StayService::class)->update($stay, ['amount' => '600.00'], $this->actor);
        $this->assertSame('600.00', $this->paid($stay));
        $this->assertSame('400.00', app(PaymentAllocationService::class)->available($payment)->toDecimal());
        $this->assertDatabaseCount('payment_allocations', 3);
    }

    #[Test]
    public function moving_stay_to_another_account_keeps_payment_with_original_owner(): void
    {
        $stay = $this->stay();
        $payment = $this->payment();
        $other = Company::factory()->create();
        app(StayService::class)->update($stay, ['company_id' => $other->id], $this->actor);
        $this->assertSame('0.00', $this->paid($stay));
        $this->assertSame('1000.00', app(PaymentAllocationService::class)->available($payment)->toDecimal());
        $this->assertSame($this->company->id, $payment->fresh()->company_id);
    }

    #[Test]
    public function wrong_account_or_currency_cannot_receive_selected_payment(): void
    {
        $foreign = $this->stay(['currency' => 'EUR', 'exchange_rate_to_try' => '40']);
        $other = $this->stay(['company_id' => Company::factory()->create()->id]);
        foreach ([$foreign, $other] as $stay) {
            try {
                $this->payment(['allocation_mode' => 'selected', 'stay_ids' => [$stay->id]]);
                $this->fail('Invalid selection must fail.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('stay_ids', $e->errors());
            }
        }
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('ledger_entries', 2);
    }

    #[Test]
    public function cancelled_stay_and_already_paid_selection_are_rejected(): void
    {
        $stay = $this->stay();
        app(StayService::class)->cancel($stay, $this->actor);
        $this->post(route('payments.store'), ['company_id' => $this->company->id, 'payment_date' => '2026-09-11',
            'amount' => '100', 'currency' => 'TRY', 'allocation_mode' => 'selected', 'stay_ids' => [$stay->id]])->assertSessionHasErrors('stay_ids');
        $this->assertDatabaseCount('payments', 0);
    }

    #[Test]
    public function past_receipts_are_not_silently_allocated(): void
    {
        Payment::factory()->create(['company_id' => $this->company->id, 'currency' => 'TRY', 'amount' => '1000']);
        $stay = $this->stay();
        $summary = app(PaymentAllocationService::class)->summaries([$stay->id])[$stay->id];
        $this->assertTrue($summary['legacy']);
        $this->assertSame('Geçmiş eşleştirme bekliyor', $summary['label']);
        $this->assertSame('0.00', $this->paid($stay));
    }

    #[Test]
    public function raw_update_cannot_change_allocation_history(): void
    {
        $this->stay();
        $this->payment();
        $this->expectException(QueryException::class);
        DB::table('payment_allocations')->update(['amount' => '1']);
    }

    #[Test]
    public function person_name_is_required_and_options_are_permission_protected(): void
    {
        $this->post(route('payments.store'), ['company_id' => $this->company->id, 'payment_date' => '2026-09-11',
            'amount' => '100', 'currency' => 'TRY', 'payer_type' => 'individual'])->assertSessionHasErrors('payer_name');
        $this->actingAs(User::factory()->create())->getJson(route('payments.allocation-options', [
            'company_id' => $this->company->id, 'currency' => 'TRY',
        ]))->assertForbidden();
    }

    #[Test]
    public function personnel_can_select_stays_but_cannot_cancel_receipt(): void
    {
        $stay = $this->stay();
        $this->actingAs($this->userWithRole(RoleName::Personel));
        $this->getJson(route('payments.allocation-options', ['company_id' => $this->company->id, 'currency' => 'TRY']))->assertOk()->assertJsonPath('stays.0.id', $stay->id);
        $this->post(route('payments.store'), ['company_id' => $this->company->id, 'payment_date' => '2026-09-11',
            'amount' => '400', 'currency' => 'TRY', 'allocation_mode' => 'selected', 'stay_ids' => [$stay->id],
            'payer_type' => 'individual', 'payer_name' => 'Misafir'])->assertSessionHasNoErrors();
        $payment = Payment::query()->firstOrFail();
        $this->assertSame('400.00', $this->paid($stay));
        $this->patch(route('payments.cancel', $payment), ['cancellation_reason' => 'Deneme'])->assertForbidden();
    }

    #[Test]
    public function invalid_cash_allocation_rolls_back_receipt_ledger_and_cash(): void
    {
        $stay = $this->stay(['company_id' => Company::factory()->create()->id]);
        try {
            $this->payment(['payment_method' => 'cash', 'allocation_mode' => 'selected', 'stay_ids' => [$stay->id]]);
            $this->fail('Wrong account must fail.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('stay_ids', $e->errors());
        }
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseMissing('cash_movements', ['reference_type' => 'payment']);
        $this->assertDatabaseCount('ledger_entries', 1);
    }

    #[Test]
    public function cash_allocation_does_not_create_cash_movement(): void
    {
        $this->stay();
        $this->stay();
        $payment = $this->payment(['amount' => '1500', 'payment_method' => 'cash']);
        $this->assertSame(0, DB::table('cash_movements')->where('reference_type', 'payment')->where('reference_id', $payment->id)->count());
        $this->assertDatabaseCount('payment_allocations', 2);
        $this->assertDatabaseCount('ledger_entries', 3);
    }

    #[Test]
    public function stale_payment_cancellation_cannot_reverse_twice(): void
    {
        $stay = $this->stay();
        $payment = $this->payment();
        $stale = $payment->fresh();
        app(PaymentService::class)->cancel($payment, 'Hatalı', $this->actor);
        try {
            app(PaymentService::class)->cancel($stale, 'Tekrar', $this->actor);
            $this->fail('Second cancellation must fail.');
        } catch (PaymentAlreadyCancelled) {
            $this->assertSame('0.00', $this->paid($stay));
            $this->assertDatabaseCount('payment_allocations', 2);
            $this->assertDatabaseCount('ledger_entries', 3);
        }
    }

    #[Test]
    public function raw_delete_cannot_remove_allocation_history(): void
    {
        $this->stay();
        $this->payment();
        $this->expectException(QueryException::class);
        DB::table('payment_allocations')->delete();
    }
}
