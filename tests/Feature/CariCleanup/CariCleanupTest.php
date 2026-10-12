<?php

declare(strict_types=1);

namespace Tests\Feature\CariCleanup;

use App\Models\User;
use App\Modules\Authorization\Enums\Permission as PermissionName;
use App\Modules\Authorization\Enums\RoleName;
use App\Modules\CariCleanup\Services\CariCleanupService;
use App\Modules\Companies\Models\Company;
use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
use App\Modules\Ledger\Services\LedgerService;
use App\Modules\Payments\Services\PaymentService;
use App\Modules\Rooms\Models\Room;
use App\Modules\Stays\Models\Stay;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Uses an independently migrated MySQL/MariaDB test database. This class never
 * resets the schema; every fixture is rolled back after its test.
 */
class CariCleanupTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->admin = $this->userWithRole(RoleName::Admin);
    }

    #[Test]
    public function active_admin_sees_actual_counts_and_a_blocked_cleanup(): void
    {
        $this->seedRepresentativeRecords();

        $response = $this->actingAs($this->admin)->get(route('cari-cleanup.index'));
        $response->assertOk()->assertViewIs('cari-cleanup::index')
            ->assertViewHas('preflight', false)->assertSee('Cari Veri Temizleme');

        $report = $response->viewData('report');
        $this->assertTrue($report['available']);
        $this->assertNotEmpty($report['blockers']);
        $tables = collect($report['tables'])->keyBy('name');
        $candidates = ['companies', 'stays', 'payments', 'payment_allocations', 'ledger_entries', 'offers', 'offer_periods', 'offer_room_types', 'offer_prices', 'receipt_book_entries'];
        $schemaTables = Schema::getTableListing(null, false);
        $this->assertCount(count($schemaTables), $tables);
        foreach ($schemaTables as $table) {
            $this->assertSame(! in_array($table, $candidates, true), $tables[$table]['protected'], $table.' must default to protected outside CARİ scope.');
        }

        foreach (['companies', 'stays', 'payments', 'payment_allocations', 'ledger_entries', 'offers', 'receipt_book_entries'] as $table) {
            $this->assertSame(DB::table($table)->count(), $tables[$table]['count'], $table);
            $this->assertFalse($tables[$table]['protected'], $table);
        }

        foreach (['monthly_account_days', 'monthly_account_revisions', 'monthly_account_items', 'monthly_payment_providers', 'personnel_employees', 'personnel_payroll_entries', 'users', 'roles', 'permissions', 'rooms', 'audit_logs', 'cash_sessions', 'cash_movements'] as $table) {
            $this->assertSame(DB::table($table)->count(), $tables[$table]['count'], $table);
            $this->assertTrue($tables[$table]['protected'], $table);
        }

        $this->assertMatchesRegularExpression('/<button\b[^>]*\bdisabled\b[^>]*>\s*CARİ VERİLERİNİ TEMİZLE\s*<\/button>/u', $response->getContent());
    }

    #[Test]
    public function page_and_repeated_preflights_preserve_every_table_row(): void
    {
        $this->seedRepresentativeRecords();
        $this->actingAs($this->admin);
        $before = $this->snapshotAllTables();

        $this->get(route('cari-cleanup.index'))->assertOk();
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $response = $this->post(route('cari-cleanup.preflight'), [
                'backup_confirmed' => true,
                'password' => 'password',
                'confirmation' => 'CARİ VERİLERİNİ TEMİZLE',
                'execute' => true,
            ]);
            $response->assertOk()->assertViewHas('preflight', true);
            $this->assertNotEmpty($response->viewData('report')['blockers']);
            $this->assertSame($before, $this->snapshotAllTables(), 'Preflight must leave all row contents unchanged.');
        }
    }

    #[Test]
    public function guests_cannot_open_the_page_or_run_preflight(): void
    {
        $before = $this->snapshotAllTables();
        $this->get(route('cari-cleanup.index'))->assertRedirect(route('login'));
        $this->post(route('cari-cleanup.preflight'))->assertRedirect(route('login'));
        $this->assertSame($before, $this->snapshotAllTables());
    }

    #[Test]
    public function non_admins_and_inactive_admins_are_rejected_even_with_permissions(): void
    {
        foreach ([RoleName::Patron, RoleName::Personel] as $role) {
            $user = $this->userWithRole($role);
            $user->givePermissionTo(array_map(fn (PermissionName $permission): string => $permission->value, PermissionName::cases()));
            $this->actingAs($user);
            $before = $this->snapshotAllTables();
            $this->assertFalse(Gate::forUser($user)->allows('view', CariCleanupService::class));
            $this->get(route('cari-cleanup.index'))->assertForbidden();
            $this->post(route('cari-cleanup.preflight'))->assertForbidden();
            $this->assertSame($before, $this->snapshotAllTables());
        }

        $inactive = $this->userWithRole(RoleName::Admin, ['is_active' => false]);
        $this->actingAs($inactive);
        $before = $this->snapshotAllTables();
        $this->assertFalse(Gate::forUser($inactive)->allows('view', CariCleanupService::class));
        $this->get(route('cari-cleanup.index'))->assertForbidden();
        $this->post(route('cari-cleanup.preflight'))->assertForbidden();
        $this->assertSame($before, $this->snapshotAllTables());
    }

    #[Test]
    public function forged_execution_requests_have_no_route_and_cannot_change_data(): void
    {
        $this->seedRepresentativeRecords();
        $this->actingAs($this->admin);
        $before = $this->snapshotAllTables();

        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $this->call($method, '/admin/cari-temizleme', [
                'backup_confirmed' => true, 'password' => 'password',
                'confirmation' => 'CARİ VERİLERİNİ TEMİZLE',
            ])->assertStatus(405);
            $this->call($method, '/admin/cari-temizleme/temizle')->assertNotFound();
        }
        $this->get(route('cari-cleanup.preflight'))->assertStatus(405);
        $this->assertSame($before, $this->snapshotAllTables());

        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route): bool => str_starts_with($route->uri(), 'admin/cari-temizleme'));
        $this->assertCount(2, $routes, 'Only the page and read-only preflight routes may exist.');
        $this->assertSame(['GET', 'HEAD', 'POST'], $routes->flatMap(fn ($route): array => $route->methods())->sort()->values()->all());
    }

    #[Test]
    public function real_request_forgery_protection_rejects_missing_and_invalid_tokens(): void
    {
        // Laravel skips token checks during tests. Override only that bypass;
        // the actual middleware and its session/token comparison remain active.
        $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });
        $this->actingAs($this->admin)->withSession(['_token' => 'valid-cleanup-csrf-token']);
        $before = $this->snapshotAllTables();

        $this->post(route('cari-cleanup.preflight'))->assertStatus(419);
        $this->post(route('cari-cleanup.preflight'), ['_token' => 'invalid'])->assertStatus(419);
        $this->post(route('cari-cleanup.preflight'), ['_token' => 'valid-cleanup-csrf-token'])->assertOk();
        $this->assertSame($before, $this->snapshotAllTables());
    }

    #[Test]
    public function report_uses_read_queries_and_real_trigger_metadata(): void
    {
        $this->seedRepresentativeRecords();
        $before = $this->snapshotAllTables();
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $report = app(CariCleanupService::class)->report();
        $this->assertTrue($report['available']);
        $this->assertNotEmpty($report['blockers']);
        $this->assertNotEmpty($queries);
        foreach ($queries as $query) {
            $this->assertMatchesRegularExpression('/^\s*(SELECT|SHOW|DESCRIBE|EXPLAIN|SAVEPOINT|RELEASE SAVEPOINT|ROLLBACK|START TRANSACTION)\b/i', $query, $query);
        }
        $this->assertNotEmpty(array_filter($queries, fn (string $sql): bool => str_contains(strtolower($sql), 'information_schema.triggers')));
        $triggers = collect($report['triggers'])->keyBy('name');
        foreach (['ledger_entries_block_delete' => 'ledger_entries', 'payment_allocations_no_delete' => 'payment_allocations'] as $name => $table) {
            $this->assertSame($table, $triggers[$name]['table']);
            $this->assertSame('DELETE', $triggers[$name]['event']);
        }
        foreach ([['ledger_entries', 'company_id', 'companies'], ['payment_allocations', 'payment_id', 'payments'], ['payment_allocations', 'stay_id', 'stays']] as [$table, $column, $parent]) {
            $dependency = collect($report['dependencies'])->first(fn (array $row): bool => $row['table'] === $table && $row['column'] === $column);
            $this->assertNotNull($dependency, $table.'.'.$column);
            $this->assertSame($parent, $dependency['referenced_table']);
            $this->assertSame('RESTRICT', $dependency['delete_rule']);
        }
        $this->assertSame($before, $this->snapshotAllTables());
    }

    #[Test]
    public function missing_required_schema_fails_closed_without_trusting_partial_counts(): void
    {
        $this->seedRepresentativeRecords();
        $before = $this->snapshotAllTables();
        $report = $this->reportWithMetadata(function (string $sql, array $bindings, Connection $connection): array {
            $rows = $connection->select($sql, $bindings);

            return str_contains($sql, 'information_schema.TABLES')
                ? array_values(array_filter($rows, fn (object $row): bool => $row->name !== 'ledger_entries'))
                : $rows;
        });

        $this->assertUnavailableReport($report);
        $this->assertSame($before, $this->snapshotAllTables());
    }

    #[Test]
    public function database_metadata_errors_fail_closed_and_preserve_all_rows(): void
    {
        $this->seedRepresentativeRecords();
        $before = $this->snapshotAllTables();
        $report = $this->reportWithMetadata(function (string $sql, array $bindings, Connection $connection): array {
            if (str_contains($sql, 'information_schema.TRIGGERS')) {
                throw new RuntimeException('Simulated metadata access failure.');
            }

            return $connection->select($sql, $bindings);
        });

        $this->assertUnavailableReport($report);
        $this->assertSame($before, $this->snapshotAllTables());
    }

    #[Test]
    public function missing_trigger_diagnostic_never_grants_cleanup_permission(): void
    {
        $before = $this->snapshotAllTables();
        $report = $this->reportWithMetadata(function (string $sql, array $bindings, Connection $connection): array {
            $rows = $connection->select($sql, $bindings);

            return str_contains($sql, 'information_schema.TRIGGERS')
                ? array_values(array_filter($rows, fn (object $row): bool => $row->name !== 'ledger_entries_block_delete'))
                : $rows;
        });

        $this->assertTrue($report['available']);
        $this->assertStringContainsString('ledger_entries_block_delete', implode(' ', $report['blockers']));
        $this->assertStringContainsString('silme izni değil', implode(' ', $report['blockers']));
        $this->assertSame($before, $this->snapshotAllTables());
    }

    /** @param array<string, mixed> $report */
    private function assertUnavailableReport(array $report): void
    {
        $this->assertFalse($report['available']);
        $this->assertNotEmpty($report['blockers']);
        $this->assertSame([], $report['tables']);
        foreach (array_merge($report['categories'], $report['protected_groups']) as $category) {
            $this->assertNull($category['count'], 'Unavailable counts must never be reported as zero.');
        }
    }

    /**
     * Fault injection changes only SELECT results; the real schema and its
     * triggers remain intact. Restore the facade before transaction teardown.
     *
     * @param  callable(string, array, Connection): array  $select
     * @return array<string, mixed>
     */
    private function reportWithMetadata(callable $select): array
    {
        $manager = DB::getFacadeRoot();
        $connection = DB::connection();
        $readConnection = Mockery::mock(Connection::class);
        $readConnection->shouldReceive('getDriverName')->andReturn('mysql');
        $readConnection->shouldReceive('transactionLevel')->andReturn(1);
        $readConnection->shouldReceive('getDatabaseName')->andReturn($connection->getDatabaseName());
        $readConnection->shouldReceive('select')->andReturnUsing(fn (string $sql, array $bindings): array => $select($sql, $bindings, $connection));
        $readConnection->shouldReceive('table')->andReturnUsing(fn (string $name) => $connection->table($name));
        DB::shouldReceive('connection')->once()->andReturn($readConnection);

        try {
            return app(CariCleanupService::class)->report();
        } finally {
            DB::swap($manager);
        }
    }

    /** @return array<string, list<string>> */
    private function snapshotAllTables(): array
    {
        $snapshot = [];
        foreach (Schema::getTableListing() as $table) {
            // SESSION_DRIVER=array keeps even session/cache infrastructure out
            // of the DB during these requests; include all tables regardless.
            $rows = DB::table($table)->get()->map(function (object $row): string {
                $values = (array) $row;
                ksort($values);

                return json_encode($values, JSON_THROW_ON_ERROR);
            })->all();
            sort($rows, SORT_STRING);
            $snapshot[$table] = $rows;
        }
        ksort($snapshot);

        return $snapshot;
    }

    private function seedRepresentativeRecords(): void
    {
        $this->actingAs($this->admin);
        $company = Company::factory()->create(['created_by' => $this->admin->id]);
        Company::factory()->create(['account_type' => 'individual', 'is_active' => false, 'created_by' => $this->admin->id]);
        Company::factory()->create(['deleted_at' => now(), 'created_by' => $this->admin->id]);
        $room = Room::factory()->create();
        $stay = Stay::factory()->forRoom($room)->create([
            'company_id' => $company->id, 'amount' => '1500.00', 'created_by' => $this->admin->id,
        ]);
        app(LedgerService::class)->debit(
            $company->id, Money::fromDecimal('1500.00'), Currency::TRY, $stay->check_in_date,
            'Cari temizleme koruma testi', 'stay', $stay->id, $this->admin,
        );
        $payment = app(PaymentService::class)->create([
            'company_id' => $company->id, 'payment_date' => '2026-10-01', 'amount' => '500.00',
            'currency' => 'TRY', 'payment_method' => 'bank_transfer', 'allocation_mode' => 'selected',
            'stay_ids' => [$stay->id],
        ], $this->admin);
        $this->assertTrue(DB::table('payment_allocations')->where('payment_id', $payment->id)->exists());

        $now = now();
        $actor = ['created_by' => $this->admin->id, 'updated_by' => $this->admin->id];
        $timestamps = ['created_at' => $now, 'updated_at' => $now];
        $provider = 'Korunan Banka '.Str::uuid();
        DB::table('monthly_payment_providers')->insert(['name' => $provider, 'is_active' => true] + $actor + $timestamps);
        $day = DB::table('monthly_account_days')->insertGetId([
            'owner_id' => $this->admin->id, 'account_date' => '2026-10-01', 'current_version' => 2,
        ] + $actor + $timestamps);
        foreach ([1 => '250.00', 2 => '375.00'] as $version => $amount) {
            $revision = DB::table('monthly_account_revisions')->insertGetId([
                'day_id' => $day, 'version' => $version, 'daily_total' => $amount,
                'note' => 'Korunan tarihsel revizyon '.$version, 'created_by' => $this->admin->id, 'created_at' => $now,
            ]);
            DB::table('monthly_account_items')->insert([
                'revision_id' => $revision, 'method' => 'bank_transfer', 'currency' => 'TRY', 'bank_name' => $provider,
                'amount' => $amount, 'try_equivalent' => $amount, 'created_at' => $now,
            ]);
        }
        $employee = DB::table('personnel_employees')->insertGetId([
            'employee_code' => 'QA-'.Str::random(12), 'first_name' => 'Korunan', 'last_name' => 'Personel',
            'start_date' => '2026-09-01',
        ] + $actor + $timestamps);
        DB::table('personnel_payroll_entries')->insert([
            'employee_id' => $employee, 'entry_date' => '2026-10-01', 'period' => '2026-10',
            'type' => 'earning', 'direction' => 'credit', 'amount' => '2500.00', 'currency' => 'TRY',
            'created_by' => $this->admin->id, 'created_at' => $now,
        ]);
        $session = DB::table('cash_sessions')->insertGetId([
            'opened_by' => $this->admin->id, 'opened_at' => '2026-09-01 10:00:00',
            'status' => 'open', 'is_open' => 1,
        ] + $timestamps);
        DB::table('cash_movements')->insert([
            'cash_session_id' => $session, 'type' => 'room_income', 'direction' => 'in', 'amount' => '500.00',
            'currency' => 'TRY', 'entry_at' => '2026-09-01 12:00:00', 'reference_type' => 'payment',
            'reference_id' => $payment->id, 'created_by' => $this->admin->id, 'created_at' => $now,
        ]);
        DB::table('cash_sessions')->where('id', $session)->update([
            'status' => 'closed', 'is_open' => null,
            'closed_by' => $this->admin->id, 'closed_at' => '2026-09-01 18:00:00',
        ]);
        $offer = DB::table('offers')->insertGetId([
            'company_id' => $company->id, 'customer_name_snapshot' => $company->name,
            'title' => 'Korunan teklif', 'offer_date' => '2026-10-01', 'currency' => 'TRY',
            'document_heading' => 'Konaklama teklifi', 'document_year' => 2026,
        ] + $actor + $timestamps);
        $period = DB::table('offer_periods')->insertGetId(['offer_id' => $offer, 'label' => 'Ekim', 'sort_order' => 1]);
        $type = DB::table('offer_room_types')->insertGetId(['offer_id' => $offer, 'name' => 'Standart', 'sort_order' => 1]);
        DB::table('offer_prices')->insert(['offer_id' => $offer, 'offer_period_id' => $period, 'offer_room_type_id' => $type, 'price' => '1500.00']);
        DB::table('receipt_book_entries')->insert([
            'receipt_date' => '2026-10-01', 'customer_name' => 'Korunan makbuz', 'amount' => '500.00', 'currency' => 'TRY',
        ] + $actor + $timestamps);
    }
}
