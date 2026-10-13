<?php

declare(strict_types=1);

namespace App\Modules\CariCleanup\Services;

use Illuminate\Database\Connection;
use RuntimeException;

/** Katalog, tam satır özeti ve gerektiğinde InnoDB satır/boşluk kilitleri. */
class CariCleanupInventory
{
    public const STATE_TABLE = 'cari_cleanup_operations';

    public const CATEGORIES = [
        'Konaklamalar' => ['stays'],
        'Teklifler' => ['offers', 'offer_periods', 'offer_room_types', 'offer_prices'],
        'Tahsilatlar' => ['payments'],
        'Tahsilat makbuzları' => ['receipt_book_entries'],
        'Cari hareketler' => ['ledger_entries', 'payment_allocations'],
    ];

    public const TARGETS = [
        'payment_allocations', 'ledger_entries', 'offer_prices', 'offer_periods',
        'offer_room_types', 'offers', 'payments', 'stays', 'receipt_book_entries',
    ];

    /** @return array<string, mixed> */
    public function inspect(Connection $db, bool $contents = false, bool $lock = false): array
    {
        if (! in_array($db->getDriverName(), ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('MySQL/MariaDB required.');
        }
        $schema = $this->schema($db);
        $names = array_column($schema['tables'], 'name');
        foreach (array_merge(self::TARGETS, [
            'companies', self::STATE_TABLE, 'users', 'roles', 'model_has_roles',
            'monthly_account_days', 'monthly_account_revisions', 'monthly_account_items',
            'monthly_payment_providers', 'audit_logs', 'cash_movements',
        ]) as $required) {
            if (! in_array($required, $names, true)) {
                throw new RuntimeException('Required table missing: '.$required);
            }
        }

        $stats = [];
        $tables = [];
        $groups = [];
        foreach ($schema['tables'] as $table) {
            if ($table['type'] !== 'BASE TABLE' || strtoupper((string) $table['engine']) !== 'INNODB') {
                throw new RuntimeException('Unsupported table snapshot: '.$table['name']);
            }
            $name = $table['name'];
            $query = $db->table($name);
            if ($lock) {
                $query->lockForUpdate();
            }
            if ($contents) {
                $rows = $query->get()->all();
                $hashes = [];
                foreach ($rows as $row) {
                    $values = (array) $row;
                    ksort($values, SORT_STRING);
                    // serialize preserves NULL, binary bytes, scalar types and lengths.
                    $hashes[] = hash('sha256', serialize($values));
                }
                sort($hashes, SORT_STRING); // duplicates remain in the multiset
                $stats[$name] = ['count' => count($rows), 'sha256' => hash('sha256', serialize($hashes))];
            } else {
                $stats[$name] = ['count' => $query->count(), 'sha256' => null];
            }
            $protected = ! in_array($name, self::TARGETS, true);
            $metadata = $name === self::STATE_TABLE;
            $module = $metadata ? 'İşlem durumu' : ($protected ? $this->module($name) : 'Cari');
            $count = $stats[$name]['count'];
            $tables[] = compact('name', 'module', 'count', 'protected', 'metadata');
            if ($protected && ! $metadata) {
                $groups[$module] = ($groups[$module] ?? 0) + $count;
            }
        }

        $state = $db->table(self::STATE_TABLE)->where('id', 1)->first();
        if ($state === null || $stats[self::STATE_TABLE]['count'] !== 1) {
            throw new RuntimeException('Invalid cleanup singleton.');
        }
        $blockers = [];
        foreach ([
            'ledger_entries_block_delete' => 'ledger_entries',
            'payment_allocations_no_delete' => 'payment_allocations',
        ] as $expected => $table) {
            $trigger = collect($schema['triggers'])->firstWhere('name', $expected);
            if ($trigger === null || $trigger['table'] !== $table || $trigger['event'] !== 'DELETE'
                || $trigger['timing'] !== 'BEFORE'
                || ! str_contains($trigger['definition'], 'LEMON_CARI_CLEANUP_V1')) {
                $blockers[] = 'Tek kullanımlık finansal silme koruması doğrulanamadı: '.$expected;
            }
        }
        foreach (['ledger_entries_block_update', 'payment_allocations_no_update'] as $name) {
            $trigger = collect($schema['triggers'])->firstWhere('name', $name);
            if ($trigger === null || $trigger['event'] !== 'UPDATE' || ! str_contains(strtoupper($trigger['definition']), 'SIGNAL')) {
                $blockers[] = 'Finansal güncelleme koruması doğrulanamadı: '.$name;
            }
        }
        foreach (['cari_cleanup_operations_completed_immutable' => 'UPDATE', 'cari_cleanup_operations_no_delete' => 'DELETE'] as $name => $event) {
            $trigger = collect($schema['triggers'])->firstWhere('name', $name);
            if ($trigger === null || $trigger['table'] !== self::STATE_TABLE || $trigger['event'] !== $event
                || $trigger['timing'] !== 'BEFORE' || ! str_contains($trigger['definition'], 'LEMON_CARI_CLEANUP_STATE_V1')) {
                $blockers[] = 'Tek kullanımlık işlem kilidi doğrulanamadı: '.$name;
            }
        }
        foreach ($schema['triggers'] as $trigger) {
            if (str_starts_with($trigger['name'], 'cari_cleanup_install_guard_')) {
                $blockers[] = 'Veritabanı koruma geçişi tamamlanmamış; işlem kapalı.';
            }
        }
        foreach ($schema['dependencies'] as $dependency) {
            if (in_array($dependency['referenced_table'], self::TARGETS, true)
                && ! in_array($dependency['table'], self::TARGETS, true)) {
                $blockers[] = 'Korunan tablodan silme kapsamına beklenmeyen bağlantı: '.$dependency['table'].'.'.$dependency['column'];
            }
        }
        $completed = $state->status === 'completed';
        if (! $completed && ($state->status !== 'ready' || $state->actor_id !== null
            || $state->connection_id !== null || $state->nonce_hash !== null || $state->expires_at !== null
            || $state->completed_at !== null || $state->report !== null)) {
            $blockers[] = 'İşlem durumu hazır değil; yeniden etkinleştirme yapılmaz.';
        }

        $categories = [];
        foreach (self::CATEGORIES as $label => $categoryTables) {
            $categories[] = [
                'label' => $label, 'tables' => $categoryTables,
                'count' => array_sum(array_map(fn (string $name): int => $stats[$name]['count'], $categoryTables)),
            ];
        }
        $protected = array_diff_key($stats, array_flip(array_merge(self::TARGETS, [self::STATE_TABLE])));
        $fingerprintRows = array_intersect_key($stats, array_flip(array_merge(self::TARGETS, ['companies'])));

        return [
            'available' => true, 'checked_at' => now()->format('d.m.Y H:i:s'),
            'tables' => $tables, 'categories' => $categories,
            'protected_groups' => array_map(fn (string $label): array => ['label' => $label, 'count' => $groups[$label]], array_keys($groups)),
            'dependencies' => $schema['dependencies'], 'triggers' => $schema['triggers'],
            'blockers' => $blockers, 'completed' => $completed,
            'completion_report' => $completed ? json_decode((string) $state->report, true, 512, JSON_THROW_ON_ERROR) : null,
            'ready' => ! $completed && $blockers === [],
            'stats' => $stats, 'protected_stats' => $protected,
            'schema_hash' => hash('sha256', serialize($schema)),
            'fingerprint' => hash('sha256', serialize([$schema, $fingerprintRows])),
        ];
    }

    /** @return array<string, list<array<string, mixed>>> */
    public function schema(Connection $db): array
    {
        $schema = $db->getDatabaseName();
        $queries = [
            'tables' => 'SELECT TABLE_NAME AS name, TABLE_TYPE AS type, ENGINE AS engine, TABLE_COLLATION AS collation FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME',
            'columns' => 'SELECT TABLE_NAME AS table_name, COLUMN_NAME AS name, ORDINAL_POSITION AS position, COLUMN_TYPE AS type, IS_NULLABLE AS nullable, COLUMN_DEFAULT AS default_value, COLLATION_NAME AS collation, EXTRA AS extra, GENERATION_EXPRESSION AS generation_expression FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME, ORDINAL_POSITION',
            'indexes' => 'SELECT TABLE_NAME AS table_name, INDEX_NAME AS name, NON_UNIQUE AS non_unique, SEQ_IN_INDEX AS sequence_number, COLUMN_NAME AS column_name, SUB_PART AS prefix_length, COLLATION AS collation, INDEX_TYPE AS type FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX',
            'constraints' => 'SELECT TABLE_NAME AS table_name, CONSTRAINT_NAME AS name, CONSTRAINT_TYPE AS type FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = ? ORDER BY TABLE_NAME, CONSTRAINT_NAME',
            'checks' => 'SELECT t.TABLE_NAME AS table_name, c.CONSTRAINT_NAME AS name, c.CHECK_CLAUSE AS definition FROM information_schema.CHECK_CONSTRAINTS c JOIN information_schema.TABLE_CONSTRAINTS t ON t.CONSTRAINT_SCHEMA = c.CONSTRAINT_SCHEMA AND t.CONSTRAINT_NAME = c.CONSTRAINT_NAME AND t.CONSTRAINT_TYPE = \'CHECK\' WHERE c.CONSTRAINT_SCHEMA = ? ORDER BY t.TABLE_NAME, c.CONSTRAINT_NAME',
            'dependencies' => 'SELECT k.TABLE_NAME AS `table`, k.COLUMN_NAME AS `column`, k.REFERENCED_TABLE_NAME AS referenced_table, k.REFERENCED_COLUMN_NAME AS referenced_column, r.DELETE_RULE AS delete_rule, r.UPDATE_RULE AS update_rule FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.TABLE_NAME = k.TABLE_NAME AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME WHERE k.TABLE_SCHEMA = ? AND k.REFERENCED_TABLE_NAME IS NOT NULL ORDER BY k.TABLE_NAME, k.CONSTRAINT_NAME, k.ORDINAL_POSITION',
            'triggers' => 'SELECT EVENT_OBJECT_TABLE AS `table`, TRIGGER_NAME AS name, EVENT_MANIPULATION AS event, ACTION_TIMING AS timing, ACTION_STATEMENT AS definition FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = ? ORDER BY EVENT_OBJECT_TABLE, TRIGGER_NAME',
        ];
        // MariaDB check names can repeat across tables; MySQL exposes the
        // table through TABLE_CONSTRAINTS. Do not create a cross-table join.
        if (str_contains((string) $db->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION), 'MariaDB')) {
            $queries['checks'] = 'SELECT TABLE_NAME AS table_name, CONSTRAINT_NAME AS name, CHECK_CLAUSE AS definition FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = ? ORDER BY TABLE_NAME, CONSTRAINT_NAME';
        } else {
            $queries['indexes'] = 'SELECT TABLE_NAME AS table_name, INDEX_NAME AS name, NON_UNIQUE AS non_unique, SEQ_IN_INDEX AS sequence_number, COLUMN_NAME AS column_name, EXPRESSION AS expression_value, SUB_PART AS prefix_length, COLLATION AS collation, INDEX_TYPE AS type, IS_VISIBLE AS visible FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX';
        }
        $result = [];
        foreach ($queries as $key => $sql) {
            $result[$key] = array_map(fn (object $row): array => (array) $row, $db->select($sql, [$schema]));
        }

        return $result;
    }

    private function module(string $table): string
    {
        return match (true) {
            $table === 'companies' => 'Cari hesap kartları',
            str_starts_with($table, 'monthly_') => 'Hesap',
            str_starts_with($table, 'personnel_') => 'Personel',
            str_starts_with($table, 'cash_') => 'Eski kasa ve hareket geçmişi',
            in_array($table, ['roles', 'permissions', 'model_has_roles', 'model_has_permissions', 'role_has_permissions', 'user_quick_menus'], true) => 'Yönetim',
            $table === 'users' => 'Kullanıcılar',
            $table === 'rooms' => 'Odalar',
            $table === 'audit_logs' => 'Denetim geçmişi',
            default => 'Diğer kapsam dışı modüller',
        };
    }
}
