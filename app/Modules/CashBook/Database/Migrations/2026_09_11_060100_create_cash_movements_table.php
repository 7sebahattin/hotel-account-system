<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fiziksel kasa hareketi.
 *
 * KARAR 1 — tutar `DECIMAL(15,2)`'dir, `amount_minor` değil. CLAUDE.md §5 ve §13
 * bağlayıcıdır: veritabanında para DECIMAL(15,2) tutulur, tam sayı kuruş
 * aritmetiği PHP tarafında `Core\Support\Money` ile yapılır. Kasa için ayrı bir
 * saklama birimi kullanmak, aynı uygulamada iki para konvansiyonu demek olurdu.
 *
 * KARAR 2 — `type` ile `direction` AYRI kolonlardır ve tutar her zaman POZİTİFtir.
 * Yön tipin doğal yönüdür (gelir → in, gider/çıkış → out); ters kayıtta tam
 * tersine döner. Böylece hem beklenen kasa (Σ işaretli tutar) hem de gelir-gider
 * raporu (yalnızca gelir/gider tipleri) ters kayıtları doğru sayar — açıklama
 * metnine veya negatif tutara bakmaya gerek kalmaz.
 *
 * KARAR 3 — açılış bakiyesi ayrı bir tabloda DEĞİL, `opening` tipli bir hareket
 * olarak tutulur. Beklenen kasa tek bir formülle hesaplanır ve para için iki
 * kaynak (tablo) olmaz. Ledger modülünde de `source_type = 'opening'` aynı
 * amaçla kullanılabilir durumdadır.
 *
 * Kayıt append-only'dir: düzeltme ters hareketle yapılır (bkz. sonraki migration).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cash_session_id')->constrained('cash_sessions')->restrictOnDelete();

            $table->string('type', 20);
            $table->char('direction', 3);
            $table->decimal('amount', 15, 2);
            $table->char('currency', 3);

            $table->timestamp('entry_at');
            $table->string('description', 255)->nullable();

            // Ara kasa / patron / banka teslimlerinde parayı teslim alan taraf.
            $table->string('counterparty', 150)->nullable();

            /*
             * İleride PMS/Payments bağlanabilsin diye açık bırakılmıştır; V1'de
             * uygulama tarafından DOLDURULMAZ (CLAUDE.md §3.2: kullanılmayan
             * kolon açmak yasaktır — bu kolon yasağın istisnası değil, §3.3'teki
             * "kaynak izleme" desenidir: ledger_entries.source_type ile aynı rol).
             */
            $table->string('reference_type', 30)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();

            $table->boolean('is_reversal')->default(false);
            $table->foreignId('reverses_movement_id')->nullable()
                ->constrained('cash_movements')->restrictOnDelete();

            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();

            // Değiştirilemez kayıt: updated_at ve deleted_at YOKTUR.
            $table->timestamp('created_at')->useCurrent();

            $table->index(['cash_session_id', 'entry_at']);
            $table->index(['cash_session_id', 'currency']);
            $table->index(['type', 'entry_at']);
            $table->index('entry_at');
            $table->index('currency');
            $table->index('created_by');
            $table->index(['reference_type', 'reference_id']);

            // Bir hareket yalnızca BİR kez ters kaydedilebilir (NULL'lar tekrarlanır).
            $table->unique('reverses_movement_id');
        });

        DB::statement(
            "ALTER TABLE cash_movements ADD CONSTRAINT cash_movements_type_valid
             CHECK (type IN ('opening', 'room_income', 'extra_income', 'expense', 'cash_out', 'cash_in'))"
        );

        DB::statement(
            "ALTER TABLE cash_movements ADD CONSTRAINT cash_movements_direction_valid
             CHECK (direction IN ('in', 'out'))"
        );

        DB::statement(
            'ALTER TABLE cash_movements ADD CONSTRAINT cash_movements_amount_positive
             CHECK (amount > 0)'
        );

        /*
         * Yön tipin doğal yönüdür; ters kayıtta tam tersidir. Bu kısıt olmadan
         * "gelir ama kasadan çıkan" gibi anlamsız bir satır yazılabilirdi.
         */
        DB::statement(
            "ALTER TABLE cash_movements ADD CONSTRAINT cash_movements_direction_matches_type
             CHECK (
                 (is_reversal = 0 AND (
                     (type IN ('opening', 'room_income', 'extra_income', 'cash_in') AND direction = 'in')
                  OR (type IN ('expense', 'cash_out') AND direction = 'out')))
              OR (is_reversal = 1 AND (
                     (type IN ('opening', 'room_income', 'extra_income', 'cash_in') AND direction = 'out')
                  OR (type IN ('expense', 'cash_out') AND direction = 'in')))
             )"
        );

        // Ters kayıt her zaman bir hareketi işaret eder; işaret eden her kayıt ters kayıttır.
        DB::statement(
            'ALTER TABLE cash_movements ADD CONSTRAINT cash_movements_reversal_consistent
             CHECK ((is_reversal = 0 AND reverses_movement_id IS NULL)
                 OR (is_reversal = 1 AND reverses_movement_id IS NOT NULL))'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
    }
};
