<?php

/*
 * Bakım sayfasına gömülen TEK SEFERLİK finalize tetikleyicisi.
 * (K-108, CLAUDE.md §15 istisnası — kullanıcı onayıyla, 14 Eylül 2026.)
 *
 * NASIL YERLEŞİR: `.github/workflows/deploy.yml`, aktarımdan önce bakım
 * modunu açarken `storage/framework/maintenance.php` dosyasını BU DOSYA +
 * Laravel'in kendi bakım stub'ı olarak birleştirip yükler. Yani bu kod
 * YALNIZCA bakım modu açıkken, yalnızca o dosyanın içinde yaşar.
 *
 * NE YAPAR: bekleyen bir yayın işareti (`storage/app/deploy-pending`) varsa,
 * bakım süresince gelen İLK istek finalize zincirini çalıştırır
 * (cache temizle → migrate → yetki seed'i → cache kur → `artisan up`).
 * `up`, hem `down` hem de `maintenance.php` dosyasını siler — yani bu
 * tetikleyici işini bitirince KENDİNİ YOK EDER.
 *
 * NEDEN İNTERNETE AÇIK BİR UÇ DEĞİLDİR (CLAUDE.md §15):
 *  - Ortada URL, route, token veya parametre YOKTUR; dışarıdan çağrılabilecek
 *    bir adres oluşmaz.
 *  - Çalışmasının ÖN KOŞULU, yalnızca FTP erişimi olan deploy hattının
 *    bırakabildiği işaret dosyasıdır. Ziyaretçi migration BAŞLATAMAZ; yalnızca
 *    ZATEN BEKLEYEN işin birkaç saat yerine hemen koşmasını sağlar.
 *  - `finalize.php`'nin kendi kilidi (`flock`), sürüm kapısı ve adım adım
 *    hata yönetimi aynen geçerlidir; burada kopyalanmaz.
 *
 * NEDEN GEREKLİ: bu hosting'in cron'u güvenilmez — ölçülen gecikme 3 saate
 * kadar çıktı (13-14 Eylül 2026). Bakım modu aktarımdan önce açıldığı için
 * site, cron uyanana kadar bakım ekranında kalıyordu. Cron YEDEK olarak
 * yerinde kalır; hangisi önce davranırsa finalize'ı o koşturur.
 */

(static function (): void {
    // Bu dosya `storage/framework/maintenance.php` olarak yaşar.
    $appRoot = dirname(__DIR__, 2);
    $marker = $appRoot.'/storage/app/deploy-pending';

    // Bekleyen yayın yok → normal bakım davranışı (aşağıdaki Laravel stub'ı).
    if (! is_file($marker)) {
        return;
    }

    /*
     * Soğuma penceresi: finalize başarısız olursa işaret KORUNUR. Bu kontrol
     * olmasa her ziyaretçi isteği aynı başarısız migrate'i yeniden denerdi.
     * Kilit (flock) eşzamanlı çağrıları zaten eler; bu, ARDIŞIK çağrılar
     * içindir.
     */
    $cooldown = $appRoot.'/storage/app/finalize-attempt';
    if (is_file($cooldown) && (time() - (int) filemtime($cooldown)) < 60) {
        return;
    }
    @touch($cooldown);

    define('LEMON_FINALIZE_EMBEDDED', true);
    // Cron'un yazdığı günlüğün AYNISI: iki yol tek dosyada birleşir.
    define('LEMON_FINALIZE_LOG', dirname($appRoot).'/lemon-deploy.log');

    /*
     * `finalize.php` her yolda `exit()` ile biter. Kapanışta ziyaretçiye ne
     * döneceğimizi burada belirleriz:
     *  - Bakım kalktıysa (finalize başarılı) → aynı adrese 302; ziyaretçi
     *    yeni sürümü görür. Bu ikinci istek TEMİZ bir süreçtir, tek istekte
     *    iki kez Laravel başlatılmaz.
     *  - Hâlâ bakımdaysa (finalize başarısız) → normal bakım yanıtı (503).
     */
    register_shutdown_function(static function () use ($appRoot): void {
        if (headers_sent()) {
            return;
        }

        $down = $appRoot.'/storage/framework/down';

        if (! is_file($down)) {
            $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            if ($method === 'GET' || $method === 'HEAD') {
                header('Location: '.($_SERVER['REQUEST_URI'] ?? '/'), true, 302);

                return;
            }

            // POST/PUT gibi istekleri yeniden yönlendirmek metodu değiştirir;
            // bunun yerine "birazdan tekrar dene" demek doğrudur.
            header('Retry-After: 1', true, 503);

            return;
        }

        // Finalize tamamlanamadı: ziyaretçi boş sayfa değil bakım ekranı görür.
        $payload = json_decode((string) @file_get_contents($down), true);
        header('Retry-After: '.($payload['retry'] ?? 60), true, $payload['status'] ?? 503);
        echo is_array($payload) && isset($payload['template'])
            ? $payload['template']
            : 'Sistem güncelleniyor. Lütfen kısa bir süre sonra yeniden deneyin.';
    });

    require $appRoot.'/deploy/finalize.php';
})();
