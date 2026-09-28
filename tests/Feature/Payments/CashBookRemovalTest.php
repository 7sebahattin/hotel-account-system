<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Models\User;
use App\Modules\Authorization\Database\Seeders\RolePermissionSeeder;
use App\Modules\Authorization\Enums\Permission as PermissionEnum;
use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Authorization\Models\Role;
use App\Modules\Companies\Models\Company;
use App\Modules\Core\Enums\Currency;
use App\Modules\Ledger\Services\LedgerService;
use App\Modules\Payments\Exceptions\PaymentAlreadyCancelled;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Services\PaymentAllocationService;
use App\Modules\Payments\Services\PaymentService;
use App\Modules\Stays\Services\StayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CashBookRemovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public static function channels(): array
    {
        return array_map(fn ($method) => [$method], ['cash', 'bank_transfer', 'credit_card', 'cheque', 'other', null]);
    }

    private function payload(Company $company, ?string $method = 'cash'): array
    {
        return [
            'company_id' => $company->id, 'payment_date' => '2026-09-13',
            'amount' => '750.00', 'currency' => 'TRY', 'payment_method' => $method,
        ];
    }

    /** Eski sürümün oluşturduğu kayıt, yeni sürümün servisinden geçirilmeden hazırlanır. */
    private function legacySession(User $owner, bool $open): int
    {
        return DB::table('cash_sessions')->insertGetId([
            'opened_by' => $owner->id, 'opened_at' => '2026-09-01 10:00:00',
            'closed_by' => $open ? null : $owner->id,
            'closed_at' => $open ? null : '2026-09-02 10:00:00',
            'status' => $open ? 'open' : 'closed', 'is_open' => $open ? 1 : null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function archive(): array
    {
        $result = [];
        foreach (['cash_sessions', 'cash_movements', 'cash_transfers', 'cash_transfer_amounts', 'cash_counts', 'cash_movement_categories'] as $table) {
            $result[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }

        return $result;
    }

    #[Test]
    #[DataProvider('channels')]
    public function all_channels_work_without_a_session_and_reverse_only_the_ledger(?string $method): void
    {
        $actor = $this->userWithRole(RoleName::Admin);
        $company = Company::factory()->create();
        $before = $this->archive();
        $payment = app(PaymentService::class)->create($this->payload($company, $method), $actor);
        $this->assertNull($payment->cash_session_id);
        $this->assertNotNull($payment->receipt_no);
        $this->assertSame('-750.00', app(LedgerService::class)->balanceForCompany($company->id, Currency::TRY)->toDecimal());
        app(PaymentService::class)->cancel($payment, 'Düzeltme', $actor);
        $this->assertSame('0.00', app(LedgerService::class)->balanceForCompany($company->id, Currency::TRY)->toDecimal());
        $this->assertDatabaseCount('ledger_entries', 2);
        $this->assertSame($before, $this->archive());
    }

    #[Test]
    public function personnel_can_post_cash_while_another_users_legacy_session_is_open(): void
    {
        $owner = $this->userWithRole(RoleName::Admin);
        $session = $this->legacySession($owner, true);
        $actor = $this->userWithRole(RoleName::Personel);
        $company = Company::factory()->create();
        $before = $this->archive();
        $this->actingAs($actor)->post(route('payments.store'), $this->payload($company) + ['cash_session_id' => $session])
            ->assertSessionHasNoErrors()->assertRedirect();
        $payment = Payment::query()->sole();
        $this->assertNull($payment->cash_session_id);
        $this->assertSame($before, $this->archive());
        $this->get(route('payments.show', $payment))->assertOk()->assertDontSee('Kasa vardiyası');
        $this->get(route('payments.receipt', $payment))->assertOk();
        $this->patch(route('payments.cancel', $payment), ['cancellation_reason' => 'Yetkisiz'])->assertForbidden();
    }

    #[Test]
    public function legacy_cash_payment_cancels_without_changing_its_closed_session_or_archive(): void
    {
        $actor = $this->userWithRole(RoleName::Admin);
        $company = Company::factory()->create();
        $session = $this->legacySession($actor, true);
        $payment = Payment::factory()->create([
            'company_id' => $company->id, 'amount' => '750.00', 'currency' => 'TRY',
            'payment_method' => 'cash', 'created_by' => $actor->id, 'updated_by' => $actor->id,
        ]);
        DB::table('payments')->where('id', $payment->id)->update(['cash_session_id' => $session]);
        $payment->refresh();
        app(LedgerService::class)->credit($company->id, $payment->amountMoney(), Currency::TRY,
            $payment->payment_date, 'Eski tahsilat', 'payment', $payment->id, $actor);
        DB::table('cash_movements')->insert([
            'cash_session_id' => $session, 'type' => 'company_collection', 'direction' => 'in',
            'amount' => '750.00', 'currency' => 'TRY', 'entry_at' => '2026-09-01 12:00:00',
            'reference_type' => 'payment', 'reference_id' => $payment->id, 'created_by' => $actor->id,
        ]);
        DB::table('cash_sessions')->where('id', $session)->update([
            'closed_at' => '2026-09-02 10:00:00', 'closed_by' => $actor->id, 'status' => 'closed', 'is_open' => null,
        ]);
        $before = $this->archive();
        $this->actingAs($actor)->get(route('payments.show', $payment))->assertOk();
        $this->patch(route('payments.cancel', $payment), ['cancellation_reason' => 'Eski kayıt düzeltmesi'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame($session, $payment->fresh()->cash_session_id);
        $this->assertSame('0.00', app(LedgerService::class)->balanceForCompany($company->id, Currency::TRY)->toDecimal());
        $this->assertSame($before, $this->archive());
        $this->assertDatabaseCount('ledger_entries', 2);
        $this->expectException(PaymentAlreadyCancelled::class);
        app(PaymentService::class)->cancel($payment->fresh(), 'Tekrar', $actor);
    }

    #[Test]
    public function individual_cash_partial_payment_and_advance_keep_correct_balances(): void
    {
        $actor = $this->userWithRole(RoleName::Admin);
        $stay = app(StayService::class)->create([
            'company_id' => null, 'guest_name' => 'Bireysel Misafir',
            'check_in_date' => '2026-09-13', 'check_out_date' => '2026-09-14',
            'pricing_mode' => 'total', 'amount' => '1000.00', 'currency' => 'TRY',
        ], $actor);
        $company = Company::query()->findOrFail($stay->company_id);
        $this->assertSame('individual', $company->account_type->value);
        $payment = app(PaymentService::class)->create($this->payload($company), $actor);
        $this->assertSame('250.00', app(LedgerService::class)->balanceForCompany($company->id, Currency::TRY)->toDecimal());
        $this->assertSame('750.00', app(PaymentAllocationService::class)->summaries([$stay->id])[$stay->id]['paid']->toDecimal());
        $second = app(PaymentService::class)->create($this->payload($company), $actor);
        $this->assertSame('500.00', app(PaymentAllocationService::class)->available($second)->toDecimal());
        app(PaymentService::class)->cancel($second, 'Düzeltme', $actor);
        $this->assertSame('250.00', app(LedgerService::class)->balanceForCompany($company->id, Currency::TRY)->toDecimal());
        $this->assertSame('0.00', app(PaymentAllocationService::class)->available($payment)->toDecimal());
        $this->assertDatabaseCount('cash_movements', 0);
    }

    #[Test]
    public function cash_routes_are_removed_for_every_role_including_admin(): void
    {
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $this->assertFalse(str_starts_with($route->getName() ?? '', 'cashbook.'));
        }
        foreach (RoleName::cases() as $role) {
            $actor = $this->userWithRole($role);
            $this->actingAs($actor);
            foreach (['/cashbook', '/cashbook/movements', '/cashbook/transfers', '/cashbook/day', '/cashbook/categories'] as $url) {
                $this->get($url)->assertNotFound();
                $this->post($url, [])->assertNotFound();
            }
            $this->assertFalse($actor->can('cashbook.operate'));
            foreach (['home', 'companies.index', 'stays.index', 'payments.index'] as $route) {
                $this->get(route($route))->assertOk()->assertDontSee('/cashbook', false)->assertDontSee('Kasa Defteri');
            }
        }
    }

    #[Test]
    public function old_operations_link_obeys_financial_authorization_and_queries_no_cash_tables(): void
    {
        foreach (RoleName::cases() as $role) {
            $actor = $this->userWithRole($role);
            DB::enableQueryLog();
            DB::flushQueryLog();
            $response = $this->actingAs($actor)->get(route('home', ['view' => 'operations']))->assertOk();
            $queries = DB::getQueryLog();
            DB::disableQueryLog();
            foreach ($queries as $query) {
                $this->assertDoesNotMatchRegularExpression('/(?:from|join|into|update)\s+`?cash_/i', $query['query']);
            }
            $response->assertViewIs($role === RoleName::Personel ? 'dashboard::personnel' : 'dashboard::finance');
        }
    }

    #[Test]
    public function seeder_removes_legacy_cash_permissions_from_custom_roles_and_users_only(): void
    {
        $person = $this->userWithRole(RoleName::Personel);
        $custom = Role::query()->create(['name' => 'Özel', 'guard_name' => 'web', 'is_system' => false]);
        $person->assignRole($custom);
        $custom->givePermissionTo('payments.view');
        foreach (['cashbook.view', 'cashbook.operate', 'cashbook.view_all', 'cashbook.manage_categories'] as $name) {
            $permission = Permission::findOrCreate($name, 'web');
            $custom->givePermissionTo($permission);
            $person->givePermissionTo($permission);
        }
        $this->seed(RolePermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->assertSame(0, Permission::query()->where('name', 'like', 'cashbook.%')->count());
        $this->assertSame(['payments.view'], $custom->fresh()->permissions->pluck('name')->all());
        $this->assertTrue($person->fresh()->hasRole('Özel'));
        $this->assertTrue($person->fresh()->can('payments.create'));
        $this->assertSame([], array_values(array_filter(PermissionEnum::values(), fn ($name) => str_starts_with($name, 'cashbook.'))));
    }

    #[Test]
    public function foreign_cash_paid_personally_keeps_original_currency_rate_and_company_debt(): void
    {
        $actor = $this->userWithRole(RoleName::Admin);
        $company = Company::factory()->create();
        $data = [
            'company_id' => $company->id, 'guest_name' => 'Firma Misafiri',
            'check_in_date' => '2026-09-13', 'check_out_date' => '2026-09-14',
            'pricing_mode' => 'total', 'amount' => '1000.00', 'currency' => 'TRY',
        ];
        app(StayService::class)->create($data, $actor);
        $stay = app(StayService::class)->create(array_replace($data, [
            'guest_name' => 'Firma Misafiri EUR',
            'amount' => '100.00', 'currency' => 'EUR', 'exchange_rate_to_try' => '40.0000',
        ]), $actor);
        $payment = app(PaymentService::class)->create(array_replace($this->payload($company), [
            'amount' => '25.00', 'currency' => 'EUR', 'exchange_rate_to_try' => '41.0000',
            'payer_type' => 'individual', 'payer_name' => 'Firma Misafiri',
            'allocation_mode' => 'selected', 'stay_ids' => [$stay->id],
        ]), $actor);
        $this->assertSame('25.00', $payment->amountMoney()->toDecimal());
        $this->assertSame('1025.00', $payment->try_equivalent);
        $this->assertSame('Firma Misafiri', $payment->payer_name);
        $this->assertSame('75.00', app(LedgerService::class)->balanceForCompany($company->id, Currency::EUR)->toDecimal());
        $this->assertSame('1000.00', app(LedgerService::class)->balanceForCompany($company->id, Currency::TRY)->toDecimal());
        $this->assertSame('25.00', app(PaymentAllocationService::class)->summaries([$stay->id])[$stay->id]['paid']->toDecimal());
        $this->assertDatabaseCount('cash_movements', 0);
    }

    #[Test]
    public function archived_cash_audit_entries_remain_readable_without_the_removed_models(): void
    {
        $actor = $this->userWithRole(RoleName::Admin);
        $id = DB::table('audit_logs')->insertGetId([
            'user_id' => $actor->id, 'user_name' => $actor->name, 'event' => 'created',
            'auditable_type' => 'cash_session', 'auditable_id' => 123,
            'new_values' => json_encode(['opening_note' => 'Eski kasa açılışı']), 'created_at' => now(),
        ]);
        $before = DB::table('audit_logs')->where('id', $id)->first();
        $this->actingAs($actor)->get(route('audit.index'))->assertOk()->assertSee('cash_session #123');
        $this->assertEquals($before, DB::table('audit_logs')->where('id', $id)->first());
    }
}
