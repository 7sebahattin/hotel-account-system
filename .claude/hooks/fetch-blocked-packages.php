<?php

/**
 * ORTAMA ÖZGÜ ONARIM — yalnızca Claude Code on the web container'ı içindir.
 *
 * Bu ortamda GitHub zipball indirmeleri (api.github.com, codeload) proxy
 * tarafından 403 ile engellenir; git clone ise çalışır. Bazı paketlerin
 * (ör. phpstan/phpstan) packagist'te source tanımı yoktur, yalnızca zipball
 * vardır — bu yüzden composer install bu ortamda tamamlanamaz.
 *
 * Script, composer.lock'ta yazan sürümü git ile getirir ve composer'ın
 * installed.json kaydına ekler. Yalnızca vendor/ dizinini değiştirir; proje
 * dosyalarına, composer.json'a veya lock'a dokunmaz. CI ve geliştirici
 * makinelerinde normal composer install çalıştığı için orada devreye girmez.
 */
declare(strict_types=1);

$root = dirname(__DIR__, 2);

$lock = json_decode((string) file_get_contents($root.'/composer.lock'), true, 512, JSON_THROW_ON_ERROR);
$installedPath = $root.'/vendor/composer/installed.json';
$installed = json_decode((string) file_get_contents($installedPath), true, 512, JSON_THROW_ON_ERROR);

$alreadyInstalled = array_column($installed['packages'], 'name');
$repaired = [];

foreach (['packages' => false, 'packages-dev' => true] as $section => $isDev) {
    foreach ($lock[$section] ?? [] as $package) {
        $name = $package['name'];
        $target = $root.'/vendor/'.$name;

        // Dizin var ve doluysa dokunma.
        if (is_dir($target) && (new FilesystemIterator($target))->valid()) {
            continue;
        }

        $repo = $package['source']['url']
            ?? gitUrlFromDist($package['dist']['url'] ?? null);

        if ($repo === null) {
            fwrite(STDERR, "  ✗ {$name}: git kaynağı türetilemedi\n");

            continue;
        }

        @mkdir(dirname($target), 0o755, true);
        exec(sprintf(
            'git clone --quiet --depth 1 --branch %s %s %s 2>&1',
            escapeshellarg($package['version']),
            escapeshellarg($repo),
            escapeshellarg($target),
        ), $output, $status);

        if ($status !== 0) {
            fwrite(STDERR, "  ✗ {$name}: git clone başarısız\n");

            continue;
        }

        exec('rm -rf '.escapeshellarg($target.'/.git'));

        if (! in_array($name, $alreadyInstalled, true)) {
            $entry = $package;
            $entry['install-path'] = '../'.$name;
            $installed['packages'][] = $entry;

            if ($isDev && ! in_array($name, $installed['dev-package-names'] ?? [], true)) {
                $installed['dev-package-names'][] = $name;
            }
        }

        // Paketin bin dosyaları vendor/bin altına bağlanır.
        foreach ($package['bin'] ?? [] as $bin) {
            $link = $root.'/vendor/bin/'.basename($bin);
            @unlink($link);
            @symlink('../'.$name.'/'.$bin, $link);
            @chmod($target.'/'.$bin, 0o755);
        }

        $repaired[] = $name;
    }
}

if ($repaired === []) {
    exit(0);
}

file_put_contents($installedPath, json_encode($installed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

echo '  git ile getirilen paketler: '.implode(', ', $repaired)."\n";

/** api.github.com zipball adresinden git deposu adresini türetir. */
function gitUrlFromDist(?string $distUrl): ?string
{
    if ($distUrl === null || ! preg_match('#^https://api\.github\.com/repos/([^/]+)/([^/]+)/zipball#', $distUrl, $m)) {
        return null;
    }

    return sprintf('https://github.com/%s/%s.git', $m[1], $m[2]);
}
