<?php

declare(strict_types=1);

/**
 * Deploy finalize script — docs/deploy-lemon.md §6 / docs/deploy-github-actions.md §6.
 *
 * cPanel cron'da dakikada bir çalışır:
 *
 *   * * * * * /usr/local/bin/lsphp /home/derspros/lemon-app/deploy/finalize.php \
 *             >> /home/derspros/lemon-deploy.log 2>&1
 *
 * Cron'u çağıran binary CLI OLMAK ZORUNDA DEĞİLDİR (bu sunucuda LiteSpeed'in
 * `lsphp`'si kullanılıyor ve SAPI'si `cli` değildir). Script bu duruma göre
 * yazılmıştır: çıktıyı `php://stdout`/`php://stderr` akışlarıyla yazar (CLI'a
 * özgü STDOUT/STDERR SABİTLERİNİ KULLANMAZ) ve `artisan` komutlarını kendi
 * bulduğu gerçek bir PHP 8.3 CLI binary'siyle çalıştırır (`resolveCliPhp()`).
 * Böylece cron satırındaki binary değişse bile davranış aynı kalır.
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
 * güvence olarak bir HTTP isteği bağlamında çalışmayı reddeder.
 */

/*
 * Çıktı yardımcıları.
 *
 * `STDOUT`/`STDERR` SABİTLERİ YALNIZCA CLI SAPI'DE TANIMLIDIR. Bu sunucudaki
 * cron, LiteSpeed'in `lsphp` binary'siyle koşuyor ve onun SAPI'si `cli`
 * DEĞİLDİR — sabitleri kullanmak "Undefined constant STDERR" fatal hatası
 * verir (gerçekte yaşandı). `php://stdout` / `php://stderr` akışları ise her
 * SAPI'de açılabilir, bu yüzden çıktı HER ZAMAN bunlar üzerinden yazılır.
 */
function out(string $message): void
{
    $stream = fopen('php://stdout', 'w');
    if ($stream !== false) {
        fwrite($stream, $message);
        fclose($stream);
    }
}

function err(string $message): void
{
    $stream = fopen('php://stderr', 'w');
    if ($stream !== false) {
        fwrite($stream, $message);
        fclose($stream);
    }
}

/*
 * Web'den çağrılmayı reddet.
 *
 * SAPI ADINA bakmak yeterli DEĞİLDİR: cron burada CLI olmayan bir binary
 * (`lsphp`) ile koşuyor, yani `PHP_SAPI !== 'cli'` kontrolü meşru cron
 * çalışmasını da reddederdi. Ayırt edici ölçüt SAPI değil, GERÇEK bir HTTP
 * isteği bağlamının varlığıdır: cron çalışmasında `REQUEST_METHOD` yoktur.
 */
if (isset($_SERVER['REQUEST_METHOD']) || isset($_SERVER['HTTP_HOST'])) {
    http_response_code(404);
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
    err("Kilit dosyası açılamadı: {$lockFile}\n");
    exit(1);
}

// Eşzamanlı iki çalıştırmayı engeller: bir önceki tur hâlâ sürüyorsa
// (ör. migrate uzun sürdüyse) bu çalıştırma sessizce çıkar, kuyruğa girmez.
if (! flock($lockHandle, LOCK_EX | LOCK_NB)) {
    out(date('c')." — başka bir finalize.php çalışması sürüyor, atlanıyor.\n");
    exit(0);
}

$startedAt = date('c');
out("{$startedAt} — deploy-pending bulundu, finalize başlıyor.\n");

/**
 * Bir komutu çalıştırır; çıktı ve çıkış kodunu döner.
 *
 * @param  array<int, string>  $command
 * @return array{output: string, exitCode: int}
 */
