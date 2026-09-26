<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = [
        'personnel_payroll_entries', 'personnel_attendance_corrections', 'personnel_scan_events',
        'personnel_terminals', 'personnel_card_assignments', 'personnel_cards',
        'personnel_wage_assignments', 'personnel_shift_assignments', 'personnel_shift_versions',
        'personnel_shifts', 'personnel_employees',
    ];

    private const IMMUTABLE = [
        'personnel_shift_versions', 'personnel_shift_assignments', 'personnel_wage_assignments',
        'personnel_scan_events', 'personnel_attendance_corrections', 'personnel_payroll_entries',
    ];

    public function up(): void
    {
        Schema::create('personnel_employees', function (Blueprint $table): void {
            $table->id();
            $table->string('employee_code', 30)->unique();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('position', 100)->nullable();
            $table->string('department', 100)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['is_active', 'department']);
        });

        Schema::create('personnel_shifts', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('personnel_shift_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shift_id')->constrained('personnel_shifts')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('ends_next_day');
            $table->unsignedInteger('break_minutes');
            $table->json('workdays');
            $table->unsignedInteger('late_grace_minutes')->default(0);
            $table->unsignedInteger('early_grace_minutes')->default(0);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');
            $table->unique(['shift_id', 'version']);
        });

        Schema::create('personnel_shift_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('personnel_employees')->restrictOnDelete();
            $table->foreignId('shift_version_id')->constrained('personnel_shift_versions')->restrictOnDelete();
            $table->date('effective_from');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');
            $table->unique(['employee_id', 'effective_from']);
        });

        Schema::create('personnel_wage_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('personnel_employees')->restrictOnDelete();
            $table->date('effective_from');
            $table->string('wage_type', 10);
            $table->decimal('amount', 15, 2);
            $table->char('currency', 3);
            $table->unsignedInteger('standard_daily_minutes')->default(480);
            $table->unsignedInteger('overtime_multiplier_bp')->default(15000);
            $table->boolean('overtime_enabled')->default(false);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');
            $table->unique(['employee_id', 'effective_from']);
        });

        Schema::create('personnel_cards', function (Blueprint $table): void {
            $table->id();
            $table->string('uid', 40)->charset('ascii')->collation('ascii_bin')->unique();
            $table->string('label', 100)->nullable();
            $table->string('technology', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('personnel_card_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('card_id')->constrained('personnel_cards')->restrictOnDelete();
            $table->foreignId('employee_id')->constrained('personnel_employees')->restrictOnDelete();
            $table->dateTime('assigned_at');
            $table->dateTime('released_at')->nullable();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('released_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');
            $table->unsignedBigInteger('active_card_id')->nullable()->storedAs('case when `released_at` is null then `card_id` else NULL end');
            $table->unique('active_card_id');
            $table->index(['card_id', 'assigned_at', 'released_at'], 'personnel_card_assignment_time_idx');
        });

        Schema::create('personnel_terminals', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('platform', 20);
            $table->uuid('device_uuid');
            $table->char('token_hash', 64)->unique();
            $table->string('token_prefix', 12);
            $table->dateTime('revoked_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');
            $table->dateTime('last_seen_at')->nullable();
        });

        Schema::create('personnel_scan_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('request_id')->unique();
            $table->char('payload_hash', 64);
            $table->foreignId('card_id')->nullable()->constrained('personnel_cards')->restrictOnDelete();
            $table->foreignId('card_assignment_id')->nullable()->constrained('personnel_card_assignments')->restrictOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('personnel_employees')->restrictOnDelete();
            $table->foreignId('terminal_id')->nullable()->constrained('personnel_terminals')->restrictOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('source', 20);
            $table->string('platform', 20);
            $table->uuid('device_uuid')->nullable();
            $table->string('technology', 100)->nullable();
            $table->dateTime('occurred_at');
            $table->string('requested_action', 10);
            $table->string('action', 10)->nullable();
            $table->string('result', 30);
            $table->foreignId('duplicate_of_id')->nullable()->constrained('personnel_scan_events')->restrictOnDelete();
            $table->timestamp('created_at');
            $table->index(['employee_id', 'occurred_at', 'id']);
            $table->index(['card_id', 'occurred_at', 'id']);
        });

        Schema::create('personnel_attendance_corrections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('personnel_employees')->restrictOnDelete();
            $table->date('work_date');
            $table->unsignedInteger('revision');
            $table->foreignId('supersedes_id')->nullable()->unique()->constrained('personnel_attendance_corrections')->restrictOnDelete();
            $table->unsignedBigInteger('scan_watermark_id')->nullable();
            $table->json('sessions');
            $table->string('reason', 500);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');
            $table->unique(['employee_id', 'work_date', 'revision'], 'personnel_correction_revision_unique');
        });

        Schema::create('personnel_payroll_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('personnel_employees')->restrictOnDelete();
            $table->date('entry_date');
            $table->char('period', 7);
            $table->string('type', 20);
            $table->string('direction', 10);
            $table->decimal('amount', 15, 2);
            $table->char('currency', 3);
            $table->string('payment_method', 20)->nullable();
            $table->string('description', 500)->nullable();
            $table->string('source_key', 120)->nullable()->unique();
            $table->json('calculation_snapshot')->nullable();
            $table->foreignId('reverses_entry_id')->nullable()->unique()->constrained('personnel_payroll_entries')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');
            $table->index(['employee_id', 'currency', 'entry_date']);
            $table->index(['period', 'type']);
        });

        foreach (self::IMMUTABLE as $table) {
            foreach (['UPDATE', 'DELETE'] as $operation) {
                DB::unprepared("CREATE TRIGGER {$table}_block_".strtolower($operation)." BEFORE {$operation} ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Personel gecmis kaydi degistirilemez'");
            }
        }

        DB::unprepared("CREATE TRIGGER personnel_card_assignments_guard_update BEFORE UPDATE ON personnel_card_assignments FOR EACH ROW BEGIN IF OLD.released_at IS NOT NULL OR NEW.released_at IS NULL OR OLD.card_id <> NEW.card_id OR OLD.employee_id <> NEW.employee_id OR OLD.assigned_at <> NEW.assigned_at OR OLD.assigned_by <> NEW.assigned_by OR OLD.created_at <> NEW.created_at OR NEW.released_by IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Kart atamasi yalniz bir kez sonlandirilabilir'; END IF; END");
        DB::unprepared("CREATE TRIGGER personnel_card_assignments_block_delete BEFORE DELETE ON personnel_card_assignments FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Kart atamasi silinemez'");
        DB::unprepared("CREATE TRIGGER personnel_cards_guard_uid BEFORE UPDATE ON personnel_cards FOR EACH ROW BEGIN IF OLD.uid <> NEW.uid THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Kart UID degistirilemez'; END IF; END");
        foreach ([
            'ALTER TABLE personnel_employees ADD CONSTRAINT personnel_employee_dates_chk CHECK (end_date IS NULL OR end_date >= start_date)',
            'ALTER TABLE personnel_shift_versions ADD CONSTRAINT personnel_shift_duration_chk CHECK (version > 0 AND (TIME_TO_SEC(end_time) - TIME_TO_SEC(start_time) + IF(ends_next_day, 86400, 0)) BETWEEN 60 AND 86400 AND break_minutes * 60 < (TIME_TO_SEC(end_time) - TIME_TO_SEC(start_time) + IF(ends_next_day, 86400, 0)))',
            "ALTER TABLE personnel_wage_assignments ADD CONSTRAINT personnel_wage_values_chk CHECK (amount > 0 AND wage_type IN ('monthly','daily','hourly') AND standard_daily_minutes BETWEEN 1 AND 1440 AND overtime_multiplier_bp <= 100000)",
            "ALTER TABLE personnel_payroll_entries ADD CONSTRAINT personnel_payroll_values_chk CHECK (amount > 0 AND type IN ('earning','bonus','deduction','payment','reversal') AND direction IN ('credit','debit') AND (type IN ('reversal','earning','bonus') OR direction = 'debit') AND (type IN ('reversal','deduction','payment') OR direction = 'credit') AND (type <> 'payment' OR (payment_method IS NOT NULL AND payment_method IN ('cash','bank_transfer','other'))) AND (type <> 'reversal' OR reverses_entry_id IS NOT NULL))",
        ] as $sql) {
            DB::statement($sql);
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException('Personel verisi bulunan migration geri alınamaz.');
            }
        }

        foreach (self::IMMUTABLE as $table) {
            foreach (['update', 'delete'] as $operation) {
                DB::unprepared("DROP TRIGGER IF EXISTS {$table}_block_{$operation}");
            }
        }
        DB::unprepared('DROP TRIGGER IF EXISTS personnel_card_assignments_guard_update');
        DB::unprepared('DROP TRIGGER IF EXISTS personnel_card_assignments_block_delete');
        DB::unprepared('DROP TRIGGER IF EXISTS personnel_cards_guard_uid');

        foreach (self::TABLES as $table) {
            Schema::dropIfExists($table);
        }
    }
};
