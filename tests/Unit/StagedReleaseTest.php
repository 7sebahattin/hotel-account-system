<?php

declare(strict_types=1);

namespace Tests\Unit;

use LemonStagedRelease;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZipArchive;

require_once dirname(__DIR__, 2).'/deploy/staged-release.php';

class StagedReleaseTest extends TestCase
{
    private string $root;
    private array $marker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir().'/lemon-stage-test-'.bin2hex(random_bytes(6));
        foreach (['storage/app/deploy-inbox/local-test', 'storage/framework', 'app'] as $dir) {
            mkdir($this->root.'/'.$dir, 0777, true);
        }
        file_put_contents($this->root.'/storage/framework/down', json_encode(['deploy_release_id' => '123-1']));
        file_put_contents($this->root.'/storage/framework/maintenance.php', '<?php');
        file_put_contents($this->root.'/storage/app/deploy-pending', 'marker');
        $this->marker = [
            'mode' => 'staged', 'commit' => str_repeat('a', 40), 'run_id' => '123',
            'run_attempt' => '1', 'release_id' => 'local-test',
            'archive_sha256' => str_repeat('0', 64), 'managed_maintenance' => '1',
        ];
    }

    protected function tearDown(): void
    {
        $this->deleteFixture($this->root);
        parent::tearDown();
    }

    private function deleteFixture(string $path): void
    {
        $resolved = realpath($path);
        $temp = realpath(sys_get_temp_dir());
        if ($resolved === false || $temp === false || ! is_dir($resolved) || is_link($path)
            || ! str_starts_with($resolved, $temp.DIRECTORY_SEPARATOR.'lemon-stage-test-')) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($resolved, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() && ! $item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($resolved);
    }

    /** @param array<string, string> $files */
    private function archive(array $files = ['app/example.php' => '<?php return 1;'], ?array $manifestFiles = null): LemonStagedRelease
    {
        $files = array_replace($files, [
            'artisan' => '<?php',
            'bootstrap/app.php' => '<?php',
            'bootstrap/providers.php' => '<?php return [];',
            'config/release.php' => "<?php return ['version' => '10'];",
            'public/index.php' => '<?php',
            'public/build/manifest.json' => '{}',
            'vendor/autoload.php' => '<?php',
            'deploy/finalize.php' => '<?php',
            'deploy/staged-release.php' => '<?php',
            'deploy/maintenance-trigger.php' => '<?php',
        ]);
        $records = [];
        foreach ($files as $path => $contents) {
            $records[] = ['path' => $path, 'sha256' => hash('sha256', $contents), 'size' => strlen($contents)];
        }
        if ($manifestFiles !== null) {
            $required = [
                'artisan', 'bootstrap/app.php', 'bootstrap/providers.php', 'config/release.php',
                'public/index.php', 'public/build/manifest.json', 'vendor/autoload.php',
                'deploy/finalize.php', 'deploy/staged-release.php', 'deploy/maintenance-trigger.php',
            ];
            foreach ($records as $record) {
                if (in_array($record['path'], $required, true)) {
                    $manifestFiles[] = $record;
                }
            }
        }
        $manifest = [
            'schema_version' => 1, 'commit' => $this->marker['commit'],
            'release_version' => 10, 'created_at' => gmdate('c'),
            'files' => $manifestFiles ?? $records,
        ];
        $archive = $this->root.'/storage/app/deploy-inbox/local-test/release.zip';
        $zip = new ZipArchive;
        self::assertTrue($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        foreach ($files as $path => $contents) {
            $zip->addFromString($path, $contents);
        }
        $zip->addFromString('release-manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));
        $zip->close();
        $this->marker['archive_sha256'] = hash_file('sha256', $archive);
        return new LemonStagedRelease($this->root, $this->marker);
    }

    #[Test]
    public function valid_marker_archive_and_manifest_are_accepted(): void
    {
        $release = $this->archive();
        $release->verifyMaintenance();
        $release->prepare();
        self::assertFileExists($this->root.'/storage/app/deploy-stage/local-test/app/example.php');
        self::assertSame('validating', json_decode(file_get_contents($this->root.'/storage/app/deploy-status.json'), true)['phase']);
    }

    #[Test]
    public function invalid_release_ids_are_rejected_before_filesystem_access(): void
    {
        foreach (['../outside', '/tmp/outside', 'a/b', '', str_repeat('x', 65)] as $id) {
            $marker = $this->marker;
            $marker['release_id'] = $id;
            try {
                new LemonStagedRelease($this->root, $marker);
                self::fail('Unsafe release ID accepted.');
            } catch (RuntimeException $error) {
                self::assertSame('Invalid staged release marker.', $error->getMessage());
            }
        }
    }

    #[Test]
    public function traversal_and_protected_paths_are_rejected(): void
    {
        foreach (['../.env', '/tmp/evil.php', 'app/../../.env', '.env',
            'storage/app/private.txt', 'bootstrap/cache/config.php', 'public/storage/avatar.jpg',
            'deploy/local-release.env', 'tests/evil.php'] as $path) {
            $release = $this->archive([$path => 'bad']);
            try {
                $release->prepare();
                self::fail('Unsafe path accepted: '.$path);
            } catch (RuntimeException $error) {
                self::assertFileDoesNotExist($this->root.'/.env');
                self::assertFileExists($this->root.'/storage/app/deploy-pending');
            }
        }
    }

    #[Test]
    public function missing_archive_and_archive_hash_mismatch_are_rejected(): void
    {
        $release = new LemonStagedRelease($this->root, $this->marker);
        $this->expectException(RuntimeException::class);
        $release->prepare();
    }

    #[Test]
    public function archive_hash_mismatch_is_rejected_without_promotion(): void
    {
        $this->archive();
        $this->marker['archive_sha256'] = str_repeat('b', 64);
        $release = new LemonStagedRelease($this->root, $this->marker);
        $this->expectExceptionMessage('SHA-256 mismatch');
        $release->prepare();
    }

    #[Test]
    public function manifest_hash_and_unlisted_files_are_rejected(): void
    {
        $bad = [['path' => 'app/example.php', 'sha256' => str_repeat('b', 64), 'size' => 15]];
        $release = $this->archive(['app/example.php' => '<?php return 1;'], $bad);
        try {
            $release->prepare();
            self::fail('Bad file digest accepted.');
        } catch (RuntimeException $error) {
            self::assertStringContainsString('SHA-256 mismatch', $error->getMessage());
            self::assertFileExists($this->root.'/storage/app/deploy-pending');
        }
        $release = $this->archive(['app/example.php' => 'new', 'app/unlisted.php' => 'extra'], [
            ['path' => 'app/example.php', 'sha256' => hash('sha256', 'new'), 'size' => 3],
        ]);
        $this->expectExceptionMessage('unlisted files');
        $release->prepare();
    }

    #[Test]
    public function promotion_backs_up_overwrites_and_deletes_only_previous_managed_files(): void
    {
        file_put_contents($this->root.'/app/example.php', 'old');
        file_put_contents($this->root.'/app/obsolete.php', 'obsolete');
        file_put_contents($this->root.'/app/user.php', 'untouched');
        file_put_contents($this->root.'/.env', 'secret');
        file_put_contents($this->root.'/storage/app/upload.txt', 'user data');
        file_put_contents($this->root.'/storage/app/deploy-manifest.json', json_encode([
            'schema_version' => 1,
            'files' => [
                ['path' => 'app/example.php'], ['path' => 'app/obsolete.php'],
            ],
        ]));
        $release = $this->archive();
        $release->prepare();
        $release->promote();
        self::assertSame('<?php return 1;', file_get_contents($this->root.'/app/example.php'));
        self::assertFileDoesNotExist($this->root.'/app/obsolete.php');
        self::assertSame('old', file_get_contents($this->root.'/storage/app/deploy-backups/local-test/app/example.php'));
        self::assertSame('obsolete', file_get_contents($this->root.'/storage/app/deploy-backups/local-test/app/obsolete.php'));
        self::assertSame('untouched', file_get_contents($this->root.'/app/user.php'));
        self::assertSame('secret', file_get_contents($this->root.'/.env'));
        self::assertSame('user data', file_get_contents($this->root.'/storage/app/upload.txt'));
        $release->saveManagedManifest();
        self::assertSame('app/example.php', json_decode(file_get_contents($this->root.'/storage/app/deploy-manifest.json'), true)['files'][0]['path']);
    }

    #[Test]
    public function unsafe_previous_managed_path_never_deletes_protected_file(): void
    {
        file_put_contents($this->root.'/.env', 'secret');
        file_put_contents($this->root.'/storage/app/deploy-manifest.json', json_encode([
            'schema_version' => 1, 'files' => [['path' => '.env']],
        ]));
        $release = $this->archive();
        $this->expectExceptionMessage('Protected or unsafe');
        try {
            $release->prepare();
        } finally {
            self::assertSame('secret', file_get_contents($this->root.'/.env'));
        }
    }

    #[Test]
    public function filesystem_failure_restores_backup_before_migration_and_keeps_marker(): void
    {
        file_put_contents($this->root.'/app/example.php', 'old');
        file_put_contents($this->root.'/config', 'blocks directory creation');
        $release = $this->archive([
            'app/example.php' => 'new',
            'config/new.php' => '<?php',
        ]);
        $release->prepare();
        try {
            $release->promote();
            self::fail('Expected promotion failure.');
        } catch (RuntimeException $error) {
            self::assertSame('old', file_get_contents($this->root.'/app/example.php'));
            self::assertFileExists($this->root.'/storage/app/deploy-pending');
            self::assertFileDoesNotExist($this->root.'/config/new.php');
        }
    }

    #[Test]
    public function maintenance_owner_must_match_before_promotion(): void
    {
        $release = $this->archive();
        $release->prepare();
        file_put_contents($this->root.'/storage/framework/down', json_encode(['deploy_release_id' => 'other']));
        $this->expectExceptionMessage('owner');
        $release->promote();
    }
}
