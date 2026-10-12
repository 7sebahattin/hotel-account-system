<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kullanıcının 12 Ekim 2026 tarihli tek seferlik fiziksel temizleme istisnası.
 * Migration hiçbir cari kaydı silmez; UPDATE korumaları aynen kalır.
 *
 * MySQL/MariaDB DDL implicit commit yapar: transaction DDL'yi geri alamaz.
 * Bu nedenle iki tabloda da bağımsız, koşulsuz DELETE engeli önce kurulur.
 * Ancak bundan sonra asıl DELETE trigger'ları değiştirilir. İki yeni koruma
 * tamamlanmadan geçici engeller kaldırılmaz. Ara adım hatasında engeller
 * veritabanında kalır; migration yeniden çalışınca eksik adımları tamamlar.
 * Var olan geçici trigger beklenen koşulsuz engel değilse işlem durur.
 *
 * up/down ve yürütücü aynı connection advisory lock'unu kullanır. down hazır
 * olmayan/tamamlanmış işleme uygulanmaz; tamamlanma izi silinip yeniden
 * etkinleşme olamaz. Güvenlik durumu hiçbir yeniden denemede sıfırlanmaz.
 */
return new class extends Migration
{
    private const LOCK = 'lemon_cari_cleanup';

    private const GUARD_BODY = "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cari cleanup migration in progress'";

    /** @var array<string, array{event: string, body: string}> */
    private const STATE_GUARDS = [
        'cari_cleanup_operations_completed_immutable' => [
            'event' => 'UPDATE',
            'body' => "BEGIN /* LEMON_CARI_CLEANUP_STATE_V1 */ IF OLD.status = 'completed' OR NEW.id <> OLD.id THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Completed cari cleanup operation is immutable'; END IF; END",
        ],
        'cari_cleanup_operations_no_delete' => [
            'event' => 'DELETE',
            'body' => "BEGIN /* LEMON_CARI_CLEANUP_STATE_V1 */ SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cari cleanup operation cannot be deleted'; END",
        ],
    ];

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
        $this->withLock(function (): void {
            if (! Schema::hasTable('cari_cleanup_operations')) {
                Schema::create('cari_cleanup_operations', function (Blueprint $table): void {
                    $table->unsignedTinyInteger('id')->primary();
                    $table->enum('status', ['ready', 'running', 'completed'])->default('ready');
                    $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
                    $table->unsignedBigInteger('connection_id')->nullable();
                    $table->char('nonce_hash', 64)->nullable();
                    $table->timestamp('expires_at')->nullable();
                    $table->timestamp('completed_at')->nullable();
                    $table->longText('report')->nullable();
                    $table->timestamps();
                    $table->engine = 'InnoDB';
                });
            }

            $constraint = DB::selectOne(
                "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'cari_cleanup_operations' AND CONSTRAINT_NAME = 'cari_cleanup_singleton'",
            );
            if ($constraint === null) {
                DB::statement('ALTER TABLE cari_cleanup_operations ADD CONSTRAINT cari_cleanup_singleton CHECK (id = 1)');
            }

            DB::table('cari_cleanup_operations')->insertOrIgnore([
                'id' => 1,
                'status' => 'ready',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->assertReady();
            $this->installStateGuards();
            $this->installGuards();

            foreach (self::TABLES as $table => $definition) {
                $name = $definition['trigger'];
                $message = $definition['message'];
                DB::unprepared("DROP TRIGGER IF EXISTS `{$name}`");
                DB::unprepared(<<<SQL
                    CREATE TRIGGER `{$name}` BEFORE DELETE ON `{$table}` FOR EACH ROW
                    BEGIN
                        /* LEMON_CARI_CLEANUP_V1 */
                        IF NOT EXISTS (
                            SELECT 1
                            FROM cari_cleanup_operations AS operation
                            INNER JOIN users AS actor ON actor.id = operation.actor_id
                            INNER JOIN model_has_roles AS assignment
                                ON assignment.model_id = actor.id AND assignment.model_type = 'user'
                            INNER JOIN roles AS actor_role ON actor_role.id = assignment.role_id
                            WHERE operation.id = 1
                              AND operation.status = 'running'
                              AND operation.completed_at IS NULL
                              AND operation.connection_id = CONNECTION_ID()
                              AND operation.expires_at >= UTC_TIMESTAMP()
                              AND operation.nonce_hash IS NOT NULL
                              AND BINARY SHA2(@cari_cleanup_token, 256) = BINARY operation.nonce_hash
                              AND actor.is_active = 1
                              AND actor.deleted_at IS NULL
                              AND actor_role.name = 'admin'
                              AND actor_role.guard_name = 'web'
                        ) THEN
                            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}';
                        END IF;
                    END
                    SQL);
            }

            $this->removeGuards();
        });
    }

    public function down(): void
    {
        $this->withLock(function (): void {
            if (Schema::hasTable('cari_cleanup_operations')) {
                $this->assertReady();
            }
            $this->installGuards();

            foreach (self::TABLES as $table => $definition) {
                $name = $definition['trigger'];
                $message = $definition['message'];
                DB::unprepared("DROP TRIGGER IF EXISTS `{$name}`");
                DB::unprepared("CREATE TRIGGER `{$name}` BEFORE DELETE ON `{$table}` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'");
            }

            Schema::dropIfExists('cari_cleanup_operations');
            $this->removeGuards();
        });
    }

    private function assertReady(): void
    {
        $operation = DB::table('cari_cleanup_operations')->where('id', 1)->first();
        if ($operation === null || $operation->status !== 'ready'
            || $operation->actor_id !== null || $operation->connection_id !== null
            || $operation->nonce_hash !== null || $operation->expires_at !== null
            || $operation->completed_at !== null) {
            throw new RuntimeException('Cari temizleme durumu hazır değil; tamamlanma veya çalışma izi sıfırlanamaz.');
        }
    }

    private function installGuards(): void
    {
        foreach (self::TABLES as $table => $definition) {
            $guard = $definition['guard'];
            $existing = DB::selectOne(
                'SELECT EVENT_OBJECT_TABLE, EVENT_MANIPULATION, ACTION_TIMING, ACTION_STATEMENT FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME = ?',
                [$guard],
            );
            if ($existing !== null) {
                if ($existing->EVENT_OBJECT_TABLE !== $table || $existing->EVENT_MANIPULATION !== 'DELETE'
                    || $existing->ACTION_TIMING !== 'BEFORE'
                    || rtrim(trim($existing->ACTION_STATEMENT), ';') !== self::GUARD_BODY) {
                    throw new RuntimeException('Cari temizleme DDL koruması beklenen tanımla eşleşmiyor.');
                }

                continue;
            }
            DB::unprepared("CREATE TRIGGER `{$guard}` BEFORE DELETE ON `{$table}` FOR EACH ROW ".self::GUARD_BODY);
        }
    }

    private function installStateGuards(): void
    {
        foreach (self::STATE_GUARDS as $name => $definition) {
            $existing = DB::selectOne(
                'SELECT EVENT_OBJECT_TABLE, EVENT_MANIPULATION, ACTION_TIMING, ACTION_STATEMENT FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME = ?',
                [$name],
            );
            if ($existing !== null) {
                if ($existing->EVENT_OBJECT_TABLE !== 'cari_cleanup_operations'
                    || $existing->EVENT_MANIPULATION !== $definition['event']
                    || $existing->ACTION_TIMING !== 'BEFORE'
                    || rtrim(trim($existing->ACTION_STATEMENT), ';') !== $definition['body']) {
                    throw new RuntimeException('Cari temizleme durum koruması beklenen tanımla eşleşmiyor.');
                }

                continue;
            }
            DB::unprepared('CREATE TRIGGER `'.$name.'` BEFORE '.$definition['event'].' ON cari_cleanup_operations FOR EACH ROW '.$definition['body']);
        }
    }

    private function removeGuards(): void
    {
        foreach (self::TABLES as $definition) {
            DB::unprepared('DROP TRIGGER `'.$definition['guard'].'`');
        }
    }

    private function withLock(Closure $callback): void
    {
        $result = DB::selectOne('SELECT GET_LOCK(?, 0) AS acquired', [self::LOCK]);
        if ((int) ($result->acquired ?? 0) !== 1) {
            throw new RuntimeException('Cari temizleme veya koruma geçişi zaten çalışıyor.');
        }

        try {
            $callback();
        } finally {
            DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [self::LOCK]);
        }
    }
};
