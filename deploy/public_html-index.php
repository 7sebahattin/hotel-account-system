<?php

/**
 * cPanel public_html giriş dosyası.
 *
 * Laravel kökü web'den erişilemeyen ../app dizinindedir. Bu dosya
 * public_html/index.php olarak kopyalanır. Yol farklıysa $laravel değişkeni
 * güncellenir; başka hiçbir yeri değiştirmeyin.
 */

$laravel = __DIR__.'/../app';

// Bakım modu
if (file_exists($maintenance = $laravel.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $laravel.'/vendor/autoload.php';

$app = require_once $laravel.'/bootstrap/app.php';

$app->handleRequest(Illuminate\Http\Request::capture());
