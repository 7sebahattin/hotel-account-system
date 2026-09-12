<?php

declare(strict_types=1);

namespace Tests\Feature\Stays;

use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Companies\Enums\AccountType;
use App\Modules\Companies\Models\Company;
use App\Modules\Ledger\Services\LedgerService;
use App\Modules\Stays\Models\Stay;
use App\Modules\Stays\Services\StayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IndividualStayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->actingAs($this->userWithRole(RoleName::Admin));
    }

    private function payload(array $extra = []): array
    {
        return array_replace([
            'company_id' => '', 'guest_name' => 'Ayşe Yılmaz',
            'check_in_date' => '2026-09-11', 'check_out_date' => '2026-09-12',
            'pricing_mode' => 'total', 'amount' => '1000', 'currency' => 'TRY',
        ], $extra);
    }

    #[Test]
    public function no_firm_creates_individual_and_single_debt(): void
    {
        $this->post(route('stays.store'), $this->payload())->assertSessionHasNoErrors();
        $stay = Stay::query()->firstOrFail();
        $this->assertSame(AccountType::Individual, $stay->company->account_type);
        $this->assertSame('Ayşe Yılmaz', $stay->company->name);
        $this->assertDatabaseHas('ledger_entries', ['company_id' => $stay->company_id, 'debit' => '1000.00']);
        $this->assertDatabaseCount('ledger_entries', 1);
        $this->get(route('stays.edit', $stay))->assertOk()->assertSee('Bireysel hesap');
        $this->get(route('companies.show', $stay->company_id))->assertOk()->assertSee('Bireysel');
    }

    #[Test]
    public function repeated_guest_is_selected_explicitly_and_edit_keeps_account(): void
    {
        $this->post(route('stays.store'), $this->payload())->assertSessionHasNoErrors();
        $stay = Stay::query()->firstOrFail();
        $data = $this->payload(['individual_account_id' => $stay->company_id]);
        $this->post(route('stays.store'), $data)->assertSessionHasNoErrors();
        $this->put(route('stays.update', $stay), $data + ['notes' => 'Düzeltme'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('companies', 1);
        $this->assertDatabaseCount('ledger_entries', 2);
        $this->assertSame(2, Stay::query()->where('company_id', $stay->company_id)->count());
    }

    #[Test]
    public function identical_names_are_not_merged(): void
    {
        $this->post(route('stays.store'), $this->payload())->assertSessionHasNoErrors();
        $this->post(route('stays.store'), $this->payload())->assertSessionHasNoErrors();
        $this->assertDatabaseCount('companies', 2);
    }

    #[Test]
    public function firm_stay_remains_on_firm_and_conflicting_account_is_rejected(): void
    {
        $firm = Company::factory()->create();
        $person = Company::factory()->create(['account_type' => 'individual']);
        $this->post(route('stays.store'), $this->payload(['company_id' => $firm->id]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('stays', ['company_id' => $firm->id]);
        $this->post(route('stays.store'), $this->payload(['company_id' => $firm->id, 'individual_account_id' => $person->id]))
            ->assertSessionHasErrors('individual_account_id');
        $this->post(route('stays.store'), $this->payload(['individual_account_id' => $firm->id]))
            ->assertSessionHasErrors('individual_account_id');
        $this->assertDatabaseCount('stays', 1);
    }

    #[Test]
    public function inactive_or_deleted_individual_cannot_receive_new_stay(): void
    {
        $person = Company::factory()->create(['account_type' => 'individual', 'is_active' => false]);
        $this->post(route('stays.store'), $this->payload(['individual_account_id' => $person->id]))
            ->assertSessionHasErrors('individual_account_id');
        $person->delete();
        $this->post(route('stays.store'), $this->payload(['individual_account_id' => $person->id]))
            ->assertSessionHasErrors('individual_account_id');
        $this->assertDatabaseCount('stays', 0);
    }

    #[Test]
    public function invalid_stay_does_not_leave_an_orphan_account(): void
    {
        $this->post(route('stays.store'), $this->payload(['amount' => '-1']))->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('companies', 0);
    }

    #[Test]
    public function firm_to_individual_change_reverses_old_account_debt(): void
    {
        $firm = Company::factory()->create();
        $this->post(route('stays.store'), $this->payload(['company_id' => $firm->id]))->assertSessionHasNoErrors();
        $stay = Stay::query()->firstOrFail();
        $this->put(route('stays.update', $stay), $this->payload())->assertSessionHasNoErrors();
        $this->assertNotSame($firm->id, $stay->fresh()->company_id);
        $this->assertSame('0.00', DB::table('ledger_entries')->where('company_id', $firm->id)->selectRaw('SUM(debit-credit) as balance')->first()->balance);
        $this->assertDatabaseCount('companies', 2);
        $this->assertDatabaseCount('ledger_entries', 3);
    }

    #[Test]
    public function accounts_can_be_created_filtered_and_type_cannot_be_changed(): void
    {
        $this->post(route('companies.store'), ['name' => 'Bireysel Kişi', 'account_type' => 'individual', 'is_active' => 1])->assertSessionHasNoErrors();
        $person = Company::query()->firstOrFail();
        Company::factory()->create(['name' => 'Firma Örneği']);
        $this->get(route('companies.index', ['account_type' => 'individual']))->assertOk()->assertSee('Bireysel Kişi')->assertDontSee('Firma Örneği');
        $this->put(route('companies.update', $person), ['name' => 'Bireysel Kişi', 'account_type' => 'company'])->assertSessionHasErrors('account_type');
    }

    #[Test]
    public function personnel_can_record_individual_stay_and_payment_reduces_its_debt(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Personel));
        $this->post(route('stays.store'), $this->payload())->assertSessionHasNoErrors();
        $stay = Stay::query()->firstOrFail();
        $this->post(route('payments.store'), [
            'company_id' => $stay->company_id, 'payment_date' => '2026-09-11',
            'amount' => '400', 'currency' => 'TRY', 'payment_method' => 'bank_transfer',
        ])->assertSessionHasNoErrors();
        $this->assertSame('600.00', DB::table('ledger_entries')->where('company_id', $stay->company_id)->selectRaw('SUM(debit-credit) as balance')->first()->balance);
        $this->assertDatabaseCount('companies', 1);
        $this->assertDatabaseCount('payments', 1);
    }

    #[Test]
    public function ledger_failure_rolls_back_new_account_and_stay(): void
    {
        $this->mock(LedgerService::class, function ($mock): void {
            $mock->shouldReceive('debit')->once()->andThrow(new \RuntimeException('Test ledger failure'));
        });

        try {
            app(StayService::class)->create($this->payload());
            $this->fail('Ledger failure must propagate.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Test ledger failure', $exception->getMessage());
        }

        $this->assertDatabaseCount('companies', 0);
        $this->assertDatabaseCount('stays', 0);
        $this->assertDatabaseCount('ledger_entries', 0);
    }
}
