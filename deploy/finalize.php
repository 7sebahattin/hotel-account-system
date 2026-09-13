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
 * `lsphp`'si kullanılıyor ve SAPI'si `cli` değil, `litespeed`). Script bu
 * duruma göre yazılmıştır:
 *
 *   - Çıktıyı `php://stdout`/`php://stderr` akışlarıyla yazar; CLI'a özgü
 *     STDOUT/STDERR SABİTLERİNİ KULLANMAZ (bu sunucuda gerçekten fatal verdi).
 *   - `artisan` komutlarını, kendi bulduğu ve GERÇEKTEN SINAYARAK doğruladığı
 *     bir PHP 8.3 binary'siyle çalıştırır (`resolvePhpBinary()`). Gerçek bir
 *     CLI binary'si varsa o tercih edilir; yoksa bizi çağıran `lsphp` gibi
 *     CLI olmayan bir binary son çare olarak kullanılır (aşağıya bakınız).
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
 * `proc_open` paylaşımlı hosting'de `disable_functions` ile kapatılmış
 * olabilir; bu yüzden `exec()` ikinci yol olarak durur. İkisi de kapalıysa
 * bu bilgi AÇIKÇA günlüğe düşer (sessizce "binary bulunamadı" demek, gerçek
 * nedeni gizlerdi — ilk sürümde tam olarak bu oldu).
 *
 * @param  array<int, string>  $command
 * @param  array<string, string>  $env  komuta EKLENECEK ortam değişkenleri
 * @return array{output: string, exitCode: int}
 */
function run(array $command, string $cwd, array $env = []): array
{
    if (function_exists('proc_open')) {
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];

        // Boş $env geçilirse ortam OLDUĞU GİBİ miras alınır (null); dolu ise
        // mevcut ortamla BİRLEŞTİRİLİR — proc_open verilen diziyi ortamın
        // TAMAMI sayar, yalnızca eklenenleri geçmek PATH'i bile silerdi.
        $processEnv = $env === [] ? null : array_merge(getenv(), $env);

        $process = proc_open($command, $descriptors, $pipes, $cwd, $processEnv);

        if (is_resource($process)) {
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);

            return ['output' => trim($stdout."\n".$stderr), 'exitCode' => $exitCode];
        }

        return ['output' => 'proc_open başarısız oldu.', 'exitCode' => 1];
    }

    if (function_exists('exec')) {
        $prefix = '';
        foreach ($env as $name => $value) {
            $prefix .= $name.'='.escapeshellarg($value).' ';
        }

        $line = $prefix.implode(' ', array_map('escapeshellarg', $command)).' 2>&1';
        $lines = [];
        $exitCode = 1;
        exec('cd '.escapeshellarg($cwd).' && '.$line, $lines, $exitCode);

        return ['output' => trim(implode("\n", $lines)), 'exitCode' => $exitCode];
    }

    return [
        'output' => 'Bu PHP yapılandırmasında hem proc_open hem exec devre dışı — '.
            'finalize script\'i harici komut çalıştıramaz.',
        'exitCode' => 1,
    ];
}

/**
 * `artisan`'ı çalıştıracak PHP 8.3 binary'sini bulur ve GERÇEKTEN SINAR.
 *
 * Neden sınama gerekiyor: bu sunucuda cron, LiteSpeed'in `lsphp`'siyle
 * (CloudLinux Alt-PHP, SAPI `litespeed`) koşuyor. `lsphp` CGI/LSAPI tabanlı
 * olduğu için `-r` gibi CLI'a özgü seçenekleri desteklemeyebilir ve
 * `register_argc_argv` kapalıysa scripte ARGÜMAN TAŞIMAZ — argüman taşımayan
 * bir binary ile `artisan migrate --force` çalıştırmak sessizce `artisan list`
 * koşturmak demektir. Bu yüzden aday binary GERÇEK bir dosya çalıştırılarak,
 * GERÇEK bir argümanla sınanır.
 *
 * Sürüm kapısı MUTLAKTIR: CLAUDE.md §2 uygulamayı 8.3'e KİLİTLER ve `vendor/`
 * ağacı CI'da 8.3 platformuyla çözülür.
 *
 * @param  array<int, string>  $diagnostics  denenen her adayın sonucu buraya yazılır
 * @return array{binary: string, version: string, sapi: string, args: array<int, string>, env: array<string, string>}|null
 */
