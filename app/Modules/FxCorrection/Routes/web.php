<?php

declare(strict_types=1);

use App\Modules\Authorization\Enums\Permission;
use App\Modules\FxCorrection\Http\Controllers\FxCorrectionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:'.Permission::FxCorrectHistorical->value])
    ->prefix('fx-correction')->name('fx-correction.')->group(function (): void {
        Route::get('/', [FxCorrectionController::class, 'index'])->name('index');
        Route::patch('stays/{stay}', [FxCorrectionController::class, 'updateStay'])->name('stays.update');
        Route::patch('payments/{payment}', [FxCorrectionController::class, 'updatePayment'])->name('payments.update');
    });
