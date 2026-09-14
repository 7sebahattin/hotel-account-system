<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_account_days', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->collation = 'utf8mb4_turkish_ci';
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->date('account_date');
            $table->unsignedInteger('current_version')->default(0);
            $table->string('status', 10)->default('active');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['owner_id', 'account_date']);
        });

        Schema::create('monthly_account_revisions', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->collation = 'utf8mb4_turkish_ci';
            $table->id();
            $table->foreignId('day_id')->constrained('monthly_account_days')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('status', 10)->default('active');
            $table->decimal('daily_total', 15, 2);
            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['day_id', 'version']);
        });

        Schema::create('monthly_account_items', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->collation = 'utf8mb4_turkish_ci';
            $table->id();
            $table->foreignId('revision_id')->constrained('monthly_account_revisions')->restrictOnDelete();
            $table->string('method', 20);
            $table->char('currency', 3);
            $table->string('bank_name', 100)->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('conversion_mode', 5)->nullable();
            $table->decimal('exchange_rate_to_try', 10, 4)->nullable();
            $table->decimal('try_equivalent', 15, 2);
            $table->string('note', 1000)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        DB::statement("ALTER TABLE monthly_account_days ADD CONSTRAINT monthly_days_status_check CHECK (status IN ('active', 'deleted'))");
        DB::statement("ALTER TABLE monthly_account_revisions ADD CONSTRAINT monthly_revisions_values_check CHECK (version > 0 AND daily_total >= 0 AND status IN ('active', 'deleted'))");
        DB::statement("ALTER TABLE monthly_account_items ADD CONSTRAINT monthly_items_values_check CHECK (amount > 0 AND try_equivalent >= 0 AND currency IN ('TRY', 'EUR', 'USD', 'GBP', 'RUB', 'GEL') AND method IN ('cash', 'credit_card', 'link', 'bank_transfer', 'extra'))");
        DB::statement("ALTER TABLE monthly_account_items ADD CONSTRAINT monthly_items_bank_check CHECK (method NOT IN ('credit_card', 'link', 'bank_transfer') OR (bank_name IS NOT NULL AND CHAR_LENGTH(TRIM(bank_name)) > 0))");
        DB::statement("ALTER TABLE monthly_account_items ADD CONSTRAINT monthly_items_conversion_check CHECK ((currency = 'TRY' AND conversion_mode IS NULL AND exchange_rate_to_try IS NULL AND try_equivalent = amount) OR (currency <> 'TRY' AND conversion_mode IS NOT NULL AND conversion_mode IN ('rate', 'total') AND exchange_rate_to_try IS NOT NULL AND exchange_rate_to_try > 0))");

        foreach (['monthly_account_revisions', 'monthly_account_items'] as $table) {
            foreach (['UPDATE', 'DELETE'] as $operation) {
                $name = $table.'_no_'.strtolower($operation);
                DB::unprepared("CREATE TRIGGER {$name} BEFORE {$operation} ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Monthly account history is immutable'");
            }
        }

        DB::unprepared("CREATE TRIGGER monthly_account_days_no_delete BEFORE DELETE ON monthly_account_days FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Monthly account days cannot be deleted'");
        DB::unprepared("CREATE TRIGGER monthly_account_days_identity BEFORE UPDATE ON monthly_account_days FOR EACH ROW BEGIN IF NEW.owner_id <> OLD.owner_id OR NEW.account_date <> OLD.account_date THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Monthly account owner and date are immutable'; END IF; END");
    }

    public function down(): void
    {
        if (Schema::hasTable('monthly_account_days') && DB::table('monthly_account_days')->exists()) {
            throw new RuntimeException('Aylık hesap kayıtları varken finansal geçmişi kaldıran rollback yapılamaz.');
        }

        foreach (['monthly_account_revisions', 'monthly_account_items'] as $table) {
            foreach (['update', 'delete'] as $operation) {
                DB::unprepared("DROP TRIGGER IF EXISTS {$table}_no_{$operation}");
            }
        }
        DB::unprepared('DROP TRIGGER IF EXISTS monthly_account_days_no_delete');
        DB::unprepared('DROP TRIGGER IF EXISTS monthly_account_days_identity');

        Schema::dropIfExists('monthly_account_items');
        Schema::dropIfExists('monthly_account_revisions');
        Schema::dropIfExists('monthly_account_days');
    }
};
