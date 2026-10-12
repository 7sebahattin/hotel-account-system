<?php

declare(strict_types=1);

namespace App\Modules\CariCleanup\Services;

use App\Models\User;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class CariCleanupService
{
    public const LOCK = 'lemon_cari_cleanup';

    public const SESSION_KEY = 'cari_cleanup.preflight_hash';

    public function __construct(private readonly CariCleanupInventory $inventory) {}

    public function isCompleted(): bool
    {
        try {
            return DB::table(CariCleanupInventory::STATE_TABLE)->where('id', 1)->value('status') === 'completed';
        } catch (Throwable) {
            return true;
        }
    }

    /** @return array<string, mixed> */
    public function report(): array
    {
        try {
            return $this->readSnapshot(fn (Connection $db): array => $this->inventory->inspect($db));
        } catch (Throwable $exception) {
            report($exception);

            return $this->unavailable();
        }
    }

    /** @return array<string, mixed> */
    public function prepare(User $actor): array
    {
        Gate::forUser($actor)->authorize('execute', self::class);
        Session::forget(self::SESSION_KEY);
        try {
            $report = $this->readSnapshot(fn (Connection $db): array => $this->inventory->inspect($db, true));
            $report['token'] = null;
            if (! $report['ready']) {
                return $report;
            }
            $token = Crypt::encryptString(json_encode([
                'version' => 1, 'actor' => (int) $actor->getKey(),
                'expires' => now()->addMinutes(10)->getTimestamp(),
                'fingerprint' => $report['fingerprint'], 'nonce' => bin2hex(random_bytes(32)),
            ], JSON_THROW_ON_ERROR));
            Session::put(self::SESSION_KEY, hash('sha256', $token));
            $report['token'] = $token;

            return $report;
        } catch (Throwable $exception) {
            report($exception);

            return $this->unavailable() + ['token' => null];
        }
    }

    /** @return array<string, mixed> */
    public function execute(User $actor, string $token): array
    {
        Gate::forUser($actor)->authorize('execute', self::class);
        $db = DB::connection();
        $acquired = false;
        try {
            $payload = $this->validateToken($actor, $token);
            $lock = $db->selectOne('SELECT GET_LOCK(?, 0) AS acquired', [self::LOCK]);
            if ((int) ($lock->acquired ?? 0) !== 1) {
                throw ValidationException::withMessages(['cleanup' => 'Başka bir temizleme işlemi çalışıyor. Tekrar silme başlatılmadı.']);
            }
            $acquired = true;
            if ($db->transactionLevel() === 0) {
                $db->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ WRITE');
            }

            $result = $db->transaction(function () use ($db, $actor, $payload): array {
                $state = $db->table(CariCleanupInventory::STATE_TABLE)->where('id', 1)->lockForUpdate()->first();
                if ($state === null || $state->status !== 'ready') {
                    throw ValidationException::withMessages(['cleanup' => 'Bu tek seferlik işlem tamamlanmış veya kullanıma kapalı.']);
                }

                // Tam tablo taramaları RR altında mevcut satırları ve boşlukları kilitler.
                // Yetki kaydı da bu kilit kümesindedir; eski Eloquent rol cache'i kullanılmaz.
                $before = $this->inventory->inspect($db, true, true);
                if (! $before['ready']) {
                    throw ValidationException::withMessages(['cleanup' => implode(' ', $before['blockers'])]);
                }
                if (! hash_equals((string) $payload['fingerprint'], $before['fingerprint'])) {
                    throw ValidationException::withMessages(['preflight_token' => 'Cari girişleri, hesap kartları veya veritabanı yapısı değişti. Ön kontrolü yeniden çalıştırın.']);
                }
                if ($payload['expires'] <= now()->getTimestamp()) {
                    throw ValidationException::withMessages(['preflight_token' => 'Ön kontrolün süresi doldu. Ön kontrolü yeniden çalıştırın.']);
                }
                $freshActor = $actor->fresh();
                if ($freshActor === null || Gate::forUser($freshActor)->denies('execute', self::class)) {
                    throw ValidationException::withMessages(['cleanup' => 'Aktif Admin yetkisi doğrulanamadı.']);
                }

                $nonce = bin2hex(random_bytes(32));
                $connection = $db->selectOne('SELECT CONNECTION_ID() AS id');
                $db->table(CariCleanupInventory::STATE_TABLE)->where('id', 1)->update([
                    'status' => 'running', 'actor_id' => $actor->getKey(),
                    'connection_id' => $connection->id,
                    'nonce_hash' => hash('sha256', $nonce),
                    'expires_at' => $db->raw('DATE_ADD(UTC_TIMESTAMP(), INTERVAL 10 MINUTE)'),
                    'updated_at' => now(),
                ]);
                $db->statement('SET @cari_cleanup_token = ?', [$nonce]);

                $this->deleteReversalsFirst($db, 'payment_allocations', 'reverses_allocation_id');
                $this->deleteReversalsFirst($db, 'ledger_entries', 'reverses_entry_id');
                foreach (array_diff(CariCleanupInventory::TARGETS, ['payment_allocations', 'ledger_entries']) as $table) {
                    $db->table($table)->delete();
                }

                $after = $this->inventory->inspect($db, true, true);
                if ($before['schema_hash'] !== $after['schema_hash'] || $before['protected_stats'] !== $after['protected_stats']) {
                    throw new RuntimeException('Protected data or schema integrity check failed.');
                }
                $deleted = [];
                foreach (CariCleanupInventory::TARGETS as $table) {
                    if ($after['stats'][$table]['count'] !== 0) {
                        throw new RuntimeException('Cleanup incomplete: '.$table);
                    }
                    $deleted[$table] = $before['stats'][$table]['count'];
                }

                $result = [
                    'completed_at' => now()->format('d.m.Y H:i:s'), 'actor_id' => (int) $actor->getKey(),
                    'deleted' => $deleted, 'protected' => $after['protected_stats'],
                    'integrity_verified' => true,
                ];
                $db->statement('SET @cari_cleanup_token = NULL');
                $db->table(CariCleanupInventory::STATE_TABLE)->where('id', 1)->update([
                    'status' => 'completed', 'connection_id' => null, 'nonce_hash' => null,
                    'expires_at' => null, 'completed_at' => now(), 'updated_at' => now(),
                    'report' => json_encode($result, JSON_THROW_ON_ERROR),
                ]);

                return $result;
            }, 1);
            Session::forget(self::SESSION_KEY);

            return $result;
        } catch (ValidationException $exception) {
            Session::forget(self::SESSION_KEY);
            throw $exception;
        } catch (Throwable $exception) {
            Session::forget(self::SESSION_KEY);
            report($exception);
            throw ValidationException::withMessages(['cleanup' => 'Temizlik tamamlanamadı; bu işlemin yaptığı tüm veri değişiklikleri geri alındı. Ön kontrolü yeniden çalıştırın.']);
        } finally {
            // Session variables transaction rollback ile silinmez. Havuzda yeniden
            // kullanılan bağlantıda capability kalmaması için her çıkışta temizlenir.
            try {
                $db->statement('SET @cari_cleanup_token = NULL');
            } finally {
                if ($acquired) {
                    $db->selectOne('SELECT RELEASE_LOCK(?) AS released', [self::LOCK]);
                }
            }
        }
    }

    /** @return array<string, mixed> */
    private function validateToken(User $actor, string $token): array
    {
        try {
            $stored = Session::get(self::SESSION_KEY);
            if (! is_string($stored) || ! hash_equals($stored, hash('sha256', $token))) {
                throw new RuntimeException('Session mismatch.');
            }
            $payload = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($payload) || ($payload['version'] ?? null) !== 1
                || ($payload['actor'] ?? null) !== (int) $actor->getKey()
                || ! is_int($payload['expires'] ?? null) || $payload['expires'] <= now()->getTimestamp()
                || ! is_string($payload['fingerprint'] ?? null)) {
                throw new RuntimeException('Invalid/expired preflight.');
            }

            return $payload;
        } catch (Throwable) {
            Session::forget(self::SESSION_KEY);
            throw ValidationException::withMessages(['preflight_token' => 'Geçerli bir ön kontrol gerekli. Ön kontrolü yeniden çalıştırın.']);
        }
    }

    /** @param callable(Connection): array<string, mixed> $callback
     * @return array<string, mixed>
     */
    private function readSnapshot(callable $callback): array
    {
        $db = DB::connection();
        if ($db->transactionLevel() > 0) {
            return $callback($db);
        }
        $db->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');

        return $db->transaction(fn (): array => $callback($db));
    }

    private function deleteReversalsFirst(Connection $db, string $table, string $parentColumn): void
    {
        $rows = $db->table($table)->get(['id', $parentColumn]);
        $remaining = [];
        foreach ($rows as $row) {
            $remaining[(int) $row->id] = $row->{$parentColumn} === null ? null : (int) $row->{$parentColumn};
        }
        while ($remaining !== []) {
            $referenced = array_flip(array_filter(array_values($remaining), fn (?int $id): bool => $id !== null));
            $leaves = array_diff_key($remaining, $referenced);
            if ($leaves === []) {
                throw new RuntimeException('Cyclic financial reversal dependency.');
            }
            foreach (array_chunk(array_keys($leaves), 500) as $ids) {
                $db->table($table)->whereIn('id', $ids)->delete();
            }
            $remaining = array_diff_key($remaining, $leaves);
        }
    }

    /** @return array<string, mixed> */
    private function unavailable(): array
    {
        return [
            'checked_at' => now()->format('d.m.Y H:i:s'), 'available' => false,
            'blockers' => ['Veritabanı envanteri veya koruma durumu doğrulanamadı. Silme başlatılamaz.'],
            'tables' => [], 'categories' => [], 'dependencies' => [], 'triggers' => [],
            'protected_groups' => [], 'completed' => false, 'completion_report' => null, 'ready' => false,
        ];
    }
}