function resolvePhpBinary(string $probeFile, array &$diagnostics): ?array
{
    $candidates = [
        // Gerçek CLI binary'leri önce denenir.
        '/opt/alt/php83/usr/bin/php',              // CloudLinux Alt-PHP (CLI)
        '/opt/alt/php83/usr/bin/php-cli',
        '/opt/alt/php83/usr/bin/php83',
        '/opt/cpanel/ea-php83/root/usr/bin/php',   // cPanel EasyApache 4
        '/usr/local/lsws/lsphp83/bin/php',
        '/usr/local/bin/php83',
        '/usr/local/bin/php8.3',
        '/usr/local/bin/ea-php83',
        '/usr/bin/php83',
        '/usr/bin/php8.3',
        '/usr/local/bin/php',
        '/usr/bin/php',
        // CLI olmayan (LSAPI) binary'ler — son çare, aşağıda ayrıca uyarılır.
        '/opt/alt/php83/usr/bin/lsphp',
        '/usr/local/lsws/lsphp83/bin/lsphp',
        '/usr/local/bin/lsphp',
        PHP_BINARY,                                 // bizi çağıran binary
    ];

    // Alt-PHP kurulumları sürüm sürüm ayrı dizinlerdedir; yukarıdaki sabit
    // liste ıskalarsa dizinin kendisi taranır (yalnızca 8.3 dizini).
    $globbed = glob('/opt/alt/php83/usr/bin/*php*');
    if (is_array($globbed)) {
        $candidates = array_merge($candidates, $globbed);
    }

    $seen = [];
    $fallbackMatch = null;

    foreach ($candidates as $binary) {
        if ($binary === '' || isset($seen[$binary])) {
            continue;
        }
        $seen[$binary] = true;

        if (! is_file($binary)) {
            $diagnostics[] = "    {$binary} — yok";

            continue;
        }

        // Önce sade çağrı, olmazsa register_argc_argv açık hâliyle: CGI/LSAPI
        // SAPI'lerinde bu ini varsayılan olarak KAPALIDIR ve argüman taşınmaz.
        foreach ([[], ['-d', 'register_argc_argv=1']] as $extraArgs) {
            $result = run(array_merge([$binary], $extraArgs, [$probeFile, 'ARGTEST']), __DIR__);

            // Çıktı CGI benzeri SAPI'lerde HTTP başlığı taşıyabilir; işaretleri
            // arayan bir regex bu gürültüden etkilenmez.
            if (! preg_match('/__PROBE__(.+?)\|(.+?)\|(\d)__END__/', $result['output'], $m)) {
                $reason = $result['exitCode'] !== 0
                    ? "çalıştırılamadı (exit {$result['exitCode']})"
                    : 'sınama çıktısı okunamadı';
                $diagnostics[] = sprintf(
                    '    %s%s — %s: %s',
                    $binary,
                    $extraArgs === [] ? '' : ' -d register_argc_argv=1',
                    $reason,
                    substr(str_replace("\n", ' ', $result['output']), 0, 160),
                );

                continue;
            }

            [, $version, $sapi, $argvOk] = $m;

            if (! str_starts_with($version, '8.3.')) {
                $diagnostics[] = "    {$binary} — PHP {$version} ({$sapi}): sürüm 8.3 DEĞİL, kullanılmaz";

                continue 2; // bu binary'nin sürümü sabittir, ikinci deneme anlamsız
            }

            if ($argvOk !== '1') {
                $diagnostics[] = sprintf(
                    '    %s%s — PHP %s (%s): argüman taşımıyor',
                    $binary,
                    $extraArgs === [] ? '' : ' -d register_argc_argv=1',
                    $version,
                    $sapi,
                );

                continue;
            }

            $match = [
                'binary' => $binary,
                'version' => $version,
                'sapi' => $sapi,
                'args' => $extraArgs,
                'env' => [],
            ];

            if ($sapi === 'cli') {
                $diagnostics[] = "    {$binary} — PHP {$version} (cli): UYGUN";

                return $match;
            }

            $diagnostics[] = "    {$binary} — PHP {$version} ({$sapi}): uygun ama CLI değil (yedek)";

            if ($fallbackMatch === null) {
                /*
                 * CLI olmayan SAPI'de iki şey elle telafi edilir:
                 *  - `APP_RUNNING_IN_CONSOLE=true`: Laravel konsolda olduğunu
                 *    normalde PHP_SAPI'den anlar; bu ortam değişkeni framework'ün
                 *    KENDİ desteklediği açık kapıdır (Application::runningInConsole).
                 *  - süre/bellek limiti: web SAPI'lerinde sıfır değildir, uzun bir
                 *    migrate ortasında kesilme riski doğar.
                 */
                $match['args'] = array_merge($extraArgs, [
                    '-d', 'max_execution_time=0',
                    '-d', 'memory_limit=512M',
                ]);
                $match['env'] = ['APP_RUNNING_IN_CONSOLE' => 'true'];
                $fallbackMatch = $match;
            }

            continue 2;
        }
    }

    // CLI binary'si bulunsaydı döngü içinde DÖNERDİ; buraya gelindiyse en iyi
    // ihtimalle CLI olmayan bir yedek vardır.
    return $fallbackMatch;
}

