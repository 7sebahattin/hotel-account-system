<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bir devirde teslim edilen tutarlar — para birimi başına bir satır.
 *
 * Bir devir aynı anda birden fazla para birimi taşıyabilir (7.000 TRY + 300 EUR).
 * Para birimleri ASLA tek toplamda birleştirilmez (CLAUDE.md §6/L5); bu yüzden
 * tabloda "toplam" diye bir kolon yoktur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_transfer_amounts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cash_transfer_id')->constrained('cash_transfers')->restrictOnDelete();

            $table->char('currency', 3);
            $table->decimal('amount', 15, 2);

            $table->timestamp('created_at')->useCurrent();

            // Aynı devirde aynı para birimi iki kez yazılamaz.
            $table->unique(['cash_transfer_id', 'currency']);
        });

        DB::statement(
            'ALTER TABLE cash_transfer_amounts ADD CONSTRAINT cash_transfer_amounts_positive
             CHECK (amount > 0)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_transfer_amounts');
    }
};
