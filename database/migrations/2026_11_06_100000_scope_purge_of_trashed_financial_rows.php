<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Çöp kutusundan KALICI silme için ledger_entries ve payment_allocations
 * DELETE tetikleyicilerine dar, bağlantıya bağlı kapı (6 Kasım 2026, kullanıcı onayı).
 *
 * Kapı yalnızca `Core\Trash\FinancialPurgeGate` tarafından, çağrının sayılan
 * satır kimlikleri için, 60 saniyeliğine ve aktif bir Admin adına açılır:
 *   oturum değişkenleri: lemon_purge_actor, lemon_purge_until,
 *   lemon_purge_ledger_ids, lemon_purge_allocation_ids
 * Kimlik listesinde olmayan satır, süresi dolmuş izin veya Admin olmayan aktör
 * SIGNAL ile reddedilir. NULL mantığı kapalı kalır (IFNULL(..., 0)): değişken
 * tanımsızsa DELETE yine engellidir. UPDATE tetikleyicileri DEĞİŞMEZ.
 *
 * DDL implicit commit yaptığı için transaction güvenilmez: her tetikleyici
 * önce DROP sonra CREATE edilir; ara adım hatasında yeniden çalıştırma tamamlar.
 */
return new class extends Migration
{
    /** @var array<string, array{trigger: string, variable: string, message: string}> */
    private const TABLES = [
        'ledger_entries' => [
            'trigger' => 'ledger_entries_block_delete',
            'variable' => '@lemon_purge_ledger_ids',
            'message' => 'ledger_entries kayitlari silinemez; duzeltme ters kayit ile yapilir.',
        ],
        'payment_allocations' => [
            'trigger' => 'payment_allocations_no_delete',
            'variable' => '@lemon_purge_allocation_ids',
            'message' => 'Payment allocations are append-only',
        ],
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table => $definition) {
            $name = $definition['trigger'];
            $variable = $definition['variable'];
            $message = $definition['message'];

            DB::unprepared("DROP TRIGGER IF EXISTS `{$name}`");
            DB::unprepared(<<<SQL
                CREATE TRIGGER `{$name}` BEFORE DELETE ON `{$table}` FOR EACH ROW
                BEGIN
                    /* LEMON_TRASH_PURGE_V1 */
                    IF NOT IFNULL((
                        @lemon_purge_until >= UTC_TIMESTAMP()
                        AND FIND_IN_SET(OLD.id, {$variable}) > 0
                        AND EXISTS (
                            SELECT 1
                            FROM users AS actor
                            INNER JOIN model_has_roles AS assignment
                                ON assignment.model_id = actor.id AND assignment.model_type = 'user'
                            INNER JOIN roles AS actor_role ON actor_role.id = assignment.role_id
                            WHERE actor.id = @lemon_purge_actor
                              AND actor.is_active = 1
                              AND actor.deleted_at IS NULL
                              AND actor_role.name = 'admin'
                              AND actor_role.guard_name = 'web'
                        )
                    ), 0) THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}';
                    END IF;
                END
                SQL);
        }
    }

    /** Koşulsuz korumaya DÖNER (güvenli yön). */
    public function down(): void
    {
        foreach (self::TABLES as $table => $definition) {
            $name = $definition['trigger'];
            $message = $definition['message'];
            DB::unprepared("DROP TRIGGER IF EXISTS `{$name}`");
            DB::unprepared("CREATE TRIGGER `{$name}` BEFORE DELETE ON `{$table}` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'");
        }
    }
};
