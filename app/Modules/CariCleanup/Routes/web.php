<?php

declare(strict_types=1);

use App\Modules\CariCleanup\Http\Controllers\CariCleanupController;
use App\Modules\CariCleanup\Services\CariCleanupService;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:view,'.CariCleanupService::class])
    ->prefix('admin/cari-temizleme')->name('cari-cleanup.')->group(function (): void {
        Route::get('/', [CariCleanupController::class, 'index'])->name('index');
        Route::post('on-kontrol', [CariCleanupController::class, 'preflight'])->name('preflight');
        Route::post('sil', [CariCleanupController::class, 'execute'])
            ->middleware(['can:execute,'.CariCleanupService::class, 'throttle:3,1'])
            ->name('execute');
    });
