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
 * ────────────────────────────────────────────────────────────────────────
 * NEDEN ALT SÜREÇ (`php artisan ...`) DEĞİL, AYNI SÜREÇTE?
 *
 * Bu sunucuda (LiteSpeed + CloudLinux Alt-PHP) cron'u çağıran `lsphp`
 * yapılandırmasında **`proc_open` VE `exec` birlikte devre dışıdır**
 * (`disable_functions`). Yani bu script harici bir komut ÇALIŞTIRAMAZ —
 * "doğru PHP binary'sini bulma" sorunu değildir, hiçbir binary çağrılamaz.
 * Bu yüzden artisan komutları Laravel'in konsol çekirdeği ile AYNI SÜREÇTE
 * çalıştırılır (`Kernel::call()`).
 *
 * İki şey elle telafi edilir:
 *  - `APP_RUNNING_IN_CONSOLE=true`: Laravel konsolda olduğunu normalde
 *    PHP_SAPI'den anlar; bu ortam değişkeni framework'ün KENDİ desteklediği
 *    açık kapıdır (`Application::runningInConsole()`), SAPI `litespeed`
 *    olduğu için gereklidir. Bootstrap'TAN ÖNCE yazılır.
 *  - süre/bellek limiti: web SAPI'lerinde sıfır değildir; uzun bir migrate
 *    ortasında kesilme riskini kaldırmak için gevşetilir.
 *
 * Sürüm kapısı: CLAUDE.md §2 uygulamayı 8.3'e KİLİTLER ve `vendor/` ağacı
 * CI'da 8.3 platformuyla çözülür. Aynı süreçte koştuğumuz için "hangi
 * binary" sorusu yoktur; kontrol doğrudan bu sürecin PHP sürümünedir.
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
$lockFile = $appRoot.'/storage/app/finalize.lock';

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

// Kilit beklerken işaret kaldırılmış olabilir. Aynı yayın ikinci kez çalışmaz.
if (! is_file($markerFile)) {
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
    exit(0);
}
$markerContents = file_get_contents($markerFile);
$release = $markerContents === false ? false : parse_ini_string($markerContents, false, INI_SCANNER_RAW);
if (! is_array($release) || ! preg_match('/^[a-f0-9]{40}$/', $release['commit'] ?? '')
    || ! ctype_digit($release['run_id'] ?? '')) {
    err("Geçersiz yayın işareti; işlem yapılmadı.\n");
    exit(1);
}

/**
 * Kilidi bırakır ve verilen çıkış koduyla durur. İşaret dosyasına DOKUNMAZ:
 * hata yolunda marker KORUNUR, bir sonraki cron baştan dener.
 *
 * @param  resource  $lockHandle
 */
function stopWith($lockHandle, int $exitCode): never
{
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
    exit($exitCode);
}

out(date('c')." — deploy-pending bulundu, finalize başlıyor.\n");

// Sürüm kapısı (CLAUDE.md §2). Aynı süreçte koştuğumuz için kontrol edilen
// sürüm, artisan'ın gerçekten koşacağı sürümün TA KENDİSİDİR.
if (! str_starts_with(PHP_VERSION, '8.3.')) {
    err(sprintf(
        "%s — DURDURULDU: bu süreç PHP %s ile koşuyor, uygulama 8.3'e kilitli (CLAUDE.md §2).\n".
        "  Binary: %s (SAPI %s)\n".
        "  Cron satırındaki PHP binary'sini 8.3'e çeviriniz. İşaret dosyası KORUNDU.\n",
        date('c'),
        PHP_VERSION,
        PHP_BINARY,
        PHP_SAPI,
    ));
    stopWith($lockHandle, 1);
}

$autoload = $appRoot.'/vendor/autoload.php';
$bootstrap = $appRoot.'/bootstrap/app.php';

foreach ([$autoload, $bootstrap] as $required) {
    if (! is_file($required)) {
        err(date('c')." — DURDURULDU: gerekli dosya yok: {$required}. İşaret dosyası KORUNDU.\n");
        stopWith($lockHandle, 1);
    }
}

/*
 * Laravel'e konsolda olduğumuzu SÖYLE — bootstrap'tan ÖNCE, çünkü
 * `Application::runningInConsole()` ilk çağrıda sonucu önbelleğe alır.
 * Üç kanalın üçü de yazılır: `Env` deposu putenv/$_ENV/$_SERVER
 * adaptörlerinden hangisinin etkin olduğuna göre okur.
 */
if (function_exists('putenv')) {
    putenv('APP_RUNNING_IN_CONSOLE=true');
}
$_ENV['APP_RUNNING_IN_CONSOLE'] = 'true';
$_SERVER['APP_RUNNING_IN_CONSOLE'] = 'true';

// Web SAPI'sinin süre/bellek limitleri uzun bir migrate'i ortasında kesebilir.
@set_time_limit(0);
@ini_set('memory_limit', '512M');

require $autoload;

try {
    /** @var \Illuminate\Foundation\Application $app */
    $app = require $bootstrap;
    // Config cache, kaldırılan provider'ı kernel bootstrap sırasında yükleyebilir.
    // Temizlik artisan çağrısından ÖNCE yapılmalıdır; optimize:clear tek başına yetmez.
    foreach ([
        $app->getCachedConfigPath(), $app->getCachedServicesPath(),
        $app->getCachedPackagesPath(), $app->getCachedRoutesPath(), $app->getCachedEventsPath(),
    ] as $cachePath) {
        if (is_file($cachePath) && ! unlink($cachePath)) {
            throw new \RuntimeException('Eski bootstrap önbelleği temizlenemedi: '.$cachePath);
        }
    }
    /** @var \Illuminate\Contracts\Console\Kernel $kernel */
    $kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
} catch (\Throwable $e) {
    err(sprintf(
        "%s — DURDURULDU: uygulama başlatılamadı: %s: %s (%s:%d)\nİşaret dosyası KORUNDU.\n",
        date('c'),
        $e::class,
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
    ));
    stopWith($lockHandle, 1);
}

