<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class DeployFinalizeTest extends TestCase
{
    private string $fixture;

    protected function setUp(): void
    {
        parent::setUp();
        $project = dirname(__DIR__, 2);
        $this->fixture = $project.'/storage/framework/testing/finalize-'.bin2hex(random_bytes(6));
        foreach (['deploy', 'storage/app', 'storage/framework', 'bootstrap/cache', 'vendor'] as $dir) {
            mkdir($this->fixture.'/'.$dir, 0777, true);
        }
        copy($project.'/deploy/finalize.php', $this->fixture.'/deploy/finalize.php');
        copy($project.'/deploy/staged-release.php', $this->fixture.'/deploy/staged-release.php');
        file_put_contents($this->fixture.'/vendor/autoload.php', '<?php require '.var_export($project.'/vendor/autoload.php', true).';');
        file_put_contents($this->fixture.'/bootstrap/app.php', <<<'PHP'
<?php
class FixtureKernel extends Illuminate\Foundation\Console\Kernel {
    public function __construct() {}
    public function bootstrap() {
        foreach (glob(getenv('FINALIZE_FIXTURE').'/bootstrap/cache/*.php') as $file) {
            throw new RuntimeException('RemovedCashBookProvider from stale cache: '.$file);
        }
        file_put_contents(getenv('FINALIZE_FIXTURE').'/booted', 'yes');
    }
    public function call($command, array $parameters = [], $outputBuffer = null) {
        $root = getenv('FINALIZE_FIXTURE');
        file_put_contents($root.'/steps.jsonl', json_encode([$command, $parameters])."\n", FILE_APPEND);
        if (getenv('FINALIZE_FAIL') === $command) return 1;
        if ($command === 'up') unlink($root.'/storage/framework/down');
        return 0;
    }
}
$app = new Illuminate\Foundation\Application(dirname(__DIR__));
$app->instance(Illuminate\Contracts\Console\Kernel::class, new FixtureKernel());
return $app;
PHP);
        file_put_contents($this->fixture.'/storage/app/deploy-pending',
            'commit='.str_repeat('a', 40)."\nrun_id=123\nrun_attempt=2\nmanaged_maintenance=1\n");
        file_put_contents($this->fixture.'/storage/framework/down', json_encode(['deploy_release_id' => '123-2']));
        foreach (['config.php', 'services.php', 'packages.php', 'routes-v7.php', 'events.php'] as $file) {
            file_put_contents($this->fixture.'/bootstrap/cache/'.$file, '<?php throw new RuntimeException("RemovedCashBookProvider");');
        }
    }

    protected function tearDown(): void
    {
        $real = realpath($this->fixture);
        $parent = realpath(dirname(__DIR__, 2).'/storage/framework/testing');
        if ($real !== false && $parent !== false && str_starts_with($real, $parent.DIRECTORY_SEPARATOR.'finalize-')) {
            (new Filesystem)->deleteDirectory($real);
        }
        parent::tearDown();
    }

    private function runFinalize(?string $fail = null): Process
    {
        $process = new Process([PHP_BINARY, $this->fixture.'/deploy/finalize.php'], $this->fixture, [
            'FINALIZE_FIXTURE' => $this->fixture, 'FINALIZE_FAIL' => $fail ?? '',
            'APP_CONFIG_CACHE' => false, 'APP_SERVICES_CACHE' => false,
            'APP_PACKAGES_CACHE' => false, 'APP_ROUTES_CACHE' => false, 'APP_EVENTS_CACHE' => false,
        ]);
        $process->setTimeout(30)->run();

        return $process;
    }

    #[Test]
    public function removed_provider_cache_is_cleared_before_boot_and_matching_receipt_is_written(): void
    {
        $process = $this->runFinalize();
        $this->assertSame(0, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
        $steps = array_map(fn ($line) => json_decode($line, true)[0], file($this->fixture.'/steps.jsonl', FILE_IGNORE_NEW_LINES));
        $this->assertSame(['optimize:clear', 'migrate', 'db:seed', 'config:cache', 'route:cache', 'view:cache', 'event:cache', 'up'], $steps);
        $receipt = json_decode(file_get_contents($this->fixture.'/storage/app/deploy-completed.json'), true);
        $this->assertSame(str_repeat('a', 40), $receipt['commit']);
        $this->assertSame('123', $receipt['run_id']);
        $this->assertSame('2', $receipt['run_attempt']);
        $this->assertFileDoesNotExist($this->fixture.'/storage/framework/down');
        $this->assertFileDoesNotExist($this->fixture.'/storage/app/deploy-pending');
        $this->assertSame(0, $this->runFinalize()->getExitCode());
        $this->assertCount(8, file($this->fixture.'/steps.jsonl'));
    }

    #[Test]
    public function failed_migration_keeps_maintenance_and_marker_and_does_not_seed_or_report_success(): void
    {
        $this->assertSame(1, $this->runFinalize('migrate')->getExitCode());
        $this->assertFileExists($this->fixture.'/storage/framework/down');
        $this->assertFileExists($this->fixture.'/storage/app/deploy-pending');
        $this->assertFileDoesNotExist($this->fixture.'/storage/app/deploy-completed.json');
        $this->assertCount(2, file($this->fixture.'/steps.jsonl'));
    }

    #[Test]
    public function other_maintenance_owner_is_not_reopened(): void
    {
        file_put_contents($this->fixture.'/storage/framework/down', json_encode(['deploy_release_id' => 'other']));
        $this->assertSame(1, $this->runFinalize()->getExitCode());
        $this->assertFileExists($this->fixture.'/storage/framework/down');
        $this->assertFileDoesNotExist($this->fixture.'/storage/app/deploy-completed.json');
    }

    /**
     * Bakım sayfasına gömülü tetikleyici (K-108) finalize'ı bir HTTP isteği
     * bağlamında çağırır. İki şey kanıtlanır: (1) `LEMON_FINALIZE_EMBEDDED`
     * beyan edildiğinde HTTP reddi devreye girmez, (2) çıktı stdout'a —
     * yani ziyaretçinin sayfasına — DEĞİL, günlük dosyasına yazılır.
     */
    #[Test]
    public function embedded_trigger_finalizes_in_http_context_and_writes_to_the_log_not_the_response(): void
    {
        $log = $this->fixture.'/lemon-deploy.log';
        $process = new Process([PHP_BINARY, '-r', implode('', [
            '$_SERVER["REQUEST_METHOD"]="GET";',
            '$_SERVER["HTTP_HOST"]="lemon.example";',
            'define("LEMON_FINALIZE_EMBEDDED", true);',
            'define("LEMON_FINALIZE_LOG", '.var_export($log, true).');',
            'require '.var_export($this->fixture.'/deploy/finalize.php', true).';',
        ])], $this->fixture, [
            'FINALIZE_FIXTURE' => $this->fixture, 'FINALIZE_FAIL' => '',
            'APP_CONFIG_CACHE' => false, 'APP_SERVICES_CACHE' => false,
            'APP_PACKAGES_CACHE' => false, 'APP_ROUTES_CACHE' => false, 'APP_EVENTS_CACHE' => false,
        ]);
        $process->setTimeout(30)->run();

        $this->assertSame(0, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
        $this->assertSame('', $process->getOutput(), 'Gömülü modda hiçbir şey yanıt gövdesine yazılmamalı');
        $this->assertStringContainsString('finalize tamamlandı', (string) file_get_contents($log));
        $this->assertFileDoesNotExist($this->fixture.'/storage/app/deploy-pending');
        $this->assertFileDoesNotExist($this->fixture.'/storage/framework/down');
        $this->assertFileExists($this->fixture.'/storage/app/deploy-completed.json');
    }

    #[Test]
    public function malformed_marker_and_http_requests_do_not_boot_the_application(): void
    {
        file_put_contents($this->fixture.'/storage/app/deploy-pending', 'bad-marker');
        $this->assertSame(1, $this->runFinalize()->getExitCode());
        $this->assertFileDoesNotExist($this->fixture.'/booted');
        $process = new Process([PHP_BINARY, '-r', '$_SERVER["REQUEST_METHOD"]="GET"; require '.var_export($this->fixture.'/deploy/finalize.php', true).';']);
        $process->run();
        $this->assertSame(1, $process->getExitCode());
        $this->assertFileDoesNotExist($this->fixture.'/booted');
    }

    #[Test]
    public function staged_marker_promotes_then_runs_legacy_artisan_chain_and_writes_receipt(): void
    {
        mkdir($this->fixture.'/storage/app/deploy-inbox/local-test', 0777, true);
        mkdir($this->fixture.'/app', 0777, true);
        file_put_contents($this->fixture.'/storage/framework/maintenance.php', '<?php');
        $content = '<?php return 10;';
        $files = [
            'app/new.php' => $content,
            'artisan' => '<?php',
            'bootstrap/app.php' => file_get_contents($this->fixture.'/bootstrap/app.php'),
            'bootstrap/providers.php' => '<?php return [];',
            'config/release.php' => "<?php return ['version' => '10'];",
            'public/index.php' => '<?php',
            'public/build/manifest.json' => '{}',
            'vendor/autoload.php' => file_get_contents($this->fixture.'/vendor/autoload.php'),
            'deploy/finalize.php' => file_get_contents($this->fixture.'/deploy/finalize.php'),
            'deploy/staged-release.php' => file_get_contents($this->fixture.'/deploy/staged-release.php'),
            'deploy/maintenance-trigger.php' => '<?php',
        ];
        $manifest = [
            'schema_version' => 1,
            'commit' => str_repeat('a', 40),
            'release_version' => 10,
            'created_at' => gmdate('c'),
            'files' => array_map(
                fn (string $path, string $bytes): array => ['path' => $path, 'sha256' => hash('sha256', $bytes), 'size' => strlen($bytes)],
                array_keys($files), array_values($files),
            ),
        ];
        $archive = $this->fixture.'/storage/app/deploy-inbox/local-test/release.zip';
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($archive, \ZipArchive::CREATE | \ZipArchive::OVERWRITE));
        foreach ($files as $path => $bytes) {
            $zip->addFromString($path, $bytes);
        }
        $zip->addFromString('release-manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));
        $zip->close();
        file_put_contents($this->fixture.'/storage/app/deploy-pending',
            "mode=staged\ncommit=".str_repeat('a', 40)."\nrun_id=123\nrun_attempt=2\n".
            "managed_maintenance=1\nrelease_id=local-test\narchive_sha256=".hash_file('sha256', $archive)."\n");

        $process = $this->runFinalize();
        $this->assertSame(0, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
        $this->assertSame($content, file_get_contents($this->fixture.'/app/new.php'));
        $this->assertFileExists($this->fixture.'/storage/app/deploy-completed.json');
        $this->assertFileDoesNotExist($this->fixture.'/storage/app/deploy-pending');
        $this->assertSame('completed', json_decode(file_get_contents($this->fixture.'/storage/app/deploy-status.json'), true)['phase']);
        $this->assertSame('app/new.php', json_decode(file_get_contents($this->fixture.'/storage/app/deploy-manifest.json'), true)['files'][0]['path']);
        $steps = array_map(fn ($line) => json_decode($line, true)[0], file($this->fixture.'/steps.jsonl', FILE_IGNORE_NEW_LINES));
        $this->assertSame(['optimize:clear', 'migrate', 'db:seed', 'config:cache', 'route:cache', 'view:cache', 'event:cache', 'up'], $steps);
    }

    #[Test]
    public function staged_failure_preserves_marker_and_maintenance_without_booting_laravel(): void
    {
        file_put_contents($this->fixture.'/storage/framework/maintenance.php', '<?php');
        file_put_contents($this->fixture.'/storage/app/deploy-pending',
            "mode=staged\ncommit=".str_repeat('a', 40)."\nrun_id=123\nrun_attempt=2\n".
            "managed_maintenance=1\nrelease_id=local-test\narchive_sha256=".str_repeat('b', 64)."\n");
        $this->assertSame(1, $this->runFinalize()->getExitCode());
        $this->assertFileExists($this->fixture.'/storage/app/deploy-pending');
        $this->assertFileExists($this->fixture.'/storage/framework/down');
        $this->assertFileDoesNotExist($this->fixture.'/booted');
        $this->assertSame('failed', json_decode(file_get_contents($this->fixture.'/storage/app/deploy-status.json'), true)['phase']);
    }
}
