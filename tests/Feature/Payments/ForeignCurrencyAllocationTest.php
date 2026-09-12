<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Models\User;
use App\Modules\Authorization\Enums\RoleName;
use App\Modules\CashBook\Services\CashBookService;
use App\Modules\Companies\Models\Company;
use App\Modules\Core\Enums\Currency;
use App\Modules\Ledger\Services\LedgerService;
use App\Modules\Payments\Exceptions\PaymentFinancialFieldsAreImmutable;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Services\PaymentAllocationService;
use App\Modules\Payments\Services\PaymentService;
use App\Modules\Stays\Models\Stay;
use App\Modules\Stays\Services\StayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ForeignCurrencyAllocationTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $actor;

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
        return app(StayService::class)->create(array_replace([
            'company_id' => $this->company->id, 'guest_name' => 'Döviz Misafiri',
            'check_in_date' => '2026-09-01', 'check_out_date' => '2026-09-02',
            'pricing_mode' => 'total', 'amount' => '5000', 'currency' => 'TRY',
        ], $data), $this->actor);
    }

    private function payment(array $data = []): Payment
    {
        return app(PaymentService::class)->create(array_replace([
            'company_id' => $this->company->id, 'payment_date' => '2026-09-12',
            'amount' => '60', 'currency' => 'EUR', 'exchange_rate_to_try' => '50',
            'settlement_currency' => 'TRY', 'payment_method' => 'bank_transfer',
        ], $data), $this->actor);
    }

    private function balance(Currency $currency): string
    {
        return app(LedgerService::class)->balanceForCompany($this->company->id, $currency)->toDecimal();
    }

    private function paid(Stay $stay): string
    {
        return app(PaymentAllocationService::class)->summaries([$stay->id])[$stay->id]['paid']->toDecimal();
    }

    #[Test]
    public function euro_receipt_closes_tl_debt_without_duplicate_cash_or_collection(): void
    {
        app(CashBookService::class)->openSession($this->actor);
        $stay = $this->stay();
        $payment = $this->payment(['payment_method' => 'cash']);
        $this->assertSame('3000.00', $this->paid($stay));
        $this->assertSame('2000.00', $this->balance(Currency::TRY));
        $this->assertSame('0.00', $this->balance(Currency::EUR));
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame(1, DB::table('cash_movements')->where('reference_type', 'payment')->count());
        $this->assertDatabaseHas('cash_movements', ['reference_id' => $payment->id, 'currency' => 'EUR', 'amount' => '60.00']);
        $totals = app(PaymentService::class)->totalsByCurrencyBetween('2026-09-01', '2026-09-30');
        $this->assertSame(['EUR'], array_keys($totals));
        $this->assertSame('60.00', $totals['EUR']->toDecimal());
        $this->get(route('payments.show', $payment))->assertOk()->assertSee('3.000,00 ₺')->assertSee('60,00 €');
    }

    #[Test]
    public function excess_remains_euro_and_later_tl_stay_consumes_it(): void
    {
        $this->stay();
        $payment = $this->payment(['amount' => '120']);
        $this->assertSame('0.00', $this->balance(Currency::TRY));
        $this->assertSame('-20.00', $this->balance(Currency::EUR));
        $this->assertSame('20.00', app(PaymentAllocationService::class)->available($payment)->toDecimal());
        $next = $this->stay(['amount' => '1500']);
        $this->assertSame('1000.00', $this->paid($next));
        $this->assertSame('500.00', $this->balance(Currency::TRY));
        $this->assertSame('0.00', $this->balance(Currency::EUR));
    }

    #[Test]
    public function selected_personal_payment_only_closes_chosen_tl_stay(): void
    {
        $first = $this->stay();
        $second = $this->stay();
        $this->payment(['allocation_mode' => 'selected', 'stay_ids' => [$second->id], 'payer_type' => 'individual', 'payer_name' => 'Ayşe']);
        $this->assertSame('0.00', $this->paid($first));
        $this->assertSame('3000.00', $this->paid($second));
    }

    #[Test]
    public function cancellation_reopens_tl_debt_and_removes_euro_credit(): void
    {
        $stay = $this->stay();
        $payment = $this->payment(['amount' => '120']);
        app(PaymentService::class)->cancel($payment, 'Yanlış ödeme', $this->actor);
        $this->assertSame('5000.00', $this->balance(Currency::TRY));
        $this->assertSame('0.00', $this->balance(Currency::EUR));
        $this->assertSame('0.00', $this->paid($stay));
        $this->assertDatabaseCount('ledger_entries', 7);
    }

    #[Test]
    public function stay_cancel_returns_original_euro_advance_and_then_payment_can_cancel(): void
    {
        $stay = $this->stay();
        $payment = $this->payment();
        app(StayService::class)->cancel($stay, $this->actor);
        $this->assertSame('0.00', $this->balance(Currency::TRY));
        $this->assertSame('-60.00', $this->balance(Currency::EUR));
        $this->assertSame('60.00', app(PaymentAllocationService::class)->available($payment)->toDecimal());
        app(PaymentService::class)->cancel($payment, 'İade yerine hatalı kayıt iptali', $this->actor);
        $this->assertSame('0.00', $this->balance(Currency::EUR));
    }

    #[Test]
    public function reducing_stay_releases_only_excess_euro(): void
    {
        $stay = $this->stay();
        $payment = $this->payment();
        app(StayService::class)->update($stay, ['amount' => '2000'], $this->actor);
        $this->assertSame('2000.00', $this->paid($stay));
        $this->assertSame('0.00', $this->balance(Currency::TRY));
        $this->assertSame('-20.00', $this->balance(Currency::EUR));
        $this->assertSame('20.00', app(PaymentAllocationService::class)->available($payment)->toDecimal());
    }

    #[Test]
    public function moving_stay_preserves_receipt_in_original_account(): void
    {
        $stay = $this->stay();
        $this->payment();
        $other = Company::factory()->create();
        app(StayService::class)->update($stay, ['company_id' => $other->id], $this->actor);
        $this->assertSame('0.00', $this->balance(Currency::TRY));
        $this->assertSame('-60.00', $this->balance(Currency::EUR));
        $this->assertSame('0.00', $this->paid($stay));
    }

    #[Test]
    public function no_target_keeps_existing_same_currency_behavior(): void
    {
        $tl = $this->stay();
        $eur = $this->stay(['amount' => '100', 'currency' => 'EUR', 'exchange_rate_to_try' => '50']);
        $this->payment(['settlement_currency' => null]);
        $this->assertSame('0.00', $this->paid($tl));
        $this->assertSame('60.00', $this->paid($eur));
        $this->assertDatabaseMissing('ledger_entries', ['source_type' => 'payment_allocation']);
    }

    #[Test]
    public function wrong_currency_selection_rolls_back_all_financial_writes(): void
    {
        $eur = $this->stay(['currency' => 'EUR', 'exchange_rate_to_try' => '50']);
        try {
            $this->payment(['allocation_mode' => 'selected', 'stay_ids' => [$eur->id]]);
            $this->fail('Wrong debt currency must fail.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('stay_ids', $e->errors());
        }
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('payment_allocations', 0);
        $this->assertDatabaseCount('ledger_entries', 1);
    }

    #[Test]
    public function tl_receipt_cannot_request_conversion_and_foreign_target_is_rejected(): void
    {
        $data = ['company_id' => $this->company->id, 'payment_date' => '2026-09-12', 'amount' => '60', 'currency' => 'TRY', 'settlement_currency' => 'TRY'];
        $this->post(route('payments.store'), $data)->assertSessionHasErrors('settlement_currency');
        $this->post(route('payments.store'), array_replace($data, ['currency' => 'EUR', 'exchange_rate_to_try' => '50', 'settlement_currency' => 'USD']))->assertSessionHasErrors('settlement_currency');
        $this->assertDatabaseCount('payments', 0);
    }

    #[Test]
    public function missing_rate_is_rejected_even_for_direct_service_call(): void
    {
        $this->expectException(ValidationException::class);
        $this->payment(['exchange_rate_to_try' => null]);
    }

    #[Test]
    public function conversion_target_is_immutable(): void
    {
        $payment = $this->payment();
        $this->expectException(PaymentFinancialFieldsAreImmutable::class);
        $payment->settlement_currency = null;
        $payment->save();
    }

    #[Test]
    public function fraction_of_a_foreign_cent_is_not_silently_written_off(): void
    {
        $stay = $this->stay(['amount' => '1000']);
        $payment = $this->payment(['amount' => '30', 'exchange_rate_to_try' => '45']);
        $this->assertSame('999.90', $this->paid($stay));
        $this->assertSame('0.10', $this->balance(Currency::TRY));
        $this->assertSame('7.78', app(PaymentAllocationService::class)->available($payment)->toDecimal());
    }

    #[Test]
    public function split_rounding_preserves_total_conversion_value(): void
    {
        $first = $this->stay(['amount' => '0.01']);
        $second = $this->stay(['amount' => '0.49']);
        $payment = $this->payment(['amount' => '100', 'exchange_rate_to_try' => '0.005']);
        $this->assertSame('0.01', $this->paid($first));
        $this->assertSame('0.49', $this->paid($second));
        $this->assertSame('0.00', $this->balance(Currency::TRY));
        $this->assertSame('0.00', app(PaymentAllocationService::class)->available($payment)->toDecimal());
    }

    #[Test]
    public function summaries_and_statement_show_only_unconverted_foreign_equivalent(): void
    {
        $this->stay();
        $this->payment(['amount' => '120']);
        $service = app(PaymentService::class);
        $this->assertSame('1000.00', $service->exchangeRateCreditTotalsForCompanies([$this->company->id])[$this->company->id]['EUR']['total']->toDecimal());
        $this->assertSame('1000.00', $service->systemWideExchangeRateCreditTotalsByCurrency()['EUR']['total']->toDecimal());
        $response = $this->get('/companies/'.$this->company->id.'/statement')->assertOk()->assertSee('Dövizle Borç Kapama')->assertSee('Döviz tahsilatı');
        $fx = $response->viewData('fx');
        $this->assertTrue($fx['closing']['EUR']['complete']);
        $this->assertSame('-1000.00', $fx['closing']['EUR']['value']->toDecimal());
    }

    #[Test]
    public function personnel_can_submit_selected_foreign_payment_with_turkish_rate(): void
    {
        $stay = $this->stay();
        $this->actingAs($this->userWithRole(RoleName::Personel));
        $this->post(route('payments.store'), ['company_id' => $this->company->id, 'payment_date' => '2026-09-12',
            'amount' => '60,00', 'currency' => 'EUR', 'exchange_rate_to_try' => '50,00', 'settlement_currency' => 'TRY',
            'allocation_mode' => 'selected', 'stay_ids' => [$stay->id]])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('3000.00', $this->paid($stay));
    }
}
