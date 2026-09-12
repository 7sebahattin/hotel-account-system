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
        Schema::table('payments', fn (Blueprint $table) => $table->enum('settlement_currency', ['TRY'])->nullable());
        Schema::table('payment_allocations', function (Blueprint $table) {
            $table->decimal('payment_amount', 15, 2)->nullable();
            $table->enum('debt_currency', ['TRY'])->nullable();
        });
        DB::statement('ALTER TABLE payment_allocations ADD CONSTRAINT chk_allocation_conversion CHECK ((payment_amount IS NULL AND debt_currency IS NULL) OR (payment_amount IS NOT NULL AND payment_amount > 0 AND debt_currency IS NOT NULL))');
    }

    public function down(): void
    {
        if (DB::table('payments')->whereNotNull('settlement_currency')->exists() || DB::table('payment_allocations')->whereNotNull('payment_amount')->exists()) {
            throw new RuntimeException('Döviz kapama kayıtları mevcut; veri koruyan ileri geçiş kullanın.');
        }
        DB::statement('ALTER TABLE payment_allocations DROP CONSTRAINT chk_allocation_conversion');
        Schema::table('payment_allocations', fn (Blueprint $table) => $table->dropColumn(['payment_amount', 'debt_currency']));
        Schema::table('payments', fn (Blueprint $table) => $table->dropColumn('settlement_currency'));
    }
};
