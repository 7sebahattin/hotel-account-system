<?php

declare(strict_types=1);

namespace Tests\Feature\CariCleanup;

use App\Models\User;
use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Companies\Models\Company;
use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
use App\Modules\Ledger\Services\LedgerService;
use App\Modules\Payments\Services\PaymentService;
use App\Modules\Rooms\Models\Room;
use App\Modules\Stays\Models\Stay;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Uses an independently migrated MySQL/MariaDB schema. No DDL or schema reset
 * runs inside these rollback-only tests; migration preservation is also checked
 * separately against populated disposable schemas outside any transaction.
 */
class CariCleanupRetirementTest extends TestCase
{
    use DatabaseTransactions;

    private const URLS = [
        '/admin/cari-temizleme',
        '/admin/cari-temizleme/on-kontrol',
        '/admin/cari-temizleme/sil',
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
    public function retired_routes_are_absent_for_every_method_and_preserve_all_existing_rows(): void
    {
        $this->seedRepresentativeRecords();
        foreach (['companies', 'monthly_account_revisions', 'personnel_payroll_entries', 'cash_movements', 'audit_logs'] as $table) {
            $this->assertGreaterThan(0, DB::table($table)->count(), $table.' must contain real preservation fixtures.');
        }
        $before = $this->snapshotAllTables();
        foreach (self::URLS as $url) {
            foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'] as $method) {
                $this->call($method, $url, [
                    'password' => 'password', 'confirmation' => 'CARİ GİRİŞLERİNİ SİL',
                    'preflight_token' => 'previously-issued-token', 'confirm_irreversible' => '1',
                ])->assertNotFound();
            }
        }
        $this->assertSame($before, $this->snapshotAllTables());
    }

