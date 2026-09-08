<?php

declare(strict_types=1);

use App\Modules\Authorization\Enums\Permission;
use App\Modules\CashBook\Http\Controllers\CashBookController;
use App\Modules\CashBook\Http\Controllers\CashMovementController;
use App\Modules\CashBook\Http\Controllers\CashSessionController;
use App\Modules\CashBook\Http\Controllers\CashTransferController;
use Illuminate\Support\Facades\Route;

/*
 * Kasa defteri (Gün 10).
 *
 * SİLME UCU YOKTUR: hiçbir route DELETE metodu kullanmaz. Yanlış kayıt ters
 * hareketle düzeltilir (kullanıcı talimatı §3).
 *
 * Her route hem yetki middleware'i (kaba filtre) hem controller içinde policy
 * (kayıt bazlı karar) taşır — CLAUDE.md §7.1'deki katmanlı savunma.
 */
Route::middleware('auth')->prefix('cash')->name('cashbook.')->group(function (): void {
    Route::get('/', CashBookController::class)
        ->middleware('can:'.Permission::CashBookView->value)
        ->name('index');

    // --------------------------------------------------------------- vardiya
    Route::get('sessions/open', [CashSessionController::class, 'create'])
        ->middleware('can:'.Permission::CashBookOperate->value)
        ->name('sessions.create');

    Route::post('sessions', [CashSessionController::class, 'store'])
        ->middleware('can:'.Permission::CashBookOperate->value)
        ->name('sessions.store');

    Route::get('sessions/{session}/close', [CashSessionController::class, 'closeForm'])
        ->middleware('can:'.Permission::CashBookOperate->value)
        ->name('sessions.close');

    Route::patch('sessions/{session}/close', [CashSessionController::class, 'close'])
        ->middleware('can:'.Permission::CashBookOperate->value)
        ->name('sessions.close.store');

    Route::get('sessions/{session}', [CashSessionController::class, 'show'])
        ->middleware('can:'.Permission::CashBookView->value)
        ->name('sessions.show');

    Route::get('sessions/{session}/print', [CashSessionController::class, 'print'])
        ->middleware('can:'.Permission::CashBookView->value)
        ->name('sessions.print');

    // -------------------------------------------------------------- hareket
    Route::get('movements/create', [CashMovementController::class, 'create'])
        ->middleware('can:'.Permission::CashBookOperate->value)
        ->name('movements.create');

    Route::post('movements', [CashMovementController::class, 'store'])
        ->middleware('can:'.Permission::CashBookOperate->value)
        ->name('movements.store');

    Route::patch('movements/{movement}/reverse', [CashMovementController::class, 'reverse'])
        ->middleware('can:'.Permission::CashBookOperate->value)
        ->name('movements.reverse');

    // ----------------------------------------------------------------- devir
    Route::get('transfers/create', [CashTransferController::class, 'create'])
        ->middleware('can:'.Permission::CashBookOperate->value)
        ->name('transfers.create');

    Route::post('transfers', [CashTransferController::class, 'store'])
        ->middleware('can:'.Permission::CashBookOperate->value)
        ->name('transfers.store');

    Route::patch('transfers/{transfer}/accept', [CashTransferController::class, 'accept'])
        ->middleware('can:'.Permission::CashBookOperate->value)
        ->name('transfers.accept');

    Route::patch('transfers/{transfer}/cancel', [CashTransferController::class, 'cancel'])
        ->middleware('can:'.Permission::CashBookOperate->value)
        ->name('transfers.cancel');

    // ------------------------------------------------------ günlük defter
    Route::get('day', [CashSessionController::class, 'day'])
        ->middleware('can:'.Permission::CashBookViewAll->value)
        ->name('day');
});
