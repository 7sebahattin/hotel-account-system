<?php

declare(strict_types=1);

namespace Tests\Feature\Stays;

use App\Modules\Authorization\Enums\RoleName;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StayDuplicateConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    #[Test]
    public function two_read_committed_connections_cannot_create_the_same_new_guest_and_day(): void
    {
        $this->seedRoles();
        $actor = $this->userWithRole(RoleName::Admin);
        $barrier = 'stay-test:'.bin2hex(random_bytes(12));
        $this->assertSame(1, (int) DB::selectOne('SELECT GET_LOCK(?, 10) AS acquired', [$barrier])->acquired);

        $workers = [];
        try {
            $mysql = config('database.connections.mysql');
            $environment = array_merge(getenv() ?: [], [
                'APP_ENV' => 'testing',
                'DB_CONNECTION' => 'mysql',
                'DB_URL' => '',
                'DB_HOST' => (string) $mysql['host'],
                'DB_PORT' => (string) $mysql['port'],
                'DB_DATABASE' => (string) $mysql['database'],
                'DB_USERNAME' => (string) $mysql['username'],
                'DB_PASSWORD' => (string) $mysql['password'],
            ]);

            for ($i = 0; $i < 2; $i++) {
                $process = proc_open(
                    [PHP_BINARY, base_path('tests/Support/stay-duplicate-worker.php'), (string) $actor->id, $barrier],
                    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                    $pipes,
                    base_path(),
                    $environment,
                );
                if (! is_resource($process)) {
                    $this->fail('Concurrent worker could not be started.');
                }
                fclose($pipes[0]);
                $workers[] = [$process, $pipes];
            }

            foreach ($workers as [, $pipes]) {
                $this->assertSame("ready\n", fgets($pipes[1]));
            }
        } finally {
            DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$barrier]);
        }

        try {
            $results = [];
            foreach ($workers as [$process, $pipes]) {
                $results[] = trim((string) stream_get_contents($pipes[1]));
                $error = trim((string) stream_get_contents($pipes[2]));
                fclose($pipes[1]);
                fclose($pipes[2]);
                $this->assertSame(0, proc_close($process), $error);
            }

            sort($results);
            $this->assertSame(['created', 'duplicate'], $results);
            $this->assertDatabaseCount('stays', 1);
            $this->assertDatabaseCount('ledger_entries', 1);
            $this->assertDatabaseCount('payments', 0);
            $this->assertDatabaseCount('companies', 1);
            $this->assertDatabaseCount('cash_movements', 0);
        } finally {
            // DatabaseMigrations bütün migration'ları geri sarar. Bu testin
            // bilinçli olarak oluşturduğu bireysel cari, veri-koruma amaçlı
            // account_type migration down() kapısını tetiklememeli ve sonraki
            // testlerin şemasını yarım rollback durumunda bırakmamalıdır.
            DB::table('companies')->where('account_type', 'individual')->update(['account_type' => 'company']);
        }
    }
}
