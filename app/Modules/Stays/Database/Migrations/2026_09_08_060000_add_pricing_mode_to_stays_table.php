<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Konaklama ücreti iki şekilde girilebilir: toplam tutar veya günlük ücret.
 *
 * `amount` her iki modda da NİHAİ TOPLAM tutardır; cari hareketine yazılan
 * değer odur. `daily_rate` yalnızca daily modunda doludur.
 *
 * Ayrıca 0 gece kararı geri alınır: aynı gün giriş/çıkış artık kabul edilmez,
 * asgari gece sayısı 1'dir. Günübirlik kullanım ileride ayrı bir iş kuralı
 * olarak tasarlanacaktır (CLAUDE.md §3.2 — PMS kapsam dışı).
 *
 * NOT: Tabloda 0 geceli kayıt varsa aşağıdaki CHECK'ler eklenirken veritabanı
 * hata verir. Bu bilinçlidir — veri sessizce kabul edilmez, önce düzeltilir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stays', function (Blueprint $table) {
            $table->string('pricing_mode', 10)->default('total')->after('amount');
            $table->decimal('daily_rate', 15, 2)->nullable()->after('pricing_mode');
        });

        DB::statement(
            "ALTER TABLE stays ADD CONSTRAINT stays_pricing_mode_valid
             CHECK (pricing_mode IN ('total', 'daily'))"
        );

        // Günlük ücret yalnızca daily modunda dolu; total modunda daima NULL.
        DB::statement(
            "ALTER TABLE stays ADD CONSTRAINT stays_pricing_mode_consistent
             CHECK ((pricing_mode = 'total' AND daily_rate IS NULL)
                 OR (pricing_mode = 'daily' AND daily_rate IS NOT NULL AND daily_rate > 0))"
        );

        DB::statement('ALTER TABLE stays DROP CONSTRAINT stays_date_range');

        DB::statement(
            'ALTER TABLE stays ADD CONSTRAINT stays_date_range CHECK (check_out_date > check_in_date)'
        );

        DB::statement(
            'ALTER TABLE stays ADD CONSTRAINT stays_nights_positive CHECK (nights >= 1)'
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE stays DROP CONSTRAINT stays_nights_positive');
        DB::statement('ALTER TABLE stays DROP CONSTRAINT stays_date_range');
        DB::statement(
            'ALTER TABLE stays ADD CONSTRAINT stays_date_range CHECK (check_out_date >= check_in_date)'
        );
        DB::statement('ALTER TABLE stays DROP CONSTRAINT stays_pricing_mode_consistent');
        DB::statement('ALTER TABLE stays DROP CONSTRAINT stays_pricing_mode_valid');

        Schema::table('stays', function (Blueprint $table) {
            $table->dropColumn(['pricing_mode', 'daily_rate']);
        });
    }
};
