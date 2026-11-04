<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Konaklamada "fatura kesildi / kesilmedi" TAKİP işareti (4 Kasım 2026).
 *
 * Bu bir fatura/e-Fatura entegrasyonu DEĞİLDİR (CLAUDE.md §3.2): yalnızca
 * kullanıcının elle işaretlediği bir bayraktır. Finansal alan değildir; cari
 * hareketi üretmez. NULL = fatura kesilmedi (mevcut kayıtların hepsi).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stays', function (Blueprint $table) {
            $table->timestamp('invoiced_at')->nullable();
            $table->foreignId('invoiced_by')->nullable()->constrained('users')->restrictOnDelete();
        });

        DB::statement(
            'ALTER TABLE stays ADD CONSTRAINT stays_invoice_pair_complete
             CHECK ((invoiced_at IS NULL AND invoiced_by IS NULL)
                 OR (invoiced_at IS NOT NULL AND invoiced_by IS NOT NULL))'
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE stays DROP CONSTRAINT stays_invoice_pair_complete');
        Schema::table('stays', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invoiced_by');
            $table->dropColumn('invoiced_at');
        });
    }
};
