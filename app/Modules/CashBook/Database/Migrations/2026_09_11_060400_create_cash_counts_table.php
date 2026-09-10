<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vardiya kapanışındaki fiziksel kasa sayımı — para birimi başına bir satır.
 *
 * `expected_amount` kapanış anındaki hesaplanan tutarın ANLIK GÖRÜNTÜSÜdür;
 * `counted_amount` personelin fiilen saydığıdır. Fark ikisinin çıkarımıdır ve
 * saklanmaz: her iki kaynak da değişmez olduğu için fark her zaman aynı çıkar,
 * saklamak üçüncü bir doğruluk kaynağı yaratırdı.
 *
 * Farklar para birimi bazında ayrı tutulur ve ASLA birbirine çevrilmez (§6/L5).
 * `expected_amount` negatif olabilir (hatalı giriş sonrası düzeltme durumu);
 * bu yüzden yalnızca sayılan tutar için negatiflik yasağı vardır.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_counts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cash_session_id')->constrained('cash_sessions')->restrictOnDelete();

            $table->char('currency', 3);
            $table->decimal('expected_amount', 15, 2);
            $table->decimal('counted_amount', 15, 2);

            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            // Bir vardiyada bir para birimi yalnızca bir kez sayılır.
            $table->unique(['cash_session_id', 'currency']);
        });

        DB::statement(
            'ALTER TABLE cash_counts ADD CONSTRAINT cash_counts_counted_not_negative
             CHECK (counted_amount >= 0)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_counts');
    }
};
