<?php

declare(strict_types=1);

use App\Modules\Dashboard\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

/*
| Pano uygulamanın kökündedir. Route adı `home` olarak KORUNUR: kenar çubuğu,
| giriş sonrası yönlendirme ve mevcut testler bu adı kullanır.
*/
Route::middleware('auth')->group(function (): void {
    Route::get('/', DashboardController::class)->name('home');
});