    #[Test]
    public function retired_route_names_and_sidebar_entry_are_removed_while_admin_dashboard_works(): void
    {
        foreach (['cari-cleanup.index', 'cari-cleanup.preflight', 'cari-cleanup.execute'] as $name) {
            $this->assertFalse(Route::has($name), $name);
        }
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $this->assertStringNotContainsString('cari-temizleme', $route->uri());
        }
        $before = $this->snapshotAllTables();
        $this->get(route('home'))->assertOk()
            ->assertDontSee('/admin/cari-temizleme', false)
            ->assertDontSee('Cari Veri Temizleme')
            ->assertDontSee('CARİ GİRİŞLERİNİ SİL');
        $this->assertSame($before, $this->snapshotAllTables());
    }

    #[Test]
    public function retired_urls_are_not_available_to_guests_or_other_roles(): void
    {
        foreach ([null, $this->userWithRole(RoleName::Patron), $this->userWithRole(RoleName::Personel)] as $user) {
            if ($user === null) {
                auth()->forgetUser();
            } else {
                $this->actingAs($user);
            }
            foreach (self::URLS as $url) {
                $this->get($url)->assertNotFound();
                $this->post($url)->assertNotFound();
            }
        }
    }

    #[Test]
    public function ordinary_financial_delete_and_update_are_still_blocked(): void
    {
        $this->seedRepresentativeRecords();
        $before = $this->snapshotAllTables();
        foreach (['ledger_entries' => 'description', 'payment_allocations' => 'reason'] as $table => $column) {
            $id = DB::table($table)->max('id');
            $this->assertNotNull($id);
            $this->assertImmutableSql(fn () => DB::table($table)->where('id', $id)->delete());
            $this->assertImmutableSql(fn () => DB::table($table)->where('id', $id)->update([$column => 'Forbidden change']));
        }
        $this->assertSame($before, $this->snapshotAllTables());
    }

    #[Test]
    public function even_a_fully_matching_old_capability_cannot_delete_financial_records(): void
    {
        $this->seedRepresentativeRecords();
        $this->assertTrue($this->admin->is_active);
        $this->assertTrue($this->admin->hasRole(RoleName::Admin->value));
        $token = Str::random(64);
        DB::table('cari_cleanup_operations')->where('id', 1)->update([
            'status' => 'running', 'actor_id' => $this->admin->id,
            'connection_id' => (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id,
            'nonce_hash' => hash('sha256', $token), 'expires_at' => now('UTC')->addMinutes(10),
            'completed_at' => null,
        ]);
        DB::statement('SET @cari_cleanup_token = ?', [$token]);
        try {
            $valid = DB::selectOne(
                "SELECT connection_id = CONNECTION_ID() AS same_connection, BINARY nonce_hash = BINARY SHA2(@cari_cleanup_token, 256) AS same_nonce, expires_at >= UTC_TIMESTAMP() AS unexpired FROM cari_cleanup_operations WHERE id = 1 AND status = 'running' AND completed_at IS NULL",
            );
            $this->assertNotNull($valid);
            $this->assertSame([1, 1, 1], [(int) $valid->same_connection, (int) $valid->same_nonce, (int) $valid->unexpired]);
            $before = $this->snapshotAllTables();
            foreach (['ledger_entries', 'payment_allocations'] as $table) {
                $this->assertImmutableSql(fn () => DB::table($table)->where('id', DB::table($table)->max('id'))->delete());
            }
            $this->assertSame($before, $this->snapshotAllTables());
        } finally {
            DB::statement('SET @cari_cleanup_token = NULL');
        }
        $this->assertNull(DB::selectOne('SELECT @cari_cleanup_token AS token')->token);
    }

    #[Test]
    public function financial_trigger_bodies_are_unconditional_and_original_update_guards_remain(): void
    {
        $expected = [
            'ledger_entries_block_delete' => ['ledger_entries', 'DELETE', 'ledger_entries kayitlari silinemez; duzeltme ters kayit ile yapilir.'],
            'ledger_entries_block_update' => ['ledger_entries', 'UPDATE', 'ledger_entries kayitlari degistirilemez; duzeltme ters kayit ile yapilir.'],
            'payment_allocations_no_delete' => ['payment_allocations', 'DELETE', 'Payment allocations are append-only'],
            'payment_allocations_no_update' => ['payment_allocations', 'UPDATE', 'Payment allocations are append-only'],
        ];
        foreach ($expected as $name => [$table, $event, $message]) {
            $trigger = DB::selectOne('SELECT EVENT_OBJECT_TABLE AS table_name, EVENT_MANIPULATION AS event_name, ACTION_TIMING AS timing, ACTION_STATEMENT AS body FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = ? AND TRIGGER_NAME = ?', [DB::connection()->getDatabaseName(), $name]);
            $this->assertNotNull($trigger, $name);
            $this->assertSame($table, $trigger->table_name);
            $this->assertSame($event, $trigger->event_name);
            $this->assertSame('BEFORE', $trigger->timing);
            $this->assertSame("SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '".$message."'", preg_replace('/\s+/', ' ', trim($trigger->body, " \t\n\r\0\x0B;")));
        }
        $triggers = DB::select('SELECT ACTION_STATEMENT AS body FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = ?', [DB::connection()->getDatabaseName()]);
        foreach ($triggers as $trigger) {
            $this->assertStringNotContainsString('LEMON_CARI_CLEANUP_V1', $trigger->body);
            $this->assertStringNotContainsString('@cari_cleanup_token', $trigger->body);
        }
        $this->assertDatabaseHas('migrations', ['migration' => '2026_10_12_100000_add_one_time_cari_cleanup_capability']);
        $this->assertDatabaseHas('migrations', ['migration' => '2026_10_13_100000_retire_one_time_cari_cleanup']);
    }

    #[Test]
    public function completed_operation_and_exact_report_remain_immutable_and_unreachable(): void
    {
        $report = json_encode([
            'completed_at' => '13.10.2026 10:15:00', 'actor_id' => $this->admin->id,
            'deleted' => ['ledger_entries' => 17, 'payment_allocations' => 8],
            'protected' => ['companies' => ['count' => 3, 'sha256' => str_repeat('a', 64)]],
            'integrity_verified' => true, 'note' => "Tarihsel rapor; Türkçe İ ı ş\nİkinci satır",
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        DB::table('cari_cleanup_operations')->where('id', 1)->update([
            'status' => 'completed', 'actor_id' => $this->admin->id,
            'completed_at' => now(), 'report' => $report,
        ]);
        $before = $this->snapshotAllTables();
        $this->assertImmutableSql(fn () => DB::table('cari_cleanup_operations')->where('id', 1)->update(['status' => 'ready']));
        $this->assertImmutableSql(fn () => DB::table('cari_cleanup_operations')->where('id', 1)->update(['report' => '{}']));
        $this->assertImmutableSql(fn () => DB::table('cari_cleanup_operations')->where('id', 1)->delete());
        foreach (self::URLS as $url) {
            $this->get($url)->assertNotFound();
            $this->post($url)->assertNotFound();
        }
        $this->assertSame($report, DB::table('cari_cleanup_operations')->where('id', 1)->value('report'));
        $this->assertSame($before, $this->snapshotAllTables());
    }

    #[Test]
    public function retained_ready_operation_cannot_be_deleted_or_moved_to_another_singleton_id(): void
    {
        $state = DB::table('cari_cleanup_operations')->where('id', 1)->first();
        $this->assertNotNull($state);
        $this->assertSame('ready', $state->status);
        $before = $this->snapshotAllTables();
        $this->assertImmutableSql(fn () => DB::table('cari_cleanup_operations')->where('id', 1)->delete());
        $this->assertImmutableSql(fn () => DB::table('cari_cleanup_operations')->where('id', 1)->update(['id' => 2]));
        $this->assertSame($before, $this->snapshotAllTables());
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

    /** @return array<string, list<string>> */
    private function snapshotAllTables(): array
    {
        $snapshot = [];
        foreach (Schema::getTableListing(DB::connection()->getDatabaseName(), false) as $table) {
            $rows = DB::table($table)->get()->map(function (object $row): string {
                $values = (array) $row;
                ksort($values);

                return serialize($values);
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
