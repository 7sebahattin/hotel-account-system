<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `cash_movements` üzerindeki değişmezlik kurallarını VERİTABANINDA uygular.
 *
 * Uygulama katmanı (model olayları, CashBookService) yalnızca Eloquent'ten
 * geçen yazmaları yakalayabilir; toplu query-builder update'i veya ham SQL
 * bunları atlar. Aşağıdaki üç tetikleyici o yolu da kapatır:
 *
 *  1. UPDATE yasak — düzeltme ters hareketle yapılır.
 *  2. DELETE yasak — kasa kaydı silinmez.
 *  3. KAPALI vardiyaya INSERT yasak — kapanmış bir vardiyanın geçmişi
 *     değiştirilemez (kullanıcı talimatı §3 ve §17).
 *
 * DAĞITIM NOTU: tetikleyici oluşturmak TRIGGER yetkisi gerektirir; hedef
 * hesapta bu yetki mevcuttur (docs/kararlar.md K-068). Yetki yoksa migration
 * hata verir ve deploy durur — koruma "yoksa atla" hâline GETİRİLMEZ, çünkü
 * korumanın var sanılıp olmaması olmamasından kötüdür (ledger ile aynı ilke).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER cash_movements_block_update
            BEFORE UPDATE ON cash_movements
            FOR EACH ROW
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'cash_movements kayitlari degistirilemez; duzeltme ters hareket ile yapilir.';
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER cash_movements_block_delete
            BEFORE DELETE ON cash_movements
            FOR EACH ROW
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'cash_movements kayitlari silinemez; duzeltme ters hareket ile yapilir.';
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER cash_movements_require_open_session
            BEFORE INSERT ON cash_movements
            FOR EACH ROW
            BEGIN
                DECLARE session_status VARCHAR(20);

                SELECT status INTO session_status
                FROM cash_sessions
                WHERE id = NEW.cash_session_id;

                IF session_status IS NULL OR session_status <> 'open' THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Kapali vardiyaya kasa hareketi yazilamaz.';
                END IF;
            END
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS cash_movements_block_update');
        DB::unprepared('DROP TRIGGER IF EXISTS cash_movements_block_delete');
        DB::unprepared('DROP TRIGGER IF EXISTS cash_movements_require_open_session');
    }
};
