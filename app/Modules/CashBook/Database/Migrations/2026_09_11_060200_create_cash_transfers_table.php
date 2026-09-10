<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Personelden personele kasa devri.
 *
 * KARAR — bu tablo YALNIZCA personel devri içindir. Ara kasa / patron / banka
 * teslimi burada TUTULMAZ; o bir `cash_out` HAREKETİdir (kullanıcı talimatı §6:
 * "Bu ayrı 'cash transfer out' hareketidir"). Gerekçe: ara kasa teslimi tek
 * taraflıdır ve anında olur; personel devri ise İKİ TARAFLI onay ister ve iki
 * vardiyayı birbirine bağlar. İkisini tek tabloya sıkıştırmak, "kabul bekliyor"
 * durumunu anlamsız kılardı.
 *
 * Devir tamamlandığında teslim edilen tutarlar YENİ vardiyanın `opening`
 * hareketleri olur; teslim eden vardiya kapanır. Bu yüzden devir, kapanan
 * vardiyada ayrıca bir `cash_out` hareketi ÜRETMEZ — aksi hâlde vardiyanın
 * kapanış sayımı ile devir tutarı birbirini götürür ve devir raporundaki
 * "Beklenen / Sayılan" satırları anlamını kaybederdi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_transfers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('from_session_id')->constrained('cash_sessions')->restrictOnDelete();
            $table->foreignId('to_session_id')->nullable()->constrained('cash_sessions')->restrictOnDelete();

            $table->foreignId('from_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('to_user_id')->constrained('users')->restrictOnDelete();

            $table->string('status', 20)->default('pending');

            $table->timestamp('initiated_at');
            $table->timestamp('responded_at')->nullable();

            $table->string('note', 255)->nullable();

            /*
             * "Bir vardiyanın aynı anda yalnızca bir bekleyen devri olabilir"
             * kısıtını veritabanında uygular: bekleyen devirde kaynak vardiya
             * kimliğini, diğer durumlarda NULL taşır. Çift form gönderimi ya da
             * eşzamanlı iki istek ikinci devri oluşturamaz.
             */
            $table->unsignedBigInteger('pending_from_session_id')->nullable()
                ->storedAs("(CASE WHEN status = 'pending' THEN from_session_id ELSE NULL END)");

            $table->timestamps();

            $table->unique('pending_from_session_id');
            $table->index('from_session_id');
            $table->index('to_user_id');
            $table->index('status');
            $table->index('initiated_at');
        });

        DB::statement(
            "ALTER TABLE cash_transfers ADD CONSTRAINT cash_transfers_status_valid
             CHECK (status IN ('pending', 'accepted', 'cancelled'))"
        );

        // Kendi kendine devir yapılamaz (kullanıcı talimatı §5).
        DB::statement(
            'ALTER TABLE cash_transfers ADD CONSTRAINT cash_transfers_not_self
             CHECK (from_user_id <> to_user_id)'
        );

        /*
         * Kabul edilmiş devir, hedef vardiyayı ve yanıt zamanını taşımak
         * ZORUNDADIR; bekleyen devirde ikisi de boştur.
         */
        DB::statement(
            "ALTER TABLE cash_transfers ADD CONSTRAINT cash_transfers_response_consistent
             CHECK ((status = 'pending'   AND to_session_id IS NULL AND responded_at IS NULL)
                 OR (status = 'accepted'  AND to_session_id IS NOT NULL AND responded_at IS NOT NULL)
                 OR (status = 'cancelled' AND to_session_id IS NULL AND responded_at IS NOT NULL))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_transfers');
    }
};
