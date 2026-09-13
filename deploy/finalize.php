<?php

declare(strict_types=1);

/**
 * Deploy finalize script — docs/deploy-lemon.md §6 / docs/deploy-github-actions.md §6.
 *
 * cPanel cron'da dakikada bir çalışır:
 *
 *   * * * * * /usr/local/bin/ea-php83 /home/derspros/lemon-app/deploy/finalize.php \
 *             >> /home/derspros/lemon-deploy.log 2>&1
 *
 * GitHub Actions deploy işi, gerçek bir aktarım tamamlandığında
 * `storage/app/deploy-pending` işaret dosyasını AYRI bir FTP yüklemesiyle
 * bırakır (storage/ normal aktarımdan HARİÇ TUTULUR — kullanıcı verisi
 * kazara ezilmesin diye). Bu script işaret dosyasını görünce:
 *
 *   1. ESKİ cache'i temizler (config/route/view/event) — MIGRATE'TEN ÖNCE.
 *      Gerekçe (K-102): sunucuda kalan bayat bir bootstrap/cache/config.php,
 *      bu deploy'da yeni eklenen bir modülün Service Provider'ını
 *      taşımıyorsa `migrate` o modülün migration'larını hiç GÖRMEZ.
 *   2. `migrate --force` çalıştırır.
 *   3. `db:seed --class="App\Modules\Authorization\Database\Seeders\
 *      RolePermissionSeeder" --force` çalıştırır — ATLANMAZ (K-104):
 *      koddaki yeni bir Permission case'i bu adım koşulmadan production'da
 *      "no permission named X for guard web" hatası üretir.
 *   4. Üretim cache'ini yeniden kurar (config/route/view/event).
 *
 * Herhangi bir adım BAŞARISIZ olursa script hemen durur, işaret dosyasını
 * SİLMEZ (bir sonraki cron çalıştırması baştan dener) ve hatayı günlüğe
 * (stdout/stderr → cron yönlendirmesiyle lemon-deploy.log) yazar.
 *
 * İnternete açık bir uç DEĞİLDİR: Laravel kökü DocumentRoot'un (public/)
 * DIŞINDADIR (Mimari A), bu script web'den hiçbir şekilde ERİŞİLEMEZ. Ek
 * güvence olarak yalnızca CLI'dan çalışır.
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "finalize.php yalnızca CLI'dan çalışır.\n");
    exit(1);
}

$appRoot = dirname(__DIR__);
$markerFile = $appRoot.'/storage/app/deploy-pending';
$lockFile = __DIR__.'/finalize.lock';

// İşaret dosyası yoksa yapacak bir şey yok — cron her dakika koşar, bu normal.
if (! is_file($markerFile)) {
    exit(0);
}

$lockHandle = fopen($lockFile, 'c');

if ($lockHandle === false) {
    fwrite(STDERR, "Kilit dosyası açılamadı: {$lockFile}\n");
    exit(1);
}

// Eşzamanlı iki çalıştırmayı engeller: bir önceki tur hâlâ sürüyorsa
// (ör. migrate uzun sürdüyse) bu çalıştırma sessizce çıkar, kuyruğa girmez.
if (! flock($lockHandle, LOCK_EX | LOCK_NB)) {
    fwrite(STDOUT, date('c')." — başka bir finalize.php çalışması sürüyor, atlanıyor.\n");
    exit(0);
}

$startedAt = date('c');
fwrite(STDOUT, "{$startedAt} — deploy-pending bulundu, finalize başlıyor.\n");

/**
 * `php artisan <komut>` çalıştırır; çıktı/çıkış kodunu döner.
 *
 * @param  array<int, string>  $args
 * @return array{output: string, exitCode: int}
 */
function runArtisan(string $appRoot, array $args): array
{
    $command = array_merge([PHP_BINARY, $appRoot.'/artisan'], $args);
    $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];

    $process = proc_open($command, $descriptors, $pipes, $appRoot);

    if (! is_resource($process)) {
        return ['output' => 'proc_open başarısız oldu.', 'exitCode' => 1];
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    return ['output' => trim($stdout."\n".$stderr), 'exitCode' => $exitCode];
}

/**
 * Sıradaki komutu çalıştırır, günlüğe yazar; başarısızsa kilidi bırakıp
 * script'i BURADA durdurur — işaret dosyası SİLİNMEDEN.
 *
 * @param  array<int, string>  $args
 */
function runStepOrFail(string $appRoot, array $args, $lockHandle): void
{
    $label = implode(' ', $args);
    fwrite(STDOUT, "  → php artisan {$label}\n");

    $result = runArtisan($appRoot, $args);
    fwrite(STDOUT, $result['output']."\n");

    if ($result['exitCode'] !== 0) {
        fwrite(STDERR, date('c')." — BAŞARISIZ: 'php artisan {$label}' (exit {$result['exitCode']}). İşaret dosyası KORUNDU, bir sonraki cron tekrar dener.\n");
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
        exit(1);
    }
}

// 1) Bayat cache'i migrate'ten ÖNCE temizle (K-102). Tek çağrıda dört
// :clear komutunu toplayan Laravel kısayolu (CLAUDE.md §15).
runStepOrFail($appRoot, ['optimize:clear'], $lockHandle);

// 2) Migration.
runStepOrFail($appRoot, ['migrate', '--force'], $lockHandle);

// 3) Yetki seed'i — HER deploy'da, migrate'ten hemen sonra, atlanmaz (K-104).
runStepOrFail($appRoot, [
    'db:seed',
    '--class=App\\Modules\\Authorization\\Database\\Seeders\\RolePermissionSeeder',
    '--force',
], $lockHandle);

// 4) Üretim cache'ini yeniden kur.
runStepOrFail($appRoot, ['config:cache'], $lockHandle);
runStepOrFail($appRoot, ['route:cache'], $lockHandle);
runStepOrFail($appRoot, ['view:cache'], $lockHandle);
runStepOrFail($appRoot, ['event:cache'], $lockHandle);

// Hepsi başarılı — işaret dosyasını sil, bir daha bu deploy için tekrar çalışmasın.
unlink($markerFile);

fwrite(STDOUT, date('c')." — finalize tamamlandı, işaret dosyası silindi.\n");

flock($lockHandle, LOCK_UN);
fclose($lockHandle);
