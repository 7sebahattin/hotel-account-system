<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tahsilat çöp kutusu (4 Kasım 2026, kullanıcı talimatı).
 *
 * `SoftDeletes` DEĞİL, açık `trashed_at/trashed_by` kolonlarıdır: tahsilat
 * tablosunda bilerek `deleted_at` yoktur (CLAUDE.md §5.4) ve çöpe taşıma
 * kaydı sorgulardan gizlemekten ibarettir — cari hareketine, kasaya veya
 * ters kayda DOKUNMAZ. Yalnızca İPTAL EDİLMİŞ tahsilat çöpe taşınabilir
 * (aktif tahsilatın cari etkisi sürdüğü için); bu kural DB CHECK ile de
 * zorlanır. Eski kayıtlar NULL kalır, geçmiş değişmez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->timestamp('trashed_at')->nullable();
            $table->foreignId('trashed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->index('trashed_at');
        });

        DB::statement(
            "ALTER TABLE payments ADD CONSTRAINT payments_trash_requires_cancelled
             CHECK (trashed_at IS NULL OR status = 'cancelled')"
        );
        DB::statement(
            'ALTER TABLE payments ADD CONSTRAINT payments_trash_pair_complete
             CHECK ((trashed_at IS NULL AND trashed_by IS NULL)
                 OR (trashed_at IS NOT NULL AND trashed_by IS NOT NULL))'
        );
    }

    public function down(): void
    {
        if (DB::table('payments')->whereNotNull('trashed_at')->exists()) {
            throw new RuntimeException('Çöp kutusunda tahsilat var; veri koruyan ileri geçiş kullanın.');
        }

        DB::statement('ALTER TABLE payments DROP CONSTRAINT payments_trash_pair_complete');
        DB::statement('ALTER TABLE payments DROP CONSTRAINT payments_trash_requires_cancelled');
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('trashed_by');
            $table->dropIndex(['trashed_at']);
            $table->dropColumn('trashed_at');
        });
    }
};
