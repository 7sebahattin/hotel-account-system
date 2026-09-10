<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kasa vardiyası (oturumu) — bir personelin fiziksel kasadan sorumlu olduğu dönem.
 *
 * KARAR: İşletmede AYNI ANDA yalnızca BİR açık vardiya olabilir; kısıt kullanıcı
 * başına değil, işletme genelindedir. Gerekçe: fiziksel kasa tektir. İki açık
 * vardiya olsaydı "kasada şu an ne var" sorusunun tek bir doğru cevabı olmazdı
 * ve devir zinciri (X → Y → Z) kopardı.
 *
 * Kısıt veritabanında uygulanır: `is_open` kolonu açık vardiyada 1, kapalıda
 * NULL'dur ve UNIQUE index taşır. MySQL/MariaDB tekil indexte NULL'ları
 * tekrarlanabilir saydığı için bu, "en fazla bir açık vardiya" anlamına gelir.
 * Uygulama katmanı atlansa ya da iki istek aynı anda gelse bile ikinci açılış
 * veritabanı tarafından reddedilir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('opened_at');

            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();

            $table->string('status', 20)->default('open');

            /*
             * Yalnızca "tek açık vardiya" kısıtını taşımak için vardır:
             * açıkken 1, kapalıyken NULL. Durumun kendisi `status`'tadır.
             */
            $table->unsignedTinyInteger('is_open')->nullable()->default(1);

            $table->string('opening_note', 255)->nullable();
            $table->string('closing_note', 255)->nullable();

            $table->timestamps();

            $table->unique('is_open');
            $table->index('opened_by');
            $table->index('opened_at');
            $table->index('status');
        });

        DB::statement(
            "ALTER TABLE cash_sessions ADD CONSTRAINT cash_sessions_status_valid
             CHECK (status IN ('open', 'closed'))"
        );

        // `is_open` ile `status` birbirinden ayrı düşemez.
        DB::statement(
            "ALTER TABLE cash_sessions ADD CONSTRAINT cash_sessions_open_flag_consistent
             CHECK ((status = 'open'   AND is_open = 1    AND closed_at IS NULL AND closed_by IS NULL)
                 OR (status = 'closed' AND is_open IS NULL AND closed_at IS NOT NULL AND closed_by IS NOT NULL))"
        );

        DB::statement(
            'ALTER TABLE cash_sessions ADD CONSTRAINT cash_sessions_closed_after_opened
             CHECK (closed_at IS NULL OR closed_at >= opened_at)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_sessions');
    }
};
