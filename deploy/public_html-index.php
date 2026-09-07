<?php

/**
 * cPanel DocumentRoot giriş dosyası — YALNIZCA "Mimari B" için.
 *
 * Bu dosya, DocumentRoot cPanel'de değiştirilemediğinde kullanılır.
 * DocumentRoot Laravel'in public/ dizinine yönlendirilebiliyorsa (Mimari A,
 * önerilen) bu dosyaya HİÇ İHTİYAÇ YOKTUR ve Laravel'in kendi
 * public/index.php dosyası olduğu gibi kullanılır.
 * Ayrıntı ve karşılaştırma: docs/deploy-lemon.md §2.
 *
 * Değiştirilecek TEK yer aşağıdaki $laravel satırıdır. Yol GÖRELİDİR:
 * mutlak yol yazmayın, hesap taşındığında kırılır.
 *
 * Lemon yerleşimi:
 *   DocumentRoot : /home/derspros/lemon.derspros.com.tr/   (bu dosya burada)
 *   Laravel kökü : /home/derspros/lemon-app/               (__DIR__/../lemon-app)
 */

$laravel = __DIR__.'/../lemon-app';

// Bakım modu
if (file_exists($maintenance = $laravel.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $laravel.'/vendor/autoload.php';

$app = require_once $laravel.'/bootstrap/app.php';

$app->handleRequest(Illuminate\Http\Request::capture());
