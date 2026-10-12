<?php

declare(strict_types=1);

namespace Tests\Feature\CariCleanup;

use App\Models\User;
use App\Modules\Authorization\Enums\Permission as PermissionName;
use App\Modules\Authorization\Enums\RoleName;
use App\Modules\CariCleanup\Services\CariCleanupInventory;
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
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Uses independently migrated MySQL/MariaDB test schema, never schema resets.
 * Fixtures and successful cleanup are enclosed by a rollback-only test transaction.
 * A separate isolated smoke check exercises the actual production commit branch.
 */
class CariCleanupTest extends TestCase
{
    use DatabaseTransactions;

    private const TARGETS = [
        'stays', 'offers', 'offer_periods', 'offer_room_types', 'offer_prices',
        'payments', 'receipt_book_entries', 'ledger_entries', 'payment_allocations',
    ];

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->admin = $this->userWithRole(RoleName::Admin);
        $this->actingAs($this->admin)->withSession([]);
    }

    #[Test]
    public function active_admin_sees_actual_counts_and_protected_account_cards(): void
    {
        $this->seedRepresentativeRecords();
        $before = $this->snapshotAllTables();
        $response = $this->get(route('cari-cleanup.index'));
        $response->assertOk()->assertViewIs('cari-cleanup::index')->assertSee('Cari Veri Temizleme');

        $report = $response->viewData('report');
        $this->assertTrue($report['available']);
        $this->assertTrue($report['ready']);
        $this->assertFalse($report['completed']);
        $this->assertSame([], $report['blockers']);
        $tables = collect($report['tables'])->keyBy('name');
        $schemaTables = Schema::getTableListing(null, false);
        $this->assertCount(count($schemaTables), $tables);
        foreach ($schemaTables as $table) {
            $this->assertSame(DB::table($table)->count(), $tables[$table]['count'], $table);
            $this->assertSame(! in_array($table, self::TARGETS, true), $tables[$table]['protected'], $table);
        }
        $this->assertTrue($tables['companies']['protected']);
        $this->assertSame($before, $this->snapshotAllTables());
    }

    #[Test]
    public function page_and_repeated_preflight_do_not_delete_or_modify_database_rows(): void
    {
        $this->seedRepresentativeRecords();
        $before = $this->snapshotAllTables();
        $this->get(route('cari-cleanup.index'))->assertOk();
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $response = $this->post(route('cari-cleanup.preflight'), ['execute' => true]);
            $response->assertOk()->assertViewHas('preflight', true);
            $this->assertIsString($response->viewData('token'));
            $this->assertNotEmpty($response->viewData('token'));
            $this->assertSame($before, $this->snapshotAllTables());
        }
    }

    #[Test]
    public function cleanup_deletes_exactly_nine_tables_and_preserves_every_other_row(): void
    {
        $this->seedRepresentativeRecords();
        $this->assertTrue(DB::table('ledger_entries')->whereNotNull('reverses_entry_id')->exists());
        $this->assertTrue(DB::table('payment_allocations')->whereNotNull('reverses_allocation_id')->exists());
        $before = $this->snapshotAllTables();
        $token = $this->prepareToken();

        $outcome = app(CariCleanupService::class)->execute($this->admin, $token);

        $this->assertNotEmpty($outcome['completed_at']);
        foreach (self::TARGETS as $table) {
            $this->assertGreaterThan(0, count($before[$table]), $table.' fixture must exercise deletion.');
            $this->assertSame(count($before[$table]), $outcome['deleted'][$table], $table);
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertCount(count(self::TARGETS), $outcome['deleted']);
        $protected = $this->protectedRows($before);
        $this->assertSame($protected, $this->protectedRows($this->snapshotAllTables()));
        $this->assertArrayHasKey('companies', $outcome['protected']);
        foreach ($protected as $table => $rows) {
            $this->assertSame(count($rows), $outcome['protected'][$table]['count'], $table);
            $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $outcome['protected'][$table]['sha256'], $table);
        }
        $this->assertDatabaseHas('cari_cleanup_operations', ['id' => 1, 'status' => 'completed']);
        $this->assertCapabilityCleared();
    }

    #[Test]
    public function completion_is_persisted_and_prevents_replay_or_new_preflight(): void
    {
        $this->seedRepresentativeRecords();
        $token = $this->prepareToken();
        $outcome = app(CariCleanupService::class)->execute($this->admin, $token);
        $before = $this->snapshotAllTables();
        $report = app(CariCleanupService::class)->report();

        $this->assertTrue($report['completed']);
        $this->assertFalse($report['ready']);
        $this->assertSame($outcome['deleted'], $report['completion_report']['deleted']);
        $this->assertImmutableSql(fn () => DB::table('cari_cleanup_operations')->where('id', 1)->update(['status' => 'ready']));
        $this->assertImmutableSql(fn () => DB::table('cari_cleanup_operations')->where('id', 1)->delete());
        $this->assertValidationFailure(fn () => app(CariCleanupService::class)->execute($this->admin, $token));
        $prepared = app(CariCleanupService::class)->prepare($this->admin);
        $this->assertNull($prepared['token']);
        $this->assertSame($before, $this->snapshotAllTables());
        $this->assertCapabilityCleared();
    }

    #[Test]
    public function missing_or_tampered_tokens_cannot_start_cleanup(): void
    {
        $this->seedRepresentativeRecords();
        $token = $this->prepareToken();
        $before = $this->snapshotAllTables();
        foreach (['', 'forged-token', $token.'tampered'] as $invalid) {
            $this->assertValidationFailure(fn () => app(CariCleanupService::class)->execute($this->admin, $invalid));
            $this->assertSame($before, $this->snapshotAllTables());
            $this->assertCapabilityCleared();
        }
    }

    #[Test]
    public function invalid_token_clears_a_stale_connection_capability(): void
    {
        $this->seedRepresentativeRecords();
        $before = $this->snapshotAllTables();
        DB::statement('SET @cari_cleanup_token = ?', ['stale-capability-from-another-call']);
        $this->assertValidationFailure(fn () => app(CariCleanupService::class)->execute($this->admin, 'invalid-token'));
        $this->assertSame($before, $this->snapshotAllTables());
        $this->assertCapabilityCleared();
    }

    #[Test]
    public function authenticated_http_execution_uses_password_confirmation_and_preflight(): void
    {
        $this->seedRepresentativeRecords();
        $response = $this->post(route('cari-cleanup.preflight'));
        $response->assertOk();
        $token = $response->viewData('token');
        $this->assertIsString($token);
        $before = $this->snapshotAllTables();

        $this->post(route('cari-cleanup.execute'), $this->executePayload($token))
            ->assertSessionHasNoErrors()->assertRedirect(route('cari-cleanup.index'));

        foreach (self::TARGETS as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertSame($this->protectedRows($before), $this->protectedRows($this->snapshotAllTables()));
        $completedPage = $this->get(route('cari-cleanup.index'));
        $completedPage->assertOk();
        $this->assertTrue($completedPage->viewData('report')['completed']);
        $completedPage->assertDontSee('action="'.route('cari-cleanup.execute').'"', false);
        $completedPage->assertDontSee('href="'.route('cari-cleanup.index').'"', false);
        $this->assertCapabilityCleared();
    }

    #[Test]
    public function execution_attempts_are_rate_limited_without_modifying_domain_data(): void
    {
        $this->seedRepresentativeRecords();
        $token = $this->prepareToken();
        $before = $this->snapshotAllTables();
        $payload = array_replace($this->executePayload($token), ['password' => 'incorrect-password']);
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->post(route('cari-cleanup.execute'), $payload)->assertSessionHasErrors('password');
        }
        $this->post(route('cari-cleanup.execute'), $payload)->assertStatus(429);
        $this->assertSame($before, $this->snapshotAllTables());
        $this->assertCapabilityCleared();
    }

    #[Test]
    public function missing_required_table_metadata_fails_closed_without_schema_mutation(): void
    {
        $this->seedRepresentativeRecords();
        $before = $this->snapshotAllTables();
        $schema = app(CariCleanupInventory::class)->schema(DB::connection());
        $schema['tables'] = array_values(array_filter($schema['tables'], fn (array $table): bool => $table['name'] !== 'ledger_entries'));
        $this->replaceInventorySchema($schema);

        $report = app(CariCleanupService::class)->report();
        $this->assertFalse($report['available']);
        $this->assertFalse($report['ready']);
        $this->assertNotEmpty($report['blockers']);
        $this->assertNull(app(CariCleanupService::class)->prepare($this->admin)['token']);
        $this->assertSame($before, $this->snapshotAllTables());
    }

    #[Test]
    public function protected_foreign_key_into_cleanup_scope_blocks_all_deletions(): void
    {
        $this->seedRepresentativeRecords();
        $token = $this->prepareToken();
        $before = $this->snapshotAllTables();
        $schema = app(CariCleanupInventory::class)->schema(DB::connection());
        $schema['dependencies'][] = [
            'table' => 'personnel_payroll_entries', 'column' => 'legacy_payment_id',
            'referenced_table' => 'payments', 'referenced_column' => 'id',
            'delete_rule' => 'CASCADE', 'update_rule' => 'RESTRICT',
        ];
        $this->replaceInventorySchema($schema);

        $report = app(CariCleanupService::class)->report();
        $this->assertTrue($report['available']);
        $this->assertFalse($report['ready']);
        $this->assertStringContainsString('personnel_payroll_entries', implode(' ', $report['blockers']));
        $this->assertValidationFailure(fn () => app(CariCleanupService::class)->execute($this->admin, $token));
        $this->assertSame($before, $this->snapshotAllTables());
        $this->assertCapabilityCleared();
    }

    #[Test]
    public function incomplete_protection_migration_does_not_issue_an_execution_token(): void
    {
        $before = $this->snapshotAllTables();
        $schema = app(CariCleanupInventory::class)->schema(DB::connection());
        $schema['triggers'][] = [
            'table' => 'ledger_entries', 'name' => 'cari_cleanup_install_guard_ledger_entries',
            'event' => 'DELETE', 'timing' => 'BEFORE', 'definition' => "SIGNAL SQLSTATE '45000'",
        ];
        $this->replaceInventorySchema($schema);
        $prepared = app(CariCleanupService::class)->prepare($this->admin);
        $this->assertFalse($prepared['ready']);
        $this->assertNull($prepared['token']);
        $this->assertNotEmpty($prepared['blockers']);
        $this->assertSame($before, $this->snapshotAllTables());
    }

    #[Test]
    public function token_is_bound_to_the_current_session_and_admin(): void
    {
        $this->seedRepresentativeRecords();
        $otherAdmin = $this->userWithRole(RoleName::Admin);
        $token = $this->prepareToken();
        $before = $this->snapshotAllTables();
        $this->assertValidationFailure(fn () => app(CariCleanupService::class)->execute($otherAdmin, $token));
        session()->forget('cari_cleanup.preflight_hash');
        $this->assertValidationFailure(fn () => app(CariCleanupService::class)->execute($this->admin, $token));
        $this->assertSame($before, $this->snapshotAllTables());
        $this->assertCapabilityCleared();
    }

    #[Test]
    public function expired_token_cannot_start_cleanup(): void
    {
        $this->seedRepresentativeRecords();
        $token = $this->prepareToken();
        $before = $this->snapshotAllTables();
        $this->travel(11)->minutes();
        try {
            $this->assertValidationFailure(fn () => app(CariCleanupService::class)->execute($this->admin, $token));
        } finally {
            $this->travelBack();
        }
        $this->assertSame($before, $this->snapshotAllTables());
        $this->assertCapabilityCleared();
    }

    #[Test]
    public function token_expiring_during_lock_acquisition_is_rechecked_before_deleting(): void
    {
        $this->seedRepresentativeRecords();
        $token = $this->prepareToken();
        $before = $this->snapshotAllTables();
        $actual = app(CariCleanupInventory::class);
        $advanced = false;
        $inventory = Mockery::mock(CariCleanupInventory::class);
        $inventory->shouldReceive('inspect')->andReturnUsing(function (Connection $db, bool $contents = false, bool $lock = false) use ($actual, &$advanced): array {
            $report = $actual->inspect($db, $contents, $lock);
            if ($lock && ! $advanced) {
                $advanced = true;
                $this->travel(11)->minutes();
            }

            return $report;
        });
        $this->instance(CariCleanupInventory::class, $inventory);

        try {
            $this->assertValidationFailure(fn () => app(CariCleanupService::class)->execute($this->admin, $token));
        } finally {
            $this->travelBack();
        }
        $this->assertTrue($advanced, 'Expiry must be tested after the real locking scan.');
        $this->assertSame($before, $this->snapshotAllTables());
        $this->assertCapabilityCleared();
    }

    #[Test]
    public function inventory_failure_prevents_execution_and_issues_no_preflight_token(): void
    {
        $this->seedRepresentativeRecords();
        $token = $this->prepareToken();
        $before = $this->snapshotAllTables();
        $inventory = Mockery::mock(CariCleanupInventory::class);
        $inventory->shouldReceive('inspect')->andThrow(new RuntimeException('Injected inventory metadata failure.'));
        $this->instance(CariCleanupInventory::class, $inventory);

        $report = app(CariCleanupService::class)->report();
        $this->assertFalse($report['available']);
        $this->assertFalse($report['ready']);
        $this->assertNotEmpty($report['blockers']);
        $this->assertValidationFailure(fn () => app(CariCleanupService::class)->execute($this->admin, $token));
        $this->assertNull(app(CariCleanupService::class)->prepare($this->admin)['token']);
        $this->assertSame($before, $this->snapshotAllTables());
        $this->assertCapabilityCleared();
    }

    #[Test]
    public function target_content_changed_after_preflight_requires_a_new_preflight(): void
    {
        $this->seedRepresentativeRecords();
        $token = $this->prepareToken();
        DB::table('stays')->update(['guest_name' => 'Ön kontrolden sonra değişti']);
        $before = $this->snapshotAllTables();
        $this->assertValidationFailure(fn () => app(CariCleanupService::class)->execute($this->admin, $token));
        $this->assertSame($before, $this->snapshotAllTables());
        $this->assertCapabilityCleared();
    }

    #[Test]
    public function account_card_changed_after_preflight_invalidates_the_token(): void
    {
        $this->seedRepresentativeRecords();
        $token = $this->prepareToken();
        DB::table('companies')->update(['phone' => '05550000000']);
        $before = $this->snapshotAllTables();
        $this->assertValidationFailure(fn () => app(CariCleanupService::class)->execute($this->admin, $token));
        $this->assertSame($before, $this->snapshotAllTables());
        $this->assertCapabilityCleared();
    }

    #[Test]
    public function other_module_changes_after_preflight_are_preserved_at_execution_time(): void
    {
        $this->seedRepresentativeRecords();
        $token = $this->prepareToken();
        DB::table('monthly_payment_providers')->update(['name' => 'Korunan güncel banka '.Str::uuid()]);
        $before = $this->snapshotAllTables();
        app(CariCleanupService::class)->execute($this->admin, $token);
        $this->assertSame($this->protectedRows($before), $this->protectedRows($this->snapshotAllTables()));
        $this->assertCapabilityCleared();
    }

    #[Test]
    public function password_phrase_checkbox_and_preflight_token_are_validated_server_side(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->seedRepresentativeRecords();
        $token = $this->prepareToken();
        $payload = $this->executePayload($token);
        $before = $this->snapshotAllTables();
        foreach ([
            ['password' => 'incorrect-password'],
            ['confirmation' => 'cari girişlerini sil'],
            ['confirmation' => 'yanlış ifade'],
            ['confirm_irreversible' => 'no'],
            ['preflight_token' => ''],
        ] as $invalid) {
            $response = $this->post(route('cari-cleanup.execute'), array_replace($payload, $invalid));
            $response->assertSessionHasErrors();
            $this->assertSame($before, $this->snapshotAllTables());
        }
        $this->assertCapabilityCleared();
    }

    #[Test]
    public function only_active_admin_can_view_prepare_or_execute_cleanup(): void
    {
        $this->app['auth']->forgetGuards();
        $this->get(route('cari-cleanup.index'))->assertRedirect(route('login'));
        $this->post(route('cari-cleanup.preflight'))->assertRedirect(route('login'));
        $this->post(route('cari-cleanup.execute'))->assertRedirect(route('login'));
        foreach ([RoleName::Patron, RoleName::Personel] as $role) {
            $user = $this->userWithRole($role);
            $user->givePermissionTo(array_map(fn (PermissionName $permission): string => $permission->value, PermissionName::cases()));
            $this->actingAs($user);
            $before = $this->snapshotAllTables();
            $this->assertFalse(Gate::forUser($user)->allows('view', CariCleanupService::class));
            $this->get(route('cari-cleanup.index'))->assertForbidden();
            $this->post(route('cari-cleanup.preflight'))->assertForbidden();
            $this->post(route('cari-cleanup.execute'), $this->executePayload('forged'))->assertForbidden();
            $this->assertSame($before, $this->snapshotAllTables());
        }
        $inactive = $this->userWithRole(RoleName::Admin, ['is_active' => false]);
        $this->actingAs($inactive);
        $before = $this->snapshotAllTables();
        $this->get(route('cari-cleanup.index'))->assertForbidden();
        $this->post(route('cari-cleanup.preflight'))->assertForbidden();
        $this->post(route('cari-cleanup.execute'), $this->executePayload('forged'))->assertForbidden();
        $this->assertSame($before, $this->snapshotAllTables());
    }

    #[Test]
    public function execution_is_post_only_and_real_csrf_checks_are_enforced(): void
    {
        $this->get(route('cari-cleanup.execute'))->assertStatus(405);
        $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });
        $this->withSession(['_token' => 'valid-cleanup-csrf-token']);
        $before = $this->snapshotAllTables();
        $this->post(route('cari-cleanup.preflight'))->assertStatus(419);
        $this->post(route('cari-cleanup.execute'), $this->executePayload('forged'))->assertStatus(419);
        $this->post(route('cari-cleanup.execute'), $this->executePayload('forged') + ['_token' => 'invalid'])->assertStatus(419);
        $this->post(route('cari-cleanup.preflight'), ['_token' => 'valid-cleanup-csrf-token'])->assertOk();
        $this->assertSame($before, $this->snapshotAllTables());
    }

    #[Test]
    public function exception_after_an_intermediate_delete_rolls_back_all_rows_and_operation_state(): void
    {
        $this->seedRepresentativeRecords();
        $token = $this->prepareToken();
        $before = $this->snapshotAllTables();
        $injected = false;
        DB::listen(function (QueryExecuted $query) use (&$injected): void {
            if (! $injected && preg_match('/^\s*delete\s+from\s+[\x60"]?payments[\x60"]?\b/i', $query->sql)) {
                $injected = true;
                throw new RuntimeException('Injected failure after payment DELETE.');
            }
        });
        $this->assertValidationFailure(fn () => app(CariCleanupService::class)->execute($this->admin, $token));
        $this->assertTrue($injected, 'The test must fail after a real DELETE, not during preflight.');
        $this->assertSame($before, $this->snapshotAllTables());
        $this->assertDatabaseHas('cari_cleanup_operations', ['id' => 1, 'status' => 'ready']);
        $this->assertCapabilityCleared();
    }

    #[Test]
    public function protected_row_integrity_failure_rolls_back_deleted_and_changed_rows(): void
    {
        $this->seedRepresentativeRecords();
        $token = $this->prepareToken();
        $before = $this->snapshotAllTables();
        $injected = false;
        DB::listen(function (QueryExecuted $query) use (&$injected): void {
            if (! $injected && preg_match('/^\s*delete\s+from\s+[\x60"]?payments[\x60"]?\b/i', $query->sql)) {
                $injected = true;
                DB::table('monthly_payment_providers')->update(['name' => 'Injected protected row alteration']);
            }
        });
        $this->assertValidationFailure(fn () => app(CariCleanupService::class)->execute($this->admin, $token));
        $this->assertTrue($injected);
        $this->assertSame($before, $this->snapshotAllTables());
        $this->assertCapabilityCleared();
    }

    #[Test]
    public function another_connection_holding_the_singleton_lock_prevents_execution(): void
    {
        $this->seedRepresentativeRecords();
        $token = $this->prepareToken();
        $before = $this->snapshotAllTables();
        config(['database.connections.cari_cleanup_lock_test' => config('database.connections.'.config('database.default'))]);
        $other = DB::connection('cari_cleanup_lock_test');
        $this->assertSame(1, (int) $other->selectOne('SELECT GET_LOCK(?, 0) AS acquired', ['lemon_cari_cleanup'])->acquired);
        try {
            $this->assertValidationFailure(fn () => app(CariCleanupService::class)->execute($this->admin, $token));
            $this->assertSame($before, $this->snapshotAllTables());
        } finally {
            $other->selectOne('SELECT RELEASE_LOCK(?) AS released', ['lemon_cari_cleanup']);
            DB::disconnect('cari_cleanup_lock_test');
        }
        $this->assertCapabilityCleared();
    }

    #[Test]
    public function normal_financial_delete_stays_blocked_and_a_session_variable_is_insufficient(): void
    {
        $this->seedRepresentativeRecords();
        $before = $this->snapshotAllTables();
        DB::statement('SET @cari_cleanup_token = ?', ['arbitrary-unapproved-capability']);
        try {
            foreach (['ledger_entries', 'payment_allocations'] as $table) {
                $id = DB::table($table)->max('id');
                $this->assertImmutableSql(fn () => DB::table($table)->where('id', $id)->delete());
            }
        } finally {
            DB::statement('SET @cari_cleanup_token = NULL');
        }
        $this->assertSame($before, $this->snapshotAllTables());
        $this->assertCapabilityCleared();
    }

    #[Test]
    public function wrong_connection_nonce_expiry_or_actor_does_not_authorize_trigger_deletion(): void
    {
        $this->seedRepresentativeRecords();
        $personel = $this->userWithRole(RoleName::Personel);
        $inactive = $this->userWithRole(RoleName::Admin, ['is_active' => false]);
        $deleted = $this->userWithRole(RoleName::Admin);
        DB::table('users')->where('id', $deleted->id)->update(['deleted_at' => now()]);
        $token = Str::random(64);
        $connectionId = (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
        $valid = [
            'status' => 'running', 'actor_id' => $this->admin->id, 'connection_id' => $connectionId,
            'nonce_hash' => hash('sha256', $token), 'expires_at' => now('UTC')->addMinutes(10),
            'completed_at' => null,
        ];
        DB::statement('SET @cari_cleanup_token = ?', [$token]);
        try {
            foreach ([
                ['connection_id' => $connectionId + 1],
                ['nonce_hash' => hash('sha256', 'wrong nonce')],
                ['expires_at' => now('UTC')->subMinute()],
                ['actor_id' => $personel->id],
                ['actor_id' => $inactive->id],
                ['actor_id' => $deleted->id],
                ['status' => 'ready'],
            ] as $invalid) {
                DB::table('cari_cleanup_operations')->where('id', 1)->update(array_replace($valid, $invalid));
                foreach (['ledger_entries', 'payment_allocations'] as $table) {
                    $id = DB::table($table)->max('id');
                    $this->assertImmutableSql(fn () => DB::table($table)->where('id', $id)->delete());
                }
            }
        } finally {
            DB::statement('SET @cari_cleanup_token = NULL');
        }
        $this->assertCapabilityCleared();
    }

    #[Test]
    public function financial_update_remains_immutable_even_with_a_matching_delete_capability(): void
    {
        $this->seedRepresentativeRecords();
        $token = Str::random(64);
        DB::table('cari_cleanup_operations')->where('id', 1)->update([
            'status' => 'running', 'actor_id' => $this->admin->id,
            'connection_id' => (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id,
            'nonce_hash' => hash('sha256', $token), 'expires_at' => now('UTC')->addMinutes(10),
        ]);
        $before = $this->snapshotAllTables();
        DB::statement('SET @cari_cleanup_token = ?', [$token]);
        try {
            $this->assertImmutableSql(fn () => DB::table('ledger_entries')->where('id', DB::table('ledger_entries')->max('id'))->update(['description' => 'Forbidden change']));
            $this->assertImmutableSql(fn () => DB::table('payment_allocations')->where('id', DB::table('payment_allocations')->max('id'))->update(['reason' => 'Forbidden change']));
        } finally {
            DB::statement('SET @cari_cleanup_token = NULL');
        }
        $this->assertSame($before, $this->snapshotAllTables());
        $this->assertCapabilityCleared();
    }

    private function prepareToken(): string
    {
        $prepared = app(CariCleanupService::class)->prepare($this->admin);
        $this->assertIsString($prepared['token']);
        $this->assertNotEmpty($prepared['token']);

        return $prepared['token'];
    }

    /** @param array<string, list<array<string, mixed>>> $schema */
    private function replaceInventorySchema(array $schema): void
    {
        // Only metadata results are substituted. The real tables, triggers,
        // constraints and transaction remain untouched in these tests.
        $inventory = Mockery::mock(CariCleanupInventory::class)->makePartial();
        $inventory->shouldReceive('schema')->andReturn($schema);
        $this->instance(CariCleanupInventory::class, $inventory);
    }

    /** @return array<string, mixed> */
    private function executePayload(string $token): array
    {
        return [
            'password' => 'password', 'confirmation' => 'CARİ GİRİŞLERİNİ SİL',
            'preflight_token' => $token, 'confirm_irreversible' => '1',
        ];
    }

    private function assertValidationFailure(callable $action): void
    {
        try {
            $action();
            $this->fail('Cleanup must reject this request.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
    }

    private function assertImmutableSql(callable $action): void
    {
        try {
            $action();
            $this->fail('Database immutability guard must reject the statement.');
        } catch (QueryException $exception) {
            $this->assertSame('45000', $exception->errorInfo[0] ?? null);
        }
    }

    private function assertCapabilityCleared(): void
    {
        $this->assertNull(DB::selectOne('SELECT @cari_cleanup_token AS token')->token);
        $this->assertSame(1, (int) DB::selectOne('SELECT IS_FREE_LOCK(?) AS available', ['lemon_cari_cleanup'])->available);
    }

    /** @param array<string, list<string>> $snapshot
     * @return array<string, list<string>>
     */
    private function protectedRows(array $snapshot): array
    {
        return array_diff_key($snapshot, array_flip([...self::TARGETS, 'cari_cleanup_operations']));
    }

    /** @return array<string, list<string>> */
    private function snapshotAllTables(): array
    {
        $snapshot = [];
        foreach (Schema::getTableListing(null, false) as $table) {
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
        app(PaymentService::class)->cancel($payment, 'Gerçek ters kayıt FK koruma testi', $this->admin);

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
