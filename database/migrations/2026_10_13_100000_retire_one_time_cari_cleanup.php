<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tek seferlik temizleme sayfasının 13 Ekim 2026 tarihli emeklilik geçişi.
 * Hiçbir tablo satırı, UPDATE trigger'ı veya kalıcı işlem izi değiştirilmez.
 * İşlem ready/running/completed olabilir; yalnızca DELETE istisnası kapanır.
 *
 * DDL implicit commit nedeniyle transaction'a güvenilmez. Önce İKİ tabloda
 * da eski migration ile aynı ad/tanımda koşulsuz DELETE engelleri kurulur ve
 * doğrulanır. Ardından iki asıl trigger koşulsuz korumaya döner. Ancak her
 * ikisi tamamlanınca geçici engeller kaldırılır. Ara adım hatası engelleri
 * yerinde bırakır; yeniden çalıştırma aynı korumalar altında tamamlanır.
 *
 * Kalıcı completed raporu ve onu koruyan state trigger'ları aynen kalır.
 * down() hiçbir işlem yapmaz; istisnayı yeniden açmaz. Rollback sırasında da
 * koşulsuz finansal korumalar yerinde kalır; bu geçiş yalnız ileri yöndedir.
 */
return new class extends Migration
{
    private const LOCK = 'lemon_cari_cleanup';

    private const GUARD_BODY = "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cari cleanup migration in progress'";

    /** @var array<string, array{trigger: string, guard: string, message: string}> */
    private const TABLES = [
        'ledger_entries' => [
            'trigger' => 'ledger_entries_block_delete',
            'guard' => 'cari_cleanup_install_guard_ledger_entries',
            'message' => 'ledger_entries kayitlari silinemez; duzeltme ters kayit ile yapilir.',
        ],
        'payment_allocations' => [
            'trigger' => 'payment_allocations_no_delete',
            'guard' => 'cari_cleanup_install_guard_payment_allocations',
            'message' => 'Payment allocations are append-only',
        ],
    ];

    public function up(): void
    {
        $lock = DB::selectOne('SELECT GET_LOCK(?, 0) AS acquired', [self::LOCK]);
        if ((int) ($lock->acquired ?? 0) !== 1) {
            throw new RuntimeException('Cari temizleme veya koruma geçişi zaten çalışıyor.');
        }

        try {
            $this->installGuards();

            foreach (self::TABLES as $table => $definition) {
                $name = $definition['trigger'];
                $message = $definition['message'];
                DB::unprepared("DROP TRIGGER IF EXISTS `{$name}`");
                DB::unprepared("CREATE TRIGGER `{$name}` BEFORE DELETE ON `{$table}` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'");
            }

            foreach (self::TABLES as $definition) {
                DB::unprepared('DROP TRIGGER `'.$definition['guard'].'`');
            }
        } finally {
            DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [self::LOCK]);
        }
    }

    public function down(): void
    {
        // İleri güvenlik düzeltmesi geri alınmaz. Testlerde migration kaydı
        // geri sarılabilse de DELETE istisnası veya silme API'si yeniden açılmaz.
        // Tablo satırları ve tamamlanmış işlem raporu aynen korunur.
    }

    private function installGuards(): void
    {
        foreach (self::TABLES as $table => $definition) {
            $name = $definition['guard'];
            $existing = DB::selectOne(
                'SELECT EVENT_OBJECT_TABLE, EVENT_MANIPULATION, ACTION_TIMING, ACTION_STATEMENT FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME = ?',
                [$name],
            );
            if ($existing !== null) {
                if ($existing->EVENT_OBJECT_TABLE !== $table || $existing->EVENT_MANIPULATION !== 'DELETE'
                    || $existing->ACTION_TIMING !== 'BEFORE'
                    || rtrim(trim($existing->ACTION_STATEMENT), ';') !== self::GUARD_BODY) {
                    throw new RuntimeException('Cari temizleme DDL koruması beklenen tanımla eşleşmiyor.');
                }

                continue;
            }
            DB::unprepared("CREATE TRIGGER `{$name}` BEFORE DELETE ON `{$table}` FOR EACH ROW ".self::GUARD_BODY);
        }
    }
};
