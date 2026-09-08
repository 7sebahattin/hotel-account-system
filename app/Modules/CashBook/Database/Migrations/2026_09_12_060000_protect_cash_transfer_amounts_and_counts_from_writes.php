<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `cash_transfer_amounts` ve `cash_counts` üzerinde UPDATE ve DELETE'i
 * veritabanı düzeyinde engeller — `cash_movements` ile AYNI ilke
 * (`protect_cash_movements_from_writes` migration'ı), aynı gerekçeyle:
 *
 *  - `cash_transfer_amounts`: devir kabul edildiğinde bu tutar YENİ vardiyanın
 *    açılış bakiyesine zaten kopyalanmıştır. Sonradan değiştirilebilseydi
 *    imzalanmış devir kaydı ile gerçek açılış bakiyesi sessizce kopardı.
 *  - `cash_counts`: kapanmış bir vardiyanın imzalanmış sayım tanığıdır.
 *
 * Uygulama katmanındaki korumalar (model olayları) yalnızca Eloquent'ten geçen
 * yazmaları yakalar; bu tetikleyiciler ham SQL dahil her yolu kapatır.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER cash_transfer_amounts_block_update
            BEFORE UPDATE ON cash_transfer_amounts
            FOR EACH ROW
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'cash_transfer_amounts kayitlari degistirilemez.';
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER cash_transfer_amounts_block_delete
            BEFORE DELETE ON cash_transfer_amounts
            FOR EACH ROW
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'cash_transfer_amounts kayitlari silinemez.';
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER cash_counts_block_update
            BEFORE UPDATE ON cash_counts
            FOR EACH ROW
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'cash_counts kayitlari degistirilemez.';
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER cash_counts_block_delete
            BEFORE DELETE ON cash_counts
            FOR EACH ROW
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'cash_counts kayitlari silinemez.';
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS cash_transfer_amounts_block_update');
        DB::unprepared('DROP TRIGGER IF EXISTS cash_transfer_amounts_block_delete');
        DB::unprepared('DROP TRIGGER IF EXISTS cash_counts_block_update');
        DB::unprepared('DROP TRIGGER IF EXISTS cash_counts_block_delete');
    }
};
