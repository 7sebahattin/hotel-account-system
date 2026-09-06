<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
| Modül route'ları kendi ServiceProvider'ları tarafından yüklenir
| (App\Modules\Core\Providers\ModuleServiceProvider). Bu dosya yalnızca
| modüle ait olmayan uygulama kökü için kullanılır.
*/

Route::middleware('auth')->group(function (): void {
    Route::view('/', 'home')->name('home');
});
