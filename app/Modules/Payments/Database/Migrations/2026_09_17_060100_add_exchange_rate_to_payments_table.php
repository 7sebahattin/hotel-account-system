<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * v2.1.1-C §2/§3 — yabancı para birimli tahsilatlar için kur ANLIK GÖRÜNTÜSÜ.
 *
 * `Stays` modülündeki 2026_09_17_060000 migration'ı ile AYNI gerekçe ve AYNI
 * üç CHECK deseni — bkz. o dosyanın yorumu. Tekrar EDİLMEDİ.
 *
 * Payment tarafında bu iki alan `Payment::IMMUTABLE_FIELDS`e BİLEREK
 * EKLENMEDİ: normal düzenleme yolu (`UpdatePaymentRequest`/`updateMetadata()`)
 * zaten yalnızca `reference_no`/`description`'ı bilir, bu iki alana hiç
 * dokunmaz — model seviyesinde ayrıca kilitlemek gereksiz bir katman olurdu.
 * Tek yazma yolu `create()` (ilk giriş) ve `correctHistoricalExchangeRate()`
 * (yalnızca eksikse, tek seferlik) — CLAUDE.md §13 "gereksiz soyutlama
 * yasağı".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('exchange_rate_to_try', 10, 4)->nullable()->after('currency');
            $table->decimal('try_equivalent', 15, 2)->nullable()->after('exchange_rate_to_try');
        });

        DB::statement(
            "ALTER TABLE payments ADD CONSTRAINT payments_try_has_no_exchange_rate
             CHECK (currency <> 'TRY' OR (exchange_rate_to_try IS NULL AND try_equivalent IS NULL))"
        );

        DB::statement(
            'ALTER TABLE payments ADD CONSTRAINT payments_exchange_rate_pair_complete
             CHECK ((exchange_rate_to_try IS NULL AND try_equivalent IS NULL)
                 OR (exchange_rate_to_try IS NOT NULL AND try_equivalent IS NOT NULL))'
        );

        DB::statement(
            'ALTER TABLE payments ADD CONSTRAINT payments_exchange_rate_positive
             CHECK (exchange_rate_to_try IS NULL OR exchange_rate_to_try > 0)'
        );

        DB::statement(
            'ALTER TABLE payments ADD CONSTRAINT payments_try_equivalent_positive
             CHECK (try_equivalent IS NULL OR try_equivalent > 0)'
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE payments DROP CONSTRAINT payments_try_equivalent_positive');
        DB::statement('ALTER TABLE payments DROP CONSTRAINT payments_exchange_rate_positive');
        DB::statement('ALTER TABLE payments DROP CONSTRAINT payments_exchange_rate_pair_complete');
        DB::statement('ALTER TABLE payments DROP CONSTRAINT payments_try_has_no_exchange_rate');

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['exchange_rate_to_try', 'try_equivalent']);
        });
    }
};
