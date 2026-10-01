<?php

declare(strict_types=1);

// Standalone archive/promotion smoke check; does not boot Laravel or touch production.
require dirname(__DIR__).'/deploy/staged-release.php';

$root = sys_get_temp_dir().'/lemon-stage-smoke-'.bin2hex(random_bytes(6));
mkdir($root.'/storage/app/deploy-inbox/local-smoke', 0777, true);
mkdir($root.'/storage/framework', 0777, true);
mkdir($root.'/app', 0777, true);

try {
    file_put_contents($root.'/storage/framework/down', json_encode(['deploy_release_id' => '1-1']));
    file_put_contents($root.'/storage/framework/maintenance.php', '<?php');
    file_put_contents($root.'/app/test.php', 'old');
    $files = [
        'app/test.php' => 'new',
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
    ];
    $manifest = [
        'schema_version' => 1, 'commit' => str_repeat('a', 40),
        'release_version' => 10, 'created_at' => gmdate('c'),
        'files' => array_map(
            fn (string $path, string $bytes): array => ['path' => $path, 'sha256' => hash('sha256', $bytes), 'size' => strlen($bytes)],
            array_keys($files), array_values($files),
        ),
    ];
    $archive = $root.'/storage/app/deploy-inbox/local-smoke/release.zip';
    $zip = new ZipArchive;
    if ($zip->open($archive, ZipArchive::CREATE) !== true) {
        throw new RuntimeException('Could not create fixture ZIP.');
    }
    foreach ($files as $path => $bytes) {
        $zip->addFromString($path, $bytes);
    }
    $zip->addFromString('release-manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));
    $zip->close();
    $marker = [
        'mode' => 'staged', 'commit' => str_repeat('a', 40), 'run_id' => '1',
        'run_attempt' => '1', 'release_id' => 'local-smoke',
        'archive_sha256' => hash_file('sha256', $archive), 'managed_maintenance' => '1',
    ];
    $release = new LemonStagedRelease($root, $marker);
    $release->prepare();
    $release->promote();
    $release->saveManagedManifest();
    if (file_get_contents($root.'/app/test.php') !== 'new'
        || file_get_contents($root.'/storage/app/deploy-backups/local-smoke/app/test.php') !== 'old') {
        throw new RuntimeException('Promotion/backup smoke check failed.');
    }
    $badMarker = $marker;
    $badMarker['archive_sha256'] = str_repeat('b', 64);
    try {
        (new LemonStagedRelease($root, $badMarker))->prepare();
        throw new RuntimeException('Archive hash mismatch was accepted.');
    } catch (RuntimeException $error) {
        if (! str_contains($error->getMessage(), 'SHA-256 mismatch')) {
            throw $error;
        }
    }

    // A new release ZIP with a protected path must fail before touching the live root.
    $zip = new ZipArchive;
    $zip->open($archive, ZipArchive::OVERWRITE);
    $zip->addFromString('.env', 'do not extract');
    $zip->addFromString('release-manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));
    $zip->close();
    $marker['archive_sha256'] = hash_file('sha256', $archive);
    try {
        (new LemonStagedRelease($root, $marker))->prepare();
        throw new RuntimeException('Protected path was accepted.');
    } catch (RuntimeException $error) {
        if (! str_contains($error->getMessage(), 'unsafe release path')) {
            throw $error;
        }
    }
    if (file_get_contents($root.'/app/test.php') !== 'new' || is_file($root.'/.env')) {
        throw new RuntimeException('Protected path check changed live files.');
    }
    $release->cleanSuccessArtifacts();
    if (is_dir($root.'/storage/app/deploy-stage/local-smoke')
        || is_dir($root.'/storage/app/deploy-inbox/local-smoke')) {
        throw new RuntimeException('Safe stage/inbox cleanup failed.');
    }
    echo "Staged promotion smoke passed.\n";
} finally {
    $resolved = realpath($root);
    $temp = realpath(sys_get_temp_dir());
    if ($resolved !== false && $temp !== false
        && str_starts_with($resolved, $temp.DIRECTORY_SEPARATOR.'lemon-stage-smoke-')) {
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($resolved, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() && ! $item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($resolved);
    }
}