function run(array $command, string $cwd): array
{
    if (! function_exists('proc_open')) {
        return ['output' => 'proc_open bu PHP yapılandırmasında devre dışı.', 'exitCode' => 1];
    }

    $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open($command, $descriptors, $pipes, $cwd);

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
 * `artisan`'ı çalıştıracak PHP 8.3 **CLI** binary'sini bulur.
 *
 * Cron'u çağıran binary CLI OLMAYABİLİR: bu sunucuda cron LiteSpeed'in
 * `lsphp`'siyle koşuyor ve onun SAPI'si `cli` değil. Laravel'i web SAPI'li bir
 * binary ile koşturmak (çıktı tamponlama, header, zaman aşımı davranışı) güvenilir
 * değildir, bu yüzden `artisan` HER ZAMAN gerçek bir CLI binary'siyle çalıştırılır.
 *
 * Sürüm de burada doğrulanır — CLAUDE.md §2 uygulamayı 8.3'e KİLİTLER ve
 * `vendor/` ağacı CI'da 8.3 platformuyla çözülür; 8.4 ile koşmak sessiz değil
 * FATAL bir hata üretirdi.
 *
 * @return array{binary: string, version: string}|null
 */
function resolveCliPhp(): ?array
{
    $candidates = [
        '/opt/alt/php83/usr/bin/php',              // CloudLinux Alt-PHP
        '/usr/local/lsws/lsphp83/bin/php',         // LiteSpeed'in kendi PHP kurulumu
        '/opt/cpanel/ea-php83/root/usr/bin/php',   // cPanel EasyApache 4
        '/usr/local/bin/php83',
        '/usr/local/bin/php8.3',
        '/usr/bin/php83',
        '/usr/bin/php8.3',
        '/usr/local/bin/php',
        '/usr/bin/php',
        PHP_BINARY,                                 // son çare: bizi çağıran binary
    ];

    foreach ($candidates as $binary) {
        if ($binary === '' || ! is_file($binary)) {
            continue;
        }

        // Hem sürümü hem SAPI'yi TEK çağrıda sorar; ikisi de uygun değilse eler.
        $probe = run([$binary, '-r', 'echo PHP_VERSION."|".PHP_SAPI;'], __DIR__);

        if ($probe['exitCode'] !== 0) {
            continue;
        }

        [$version, $sapi] = array_pad(explode('|', trim($probe['output'])), 2, '');

        if ($sapi === 'cli' && str_starts_with($version, '8.3.')) {
            return ['binary' => $binary, 'version' => $version];
        }
    }

    return null;
}

$php = resolveCliPhp();

if ($php === null) {
    err(sprintf(
        "%s — DURDURULDU: PHP 8.3 CLI binary'si bulunamadı (bu script %s / SAPI %s ile çağrıldı).\n".
        "İşaret dosyası KORUNDU; doğru binary bulunup cron satırı güncellendikten sonra\n".
        "bir sonraki tur kaldığı yerden devam eder.\n",
        date('c'),
        PHP_BINARY,
        PHP_SAPI,
    ));
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
    exit(1);
}

out("  PHP CLI: {$php['binary']} ({$php['version']})\n");

/**
 * Sıradaki artisan komutunu çalıştırır, günlüğe yazar; başarısızsa kilidi
 * bırakıp script'i BURADA durdurur — işaret dosyası SİLİNMEDEN.
 *
 * @param  array<int, string>  $args
 */
function runStepOrFail(string $phpBinary, string $appRoot, array $args, $lockHandle): void
{
    $label = implode(' ', $args);
    out("  → php artisan {$label}\n");

    $result = run(array_merge([$phpBinary, $appRoot.'/artisan'], $args), $appRoot);
    out($result['output']."\n");

    if ($result['exitCode'] !== 0) {
        err(date('c')." — BAŞARISIZ: 'php artisan {$label}' (exit {$result['exitCode']}). İşaret dosyası KORUNDU, bir sonraki cron tekrar dener.\n");
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
        exit(1);
    }
}

// 1) Bayat cache'i migrate'ten ÖNCE temizle (K-102). Tek çağrıda dört
// :clear komutunu toplayan Laravel kısayolu (CLAUDE.md §15).
runStepOrFail($php['binary'], $appRoot, ['optimize:clear'], $lockHandle);

// 2) Migration.
runStepOrFail($php['binary'], $appRoot, ['migrate', '--force'], $lockHandle);

// 3) Yetki seed'i — HER deploy'da, migrate'ten hemen sonra, atlanmaz (K-104).
runStepOrFail($php['binary'], $appRoot, [
    'db:seed',
    '--class=App\\Modules\\Authorization\\Database\\Seeders\\RolePermissionSeeder',
    '--force',
], $lockHandle);

// 4) Üretim cache'ini yeniden kur.
runStepOrFail($php['binary'], $appRoot, ['config:cache'], $lockHandle);
runStepOrFail($php['binary'], $appRoot, ['route:cache'], $lockHandle);
runStepOrFail($php['binary'], $appRoot, ['view:cache'], $lockHandle);
runStepOrFail($php['binary'], $appRoot, ['event:cache'], $lockHandle);

// Hepsi başarılı — işaret dosyasını sil, bir daha bu deploy için tekrar çalışmasın.
unlink($markerFile);

out(date('c')." — finalize tamamlandı, işaret dosyası silindi.\n");

flock($lockHandle, LOCK_UN);
fclose($lockHandle);