/*
 * Sınama scripti: SAPI fark etmeksizin çalışan GERÇEK bir dosya. `-r` CGI/LSAPI
 * binary'lerinde desteklenmediği için sınama bir dosya üzerinden yapılır.
 */
$probeFile = __DIR__.'/.finalize-probe.php';
$probeWritten = file_put_contents(
    $probeFile,
    '<?php $a = isset($_SERVER["argv"]) && in_array("ARGTEST", (array) $_SERVER["argv"], true) ? 1 : 0;'.
    ' echo "__PROBE__".PHP_VERSION."|".PHP_SAPI."|".$a."__END__";'."\n",
);

if ($probeWritten === false) {
    err(date('c')." — DURDURULDU: sınama dosyası yazılamadı ({$probeFile}). İşaret dosyası KORUNDU.\n");
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
    exit(1);
}

/** @var array<int, string> $diagnostics */
$diagnostics = [];
$php = resolvePhpBinary($probeFile, $diagnostics);

@unlink($probeFile);

if ($php === null) {
    err(sprintf(
        "%s — DURDURULDU: çalıştırılabilir bir PHP 8.3 binary'si bulunamadı.\n".
        "  Bu script: %s (SAPI %s, PHP %s)\n".
        "  Denenen adaylar:\n%s\n".
        "İşaret dosyası KORUNDU; doğru binary bulunup cron satırı güncellendikten sonra\n".
        "bir sonraki tur kaldığı yerden devam eder.\n",
        date('c'),
        PHP_BINARY,
        PHP_SAPI,
        PHP_VERSION,
        implode("\n", $diagnostics),
    ));
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
    exit(1);
}

out("  PHP: {$php['binary']} ({$php['version']}, SAPI {$php['sapi']})\n");

if ($php['sapi'] !== 'cli') {
    out("  UYARI: gerçek bir CLI binary'si bulunamadı; artisan CLI olmayan bu binary ile\n".
        "  çalıştırılıyor (APP_RUNNING_IN_CONSOLE=true, süre/bellek limiti kaldırıldı).\n");
}

/**
 * Sıradaki artisan komutunu çalıştırır, günlüğe yazar; başarısızsa kilidi
 * bırakıp script'i BURADA durdurur — işaret dosyası SİLİNMEDEN.
 *
 * @param  array{binary: string, version: string, sapi: string, args: array<int, string>, env: array<string, string>}  $php
 * @param  array<int, string>  $args
 * @param  resource  $lockHandle
 */
function runStepOrFail(array $php, string $appRoot, array $args, $lockHandle): void
{
    $label = implode(' ', $args);
    out("  → php artisan {$label}\n");

    $command = array_merge([$php['binary']], $php['args'], [$appRoot.'/artisan'], $args);
    $result = run($command, $appRoot, $php['env']);
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
runStepOrFail($php, $appRoot, ['optimize:clear'], $lockHandle);

// 2) Migration.
runStepOrFail($php, $appRoot, ['migrate', '--force'], $lockHandle);

// 3) Yetki seed'i — HER deploy'da, migrate'ten hemen sonra, atlanmaz (K-104).
runStepOrFail($php, $appRoot, [
    'db:seed',
    '--class=App\\Modules\\Authorization\\Database\\Seeders\\RolePermissionSeeder',
    '--force',
], $lockHandle);

// 4) Üretim cache'ini yeniden kur.
runStepOrFail($php, $appRoot, ['config:cache'], $lockHandle);
runStepOrFail($php, $appRoot, ['route:cache'], $lockHandle);
runStepOrFail($php, $appRoot, ['view:cache'], $lockHandle);
runStepOrFail($php, $appRoot, ['event:cache'], $lockHandle);

// Hepsi başarılı — işaret dosyasını sil, bir daha bu deploy için tekrar çalışmasın.
unlink($markerFile);

out(date('c')." — finalize tamamlandı, işaret dosyası silindi.\n");

flock($lockHandle, LOCK_UN);
fclose($lockHandle);
