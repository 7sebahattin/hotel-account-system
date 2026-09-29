<?php

declare(strict_types=1);

/**
 * Server-side staged file promotion. No HTTP route and no external process.
 * The caller holds finalize.lock and has already checked the PHP version.
 */
final class LemonStagedRelease
{
    private string $storage;
    private string $releaseId;
    private string $archive;
    private string $stage;
    private string $backup;
    private array $manifest = [];
    private array $oldPaths = [];
    private array $newPaths = [];
    private array $backedUp = [];
    private bool $promotionStarted = false;

    public function __construct(private readonly string $root, private readonly array $marker)
    {
        $this->storage = $root.'/storage/app';
        $this->releaseId = (string) ($marker['release_id'] ?? '');
        if (! preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_-]{0,63}$/D', $this->releaseId)
            || ! preg_match('/^[a-f0-9]{64}$/D', (string) ($marker['archive_sha256'] ?? ''))
            || ($marker['mode'] ?? '') !== 'staged'
            || ($marker['managed_maintenance'] ?? '') !== '1'
            || ! ctype_digit((string) ($marker['run_attempt'] ?? ''))) {
            throw new RuntimeException('Invalid staged release marker.');
        }
        $this->archive = $this->storage.'/deploy-inbox/'.$this->releaseId.'/release.zip';
        $this->stage = $this->storage.'/deploy-stage/'.$this->releaseId;
        $this->backup = $this->storage.'/deploy-backups/'.$this->releaseId;
    }

    public function releaseId(): string
    {
        return $this->releaseId;
    }

    public function status(string $phase, ?string $error = null): void
    {
        if (! in_array($phase, ['uploaded', 'validating', 'promoting', 'migrating', 'seeding', 'caching', 'completed', 'failed'], true)) {
            throw new RuntimeException('Invalid deployment phase.');
        }
        $data = [
            'release_id' => $this->releaseId,
            'commit' => $this->marker['commit'],
            'phase' => $phase,
            'updated_at' => gmdate('c'),
        ];
        if ($error !== null) {
            $data['error'] = substr($error, 0, 300);
        }
        $this->atomicJson($this->storage.'/deploy-status.json', $data);
    }

    public function assertNotFailed(): void
    {
        $status = $this->readJson($this->storage.'/deploy-status.json', false);
        if (($status['release_id'] ?? null) === $this->releaseId && ($status['phase'] ?? null) === 'failed') {
            throw new RuntimeException('This release failed previously; inspect the deploy log before retrying.');
        }
    }

    public function verifyMaintenance(): void
    {
        $down = $this->readJson($this->root.'/storage/framework/down', true);
        $owner = $this->marker['run_id'].'-'.$this->marker['run_attempt'];
        if (($down['deploy_release_id'] ?? null) !== $owner
            || ! is_file($this->root.'/storage/framework/maintenance.php')) {
            throw new RuntimeException('Managed maintenance owner or shell does not match.');
        }
    }

    public function prepare(): void
    {
        $this->status('validating');
        if (! is_file($this->archive) || is_link($this->archive)) {
            throw new RuntimeException('Release archive is missing.');
        }
        if (! hash_equals($this->marker['archive_sha256'], hash_file('sha256', $this->archive))) {
            throw new RuntimeException('Release archive SHA-256 mismatch.');
        }
        if (is_dir($this->stage)) {
            $this->removeTree($this->stage);
        }
        $this->makeDirectory($this->stage);

        $zip = new ZipArchive;
        if ($zip->open($this->archive) !== true) {
            throw new RuntimeException('Release archive cannot be opened.');
        }
        try {
            $entries = [];
            $totalSize = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $path = $stat['name'];
                if (str_ends_with($path, '/')) {
                    $this->assertSafePath(substr($path, 0, -1), true);
                    continue;
                }
                $this->assertSafePath($path, $path === 'release-manifest.json');
                if (isset($entries[$path])) {
                    throw new RuntimeException('Duplicate archive path.');
                }
                if (! $zip->getExternalAttributesIndex($i, $opsys, $attributes)) {
                    throw new RuntimeException('Archive entry attributes cannot be read.');
                }
                if ($opsys === ZipArchive::OPSYS_UNIX && (($attributes >> 16) & 0170000) === 0120000) {
                    throw new RuntimeException('Archive symlink rejected.');
                }
                $totalSize += $stat['size'];
                if ($stat['size'] > 100 * 1024 * 1024 || $totalSize > 1024 * 1024 * 1024) {
                    throw new RuntimeException('Archive size limit exceeded.');
                }
                $entries[$path] = $stat;
            }
            if (! isset($entries['release-manifest.json'])
                || $entries['release-manifest.json']['size'] > 10 * 1024 * 1024) {
                throw new RuntimeException('Release manifest missing or too large.');
            }
            $raw = $zip->getFromName('release-manifest.json');
            $manifest = is_string($raw) ? json_decode($raw, true, 512, JSON_THROW_ON_ERROR) : null;
            if (! is_array($manifest) || ($manifest['schema_version'] ?? null) !== 1
                || ($manifest['commit'] ?? null) !== $this->marker['commit']
                || ! is_int($manifest['release_version'] ?? null)
                || $manifest['release_version'] < 1
                || ! is_string($manifest['created_at'] ?? null)
                || strtotime($manifest['created_at']) === false
                || ! is_array($manifest['files'] ?? null)) {
                throw new RuntimeException('Invalid release manifest.');
            }
            $listed = [];
            foreach ($manifest['files'] as $file) {
                if (! is_array($file) || ! is_string($file['path'] ?? null)
                    || ! is_string($file['sha256'] ?? null)
                    || ! preg_match('/^[a-f0-9]{64}$/D', $file['sha256'])
                    || ! is_int($file['size'] ?? null) || $file['size'] < 0) {
                    throw new RuntimeException('Invalid manifest file record.');
                }
                $path = $file['path'];
                $this->assertSafePath($path);
                if (isset($listed[$path]) || ! isset($entries[$path])
                    || $entries[$path]['size'] !== $file['size']) {
                    throw new RuntimeException('Manifest/archive file set mismatch.');
                }
                $listed[$path] = $file;
            }
            unset($entries['release-manifest.json']);
            if (count($listed) !== count($entries)) {
                throw new RuntimeException('Archive contains unlisted files.');
            }
            foreach ([
                'artisan', 'bootstrap/app.php', 'bootstrap/providers.php',
                'config/release.php', 'public/index.php', 'public/build/manifest.json',
                'vendor/autoload.php', 'deploy/finalize.php',
                'deploy/staged-release.php', 'deploy/maintenance-trigger.php',
            ] as $required) {
                if (! isset($listed[$required])) {
                    throw new RuntimeException('Required production file is missing: '.$required);
                }
            }
            foreach ($listed as $path => $file) {
                $target = $this->stage.'/'.$path;
                $this->makeDirectory(dirname($target));
                $stream = $zip->getStream($path);
                $output = fopen($target, 'wb');
                if ($stream === false || $output === false) {
                    throw new RuntimeException('Cannot extract staged file.');
                }
                try {
                    if (stream_copy_to_stream($stream, $output) !== $file['size']) {
                        throw new RuntimeException('Staged file size mismatch.');
                    }
                } finally {
                    fclose($stream);
                    fclose($output);
                }
                if (! hash_equals($file['sha256'], hash_file('sha256', $target))) {
                    throw new RuntimeException('Staged file SHA-256 mismatch.');
                }
            }
            $releaseConfig = (string) file_get_contents($this->stage.'/config/release.php');
            if (! preg_match("/'version'\\s*=>\\s*'([0-9]+)'/", $releaseConfig, $matches)
                || (int) $matches[1] !== $manifest['release_version']) {
                throw new RuntimeException('Release version does not match the packaged config.');
            }
            $this->manifest = $manifest;
            $this->newPaths = array_keys($listed);
            $old = $this->readJson($this->storage.'/deploy-manifest.json', false);
            if ($old !== null) {
                if (($old['schema_version'] ?? null) !== 1 || ! is_array($old['files'] ?? null)) {
                    throw new RuntimeException('Previous managed manifest is invalid.');
                }
                foreach ($old['files'] as $file) {
                    if (! is_array($file) || ! is_string($file['path'] ?? null)) {
                        throw new RuntimeException('Previous managed path is invalid.');
                    }
                    $this->assertSafePath($file['path']);
                    if (isset($this->oldPaths[$file['path']])) {
                        throw new RuntimeException('Previous managed manifest has duplicate paths.');
                    }
                    $this->oldPaths[$file['path']] = true;
                }
            }
        } finally {
            $zip->close();
        }
    }

    public function promote(): void
    {
        $this->verifyMaintenance();
        $this->makeDirectory($this->backup);
        $replace = array_fill_keys($this->newPaths, true);
        $delete = array_diff_key($this->oldPaths, $replace);
        $touched = array_keys($replace + $delete);
        foreach ($touched as $path) {
            $target = $this->target($path);
            if (! file_exists($target)) {
                continue;
            }
            if (! is_file($target) || is_link($target)) {
                throw new RuntimeException('Managed target is not a regular file.');
            }
            $copy = $this->backup.'/'.$path;
            $this->makeDirectory(dirname($copy));
            if (! copy($target, $copy) || ! hash_equals(hash_file('sha256', $target), hash_file('sha256', $copy))) {
                throw new RuntimeException('Managed file backup failed.');
            }
            $this->backedUp[$path] = true;
        }
        $this->status('promoting');
        $this->promotionStarted = true;
        try {
            foreach ($this->newPaths as $path) {
                $target = $this->target($path);
                $this->makeDirectory(dirname($target));
                $temp = $target.'.deploy-'.$this->releaseId.'.tmp';
                if (! copy($this->stage.'/'.$path, $temp)
                    || ! hash_equals(hash_file('sha256', $this->stage.'/'.$path), hash_file('sha256', $temp))
                    || ! rename($temp, $target)) {
                    @unlink($temp);
                    throw new RuntimeException('Managed file promotion failed.');
                }
            }
            foreach (array_keys($delete) as $path) {
                $target = $this->target($path);
                if (is_file($target) && ! unlink($target)) {
                    throw new RuntimeException('Obsolete managed file deletion failed.');
                }
            }
        } catch (Throwable $error) {
            $this->restoreBeforeMigration();
            throw $error;
        }
    }

    public function restoreBeforeMigration(): void
    {
        if (! $this->promotionStarted) {
            return;
        }
        foreach ($this->newPaths as $path) {
            $target = $this->target($path);
            if (! isset($this->backedUp[$path]) && is_file($target)) {
                if (! unlink($target)) {
                    throw new RuntimeException('Promotion rollback could not remove a new file.');
                }
            }
        }
        foreach (array_keys($this->backedUp) as $path) {
            $target = $this->target($path);
            $this->makeDirectory(dirname($target));
            if (! copy($this->backup.'/'.$path, $target)
                || ! hash_equals(hash_file('sha256', $this->backup.'/'.$path), hash_file('sha256', $target))) {
                throw new RuntimeException('Promotion rollback could not restore a backed up file.');
            }
        }
        $this->promotionStarted = false;
    }

    public function saveManagedManifest(): void
    {
        $this->atomicJson($this->storage.'/deploy-manifest.json', $this->manifest);
    }

    public function cleanSuccessArtifacts(): void
    {
        $this->removeTree($this->stage);
        $this->removeTree($this->storage.'/deploy-inbox/'.$this->releaseId);
        $base = $this->storage.'/deploy-backups';
        if (! is_dir($base) || is_link($base)) {
            return;
        }
        $backups = [];
        foreach (scandir($base) ?: [] as $name) {
            $path = $base.'/'.$name;
            if (preg_match('/^local-[a-zA-Z0-9_-]{1,57}$/D', $name)
                && is_dir($path) && ! is_link($path)) {
                $backups[] = $path;
            }
        }
        usort($backups, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));
        foreach (array_slice($backups, 3) as $oldBackup) {
            $this->removeTree($oldBackup);
        }
    }

    private function target(string $path): string
    {
        $this->assertSafePath($path);
        $parts = explode('/', $path);
        $walk = $this->root;
        foreach (array_slice($parts, 0, -1) as $part) {
            $walk .= '/'.$part;
            if (is_link($walk)) {
                throw new RuntimeException('Managed target parent is a symlink.');
            }
        }
        return $this->root.'/'.$path;
    }

    private function assertSafePath(string $path, bool $manifestOrDirectory = false): void
    {
        if ($path === '' || str_contains($path, '\\') || str_contains($path, "\0")
            || str_starts_with($path, '/') || preg_match('/^[a-zA-Z]:/', $path)
            || preg_match('#(^|/)(\.|\.\.|\.git|\.github|\.claude|storage|node_modules)(/|$)#i', $path)
            || preg_match('#(^|/)\.env(?:\.|$)#i', $path)
            || str_starts_with($path, 'bootstrap/cache/')
            || $path === 'bootstrap/cache' || str_starts_with($path, 'public/storage/')
            || $path === 'public/storage' || str_contains($path, '//')) {
            throw new RuntimeException('Protected or unsafe release path.');
        }
        if ($path === 'release-manifest.json' && $manifestOrDirectory) {
            return;
        }
        $allowed = $path === 'artisan'
            || $path === 'bootstrap/app.php'
            || $path === 'bootstrap/providers.php'
            || in_array($path, ['deploy/finalize.php', 'deploy/maintenance-trigger.php', 'deploy/staged-release.php'], true)
            || (bool) preg_match('#^(app|config|database|lang|public|vendor)/[^/].*#', $path)
            || str_starts_with($path, 'resources/views/')
            || str_starts_with($path, 'routes/');
        if (! $allowed && ! ($manifestOrDirectory && in_array($path, ['app', 'bootstrap', 'config', 'database', 'lang', 'public', 'vendor', 'resources', 'resources/views', 'routes', 'deploy'], true))) {
            throw new RuntimeException('Release path is outside the production allowlist.');
        }
    }

    private function atomicJson(string $path, array $data): void
    {
        $temp = $path.'.tmp';
        $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        if (file_put_contents($temp, $json, LOCK_EX) === false || ! rename($temp, $path)) {
            throw new RuntimeException('Deployment status/manifest could not be written.');
        }
    }

    private function readJson(string $path, bool $required): ?array
    {
        if (! is_file($path) || is_link($path)) {
            if ($required) {
                throw new RuntimeException('Required deployment metadata is missing.');
            }
            return null;
        }
        $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($data)) {
            throw new RuntimeException('Invalid deployment JSON.');
        }
        return $data;
    }

    private function makeDirectory(string $path): void
    {
        $walk = $path;
        while ($walk !== dirname($walk)) {
            if (is_link($walk)) {
                throw new RuntimeException('Deployment directory has a symlink ancestor.');
            }
            $walk = dirname($walk);
        }
        if (is_link($path) || (! is_dir($path) && ! mkdir($path, 0775, true) && ! is_dir($path))) {
            throw new RuntimeException('Deployment directory could not be created.');
        }
    }

    private function removeTree(string $path): void
    {
        if (! is_dir($path) || is_link($path)) {
            return;
        }
        $resolvedPath = realpath($path);
        $storagePath = realpath($this->storage);
        $resolved = $resolvedPath === false ? false : str_replace('\\', '/', $resolvedPath);
        $storage = $storagePath === false ? false : str_replace('\\', '/', $storagePath);
        $allowed = [
            $storage.'/deploy-stage/',
            $storage.'/deploy-inbox/',
            $storage.'/deploy-backups/',
        ];
        $inside = false;
        if ($resolved !== false && $storage !== false) {
            foreach ($allowed as $prefix) {
                if (str_starts_with($resolved, $prefix)) {
                    $inside = true;
                    break;
                }
            }
        }
        if (! $inside) {
            throw new RuntimeException('Recursive cleanup target is outside deploy storage.');
        }
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
