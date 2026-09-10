<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ledger_entries üzerinde UPDATE ve DELETE'i veritabanı düzeyinde engeller.
 *
 * Uygulama katmanındaki korumalar (model olayları, LedgerService) Eloquent'ten
 * geçmeyen yazmaları (query builder toplu update, ham SQL) yakalayamaz. Bu
 * tetikleyiciler CLAUDE.md §6/L1'i veritabanının kendisinde uygular.
 *
 * DAĞITIM NOTU: tetikleyici oluşturmak TRIGGER yetkisi gerektirir. Paylaşımlı
 * hostingde bu yetki kapalıysa migration hata verir — sessizce atlanmaz, çünkü
 * korumanın var sanılıp olmaması daha kötüdür. Bkz. docs/deploy-cpanel.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER ledger_entries_block_update
            BEFORE UPDATE ON ledger_entries
            FOR EACH ROW
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'ledger_entries kayitlari degistirilemez; duzeltme ters kayit ile yapilir.';
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER ledger_entries_block_delete
            BEFORE DELETE ON ledger_entries
            FOR EACH ROW
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'ledger_entries kayitlari silinemez; duzeltme ters kayit ile yapilir.';
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS ledger_entries_block_update');
        DB::unprepared('DROP TRIGGER IF EXISTS ledger_entries_block_delete');
    }
};