out('  PHP '.PHP_VERSION.' (SAPI '.PHP_SAPI.'), artisan aynı süreçte çalıştırılıyor.'."\n");

/**
 * Sıradaki artisan komutunu AYNI SÜREÇTE çalıştırır, çıktısını günlüğe yazar;
 * başarısızsa kilidi bırakıp script'i BURADA durdurur — işaret dosyası SİLİNMEDEN.
 *
 * @param  array<string, mixed>  $parameters
 * @param  resource  $lockHandle
 */
function runStepOrFail(\Illuminate\Contracts\Console\Kernel $kernel, string $command, array $parameters, $lockHandle): void
{
    $label = trim($command.' '.implode(' ', array_map(
        static fn (string $key, mixed $value): string => $value === true ? $key : "{$key}={$value}",
        array_keys($parameters),
        $parameters,
    )));

    out("  → php artisan {$label}\n");

    $buffer = new \Symfony\Component\Console\Output\BufferedOutput();

    try {
        $exitCode = $kernel->call($command, $parameters, $buffer);
        $output = trim($buffer->fetch());
    } catch (\Throwable $e) {
        $exitCode = 1;
        $output = trim($buffer->fetch())."\n".$e::class.': '.$e->getMessage().' ('.$e->getFile().':'.$e->getLine().')';
    }

    if ($output !== '') {
        out($output."\n");
    }

    if ($exitCode !== 0) {
        err(date('c')." — BAŞARISIZ: 'php artisan {$label}' (exit {$exitCode}). İşaret dosyası KORUNDU, bir sonraki cron tekrar dener.\n");
        stopWith($lockHandle, 1);
    }
}

// 1) Bayat cache'i migrate'ten ÖNCE temizle (K-102). Tek çağrıda dört
// :clear komutunu toplayan Laravel kısayolu (CLAUDE.md §15).
runStepOrFail($kernel, 'optimize:clear', [], $lockHandle);

// 2) Migration.
runStepOrFail($kernel, 'migrate', ['--force' => true], $lockHandle);

// 3) Yetki seed'i — HER deploy'da, migrate'ten hemen sonra, atlanmaz (K-104).
runStepOrFail($kernel, 'db:seed', [
    '--class' => 'App\\Modules\\Authorization\\Database\\Seeders\\RolePermissionSeeder',
    '--force' => true,
], $lockHandle);

// 4) Üretim cache'ini yeniden kur.
runStepOrFail($kernel, 'config:cache', [], $lockHandle);
runStepOrFail($kernel, 'route:cache', [], $lockHandle);
runStepOrFail($kernel, 'view:cache', [], $lockHandle);
runStepOrFail($kernel, 'event:cache', [], $lockHandle);

if (file_get_contents($markerFile) !== $markerContents) {
    err("Yayın işareti çalışma sırasında değişti; yeni işaret silinmedi.\n");
    stopWith($lockHandle, 1);
}

// Yalnızca bu yayının açtığı bakım modu kapatılır; başka bakımın sahipliği alınmaz.
if (($release['managed_maintenance'] ?? '') === '1') {
    $downFile = $appRoot.'/storage/framework/down';
    $down = is_file($downFile) ? json_decode((string) file_get_contents($downFile), true) : null;
    $releaseId = $release['run_id'].'-'.($release['run_attempt'] ?? '1');

    if (! is_file($downFile)) {
        /*
         * Bakım ZATEN kapalı. Beklenen bir durumdur: cron gecikirse workflow'un
         * zaman aşımı kurtarması `down` dosyasını FTP ile siler ki site 503'te
         * kalmasın. Burada kapatılacak bir şey yoktur — bu bir HATA DEĞİLDİR ve
         * yayının geri kalanı normal şekilde tamamlanır.
         */
        out("  Bakım modu zaten kapalı; 'up' çalıştırılmadı.\n");
    } elseif (! is_array($down) || ($down['deploy_release_id'] ?? '') !== $releaseId) {
        /*
         * Bakım BAŞKA bir yayına (ya da bir insana) ait: sahipliği almayız,
         * kapatmayız. Ama bu yayının kendi adımları başarıyla bitti; işareti
         * silip başarı kaydını yazmak DOĞRUDUR. Aksi hâlde marker sonsuza dek
         * kalır, her cron turu aynı hatayı tekrarlar ve yayın asla kapanmazdı.
         */
        err("  UYARI: bakım modu başka bir yayına ait; kapatılmadı (elle kontrol edin).\n");
    } else {
        runStepOrFail($kernel, 'up', [], $lockHandle);
    }
}

$receipt = $appRoot.'/storage/app/deploy-completed.json';
$receiptData = json_encode([
    'commit' => $release['commit'], 'run_id' => $release['run_id'],
    'run_attempt' => $release['run_attempt'] ?? '1', 'completed_at' => date('c'),
], JSON_THROW_ON_ERROR);
if (file_put_contents($receipt.'.tmp', $receiptData, LOCK_EX) === false
    || ! rename($receipt.'.tmp', $receipt) || ! unlink($markerFile)) {
    err("Yayın sonucu kaydedilemedi; işaretin durumunu kontrol edin.\n");
    stopWith($lockHandle, 1);
}

out(date('c')." — finalize tamamlandı, işaret dosyası silindi.\n");

stopWith($lockHandle, 0);
