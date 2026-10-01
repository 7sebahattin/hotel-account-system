<?php

declare(strict_types=1);

use App\Modules\Branding\Http\Controllers\BrandLogoController;
use Illuminate\Support\Facades\Route;

Route::get('branding/logo/{variant}', [BrandLogoController::class, 'file'])
    ->whereIn('variant', ['on-light', 'on-dark'])
    ->name('branding.logo.file');

Route::middleware(['auth', 'can:brand.manage'])->prefix('branding/logos')->name('branding.logos.')->group(function (): void {
    Route::get('/', [BrandLogoController::class, 'edit'])->name('edit');
    Route::put('{variant}', [BrandLogoController::class, 'update'])
        ->whereIn('variant', ['on-light', 'on-dark'])->name('update');
});
