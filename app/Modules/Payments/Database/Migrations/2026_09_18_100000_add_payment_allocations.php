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
        Schema::table('payments', function (Blueprint $table) {
            // NULL geçmiş kaydı belirtir; eski ödeyen veya dağılım tahmin edilmez.
            $table->enum('payer_type', ['account_holder', 'individual'])->nullable();
            $table->string('payer_name', 200)->nullable();
            $table->enum('allocation_mode', ['oldest_first', 'selected'])->nullable();
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->restrictOnDelete();
            $table->foreignId('stay_id')->constrained('stays')->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->foreignId('reverses_allocation_id')->nullable()->unique()->constrained('payment_allocations')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('reason', 255)->nullable();
            $table->timestamp('created_at');
            $table->index(['stay_id', 'reverses_allocation_id']);
            $table->index(['payment_id', 'reverses_allocation_id']);
        });
        DB::statement('ALTER TABLE payment_allocations ADD CONSTRAINT chk_allocation_amount CHECK (amount > 0)');
        DB::unprepared("CREATE TRIGGER payment_allocations_no_update BEFORE UPDATE ON payment_allocations FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Payment allocations are append-only'");
        DB::unprepared("CREATE TRIGGER payment_allocations_no_delete BEFORE DELETE ON payment_allocations FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Payment allocations are append-only'");
    }

    public function down(): void
    {
        if (DB::table('payment_allocations')->exists() || DB::table('payments')->whereNotNull('allocation_mode')->exists()) {
            throw new RuntimeException('Ödeme dağılımı verisi mevcut; veri koruyan ileri geçiş kullanın.');
        }
        DB::unprepared('DROP TRIGGER IF EXISTS payment_allocations_no_update');
        DB::unprepared('DROP TRIGGER IF EXISTS payment_allocations_no_delete');
        Schema::dropIfExists('payment_allocations');
        Schema::table('payments', fn (Blueprint $table) => $table->dropColumn(['payer_type', 'payer_name', 'allocation_mode']));
    }
};
