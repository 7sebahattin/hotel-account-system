<?php

declare(strict_types=1);

namespace App\Modules\CariCleanup\Services;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/** Salt okunur teşhis. Bu sınıfta temizleme/yazma API'si bilinçli olarak yoktur. */
class CariCleanupService
{
    /** Aday listesi silme izni değildir; tüm diğer tablolar varsayılan korunur. */
    private const CATEGORIES = [
        'Cari hesaplar' => ['companies'],
        'Konaklamalar' => ['stays'],
        'Teklifler' => ['offers', 'offer_periods', 'offer_room_types', 'offer_prices'],
        'Tahsilatlar' => ['payments'],
        'Tahsilat makbuzları' => ['receipt_book_entries'],
        'Cari finansal kayıtlar' => ['ledger_entries', 'payment_allocations'],
    ];

    private const PROTECTED_GROUPS = [
        'Hesap', 'Personel', 'Yönetim', 'Kullanıcılar', 'Odalar',
        'Denetim geçmişi', 'Eski kasa ve hareket geçmişi', 'Diğer kapsam dışı modüller',
    ];

    /** @return array<string, mixed> */
    public function report(): array
    {
        $base = [
            'checked_at' => now()->format('d.m.Y H:i:s'),
            'available' => false,
            'blockers' => [
                'Tam fiziksel CARİ temizliği mevcut append-only finansal mimariyle uyumlu değildir. Silme işlemi sunucuda tanımlı değildir; hiçbir ön kontrol silme yetkisi vermez.',
                'Ters kayıt, orijinal cari ve dağılım geçmişini kaldırmaz. Kısmi temizlik yapılmayacaktır.',
            ],
            'tables' => [], 'categories' => $this->categories([]),
            'dependencies' => [], 'triggers' => [],
            'protected_groups' => array_map(fn (string $label): array => ['label' => $label, 'count' => null], self::PROTECTED_GROUPS),
        ];

        try {
            $connection = DB::connection();
            if (! in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
                throw new RuntimeException('MySQL/MariaDB metadata required.');
            }

            // HTTP'de tek REPEATABLE READ, READ ONLY transaction. Testlerin mevcut
            // transaction'ına katılmak test izolasyonunu ve snapshot'ını korur.
            if ($connection->transactionLevel() > 0) {
                $snapshot = $this->snapshot($connection);
            } else {
                $connection->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
                $snapshot = $connection->transaction(fn (): array => $this->snapshot($connection));
            }

            return array_replace($base, $snapshot, [
                'available' => true,
                'blockers' => array_merge($base['blockers'], $snapshot['blockers']),
            ]);
        } catch (Throwable $exception) {
            report($exception);
            $base['blockers'][] = 'Veritabanı envanteri tam ve tutarlı okunamadı. Sayılar doğrulanamadı; işlem bloke kalır.';

            return $base;
        }
    }

    /** @return array<string, mixed> */
    private function snapshot(Connection $connection): array
    {
        $schema = $connection->getDatabaseName();
        $inventory = $connection->select(
            'SELECT TABLE_NAME AS name, TABLE_TYPE AS type, ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME',
            [$schema],
        );
        $candidates = array_merge(...array_values(self::CATEGORIES));
        $counts = [];
        $tables = [];
        $groups = array_fill_keys(self::PROTECTED_GROUPS, 0);

        foreach ($inventory as $table) {
            // MyISAM/view sayıları aynı InnoDB snapshot güvencesini sağlamaz.
            if ($table->type !== 'BASE TABLE' || strtoupper((string) $table->engine) !== 'INNODB') {
                throw new RuntimeException('Unsupported table snapshot: '.$table->name);
            }
            $name = (string) $table->name;
            $count = $connection->table($name)->count();
            $counts[$name] = $count;
            $protected = ! in_array($name, $candidates, true);
            $module = $protected ? $this->protectedModule($name) : 'Cari';
            $tables[] = compact('name', 'module', 'count', 'protected');
            if ($protected) {
                $groups[$module] += $count;
            }
        }

        foreach (array_merge($candidates, [
            'monthly_account_days', 'monthly_account_revisions', 'monthly_account_items',
            'monthly_payment_providers', 'audit_logs', 'cash_movements',
        ]) as $required) {
            if (! array_key_exists($required, $counts)) {
                throw new RuntimeException('Required table missing: '.$required);
            }
        }

        $dependencies = array_map(fn (object $row): array => (array) $row, $connection->select(
            'SELECT k.TABLE_NAME AS `table`, k.COLUMN_NAME AS `column`, k.REFERENCED_TABLE_NAME AS referenced_table, k.REFERENCED_COLUMN_NAME AS referenced_column, r.DELETE_RULE AS delete_rule
             FROM information_schema.KEY_COLUMN_USAGE k
             JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.TABLE_NAME = k.TABLE_NAME AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
             WHERE k.TABLE_SCHEMA = ? AND k.REFERENCED_TABLE_NAME IS NOT NULL ORDER BY k.TABLE_NAME, k.CONSTRAINT_NAME, k.ORDINAL_POSITION',
            [$schema],
        ));
        $triggers = array_map(fn (object $row): array => (array) $row, $connection->select(
            'SELECT EVENT_OBJECT_TABLE AS `table`, TRIGGER_NAME AS name, EVENT_MANIPULATION AS event FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = ? ORDER BY EVENT_OBJECT_TABLE, TRIGGER_NAME',
            [$schema],
        ));

        $blockers = [];
        foreach (['ledger_entries', 'payment_allocations'] as $immutable) {
            $blockers[] = $immutable.': '.$counts[$immutable].' kalıcı finansal geçmiş kaydı; UPDATE/DELETE koruması aşılmayacaktır.';
        }
        foreach (['ledger_entries_block_delete', 'payment_allocations_no_delete'] as $expected) {
            if (! in_array($expected, array_column($triggers, 'name'), true)) {
                $blockers[] = 'Beklenen finansal koruma tetikleyicisi okunamadı: '.$expected.'. Bu durum silme izni değil, koruma belirsizliğidir.';
            }
        }
        $cashReferences = $connection->table('cash_movements')->where('reference_type', 'payment')->count();
        $blockers[] = 'Eski kasa geçmişinde '.$cashReferences.' tahsilat referansı var (reference_type/reference_id; FK değildir). Geçmiş kasa içeriği korunacaktır.';
        $blockers[] = 'Cari defter kaynakları ve denetim geçmişi polimorfik referans taşır. Yalnız FK taraması yeterli değildir; kimlikler ve numara sıraları sıfırlanmayacaktır.';
        $blockers[] = 'Standart iptal/silme servisleri yeni ters kayıt veya denetim kaydı üretebilir; bunlar tam temizlik yerine çalıştırılmayacaktır.';

        return [
            'tables' => $tables, 'categories' => $this->categories($counts),
            'dependencies' => $dependencies, 'triggers' => $triggers, 'blockers' => $blockers,
            'protected_groups' => array_map(fn (string $label): array => ['label' => $label, 'count' => $groups[$label]], self::PROTECTED_GROUPS),
        ];
    }

    /** @param array<string, int> $counts
     * @return list<array{label: string, tables: list<string>, count: ?int}>
     */
    private function categories(array $counts): array
    {
        $result = [];
        foreach (self::CATEGORIES as $label => $tables) {
            $complete = count(array_intersect($tables, array_keys($counts))) === count($tables);
            $result[] = ['label' => $label, 'tables' => $tables,
                'count' => $complete ? array_sum(array_intersect_key($counts, array_flip($tables))) : null];
        }

        return $result;
    }

    private function protectedModule(string $table): string
    {
        return match (true) {
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
