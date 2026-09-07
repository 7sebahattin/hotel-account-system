<?php

declare(strict_types=1);

use App\Modules\Authorization\Enums\Permission;
use App\Modules\Stays\Http\Controllers\StayController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('stays')->name('stays.')->group(function (): void {
    Route::get('/', [StayController::class, 'index'])
        ->middleware('can:'.Permission::StaysView->value)
        ->name('index');

    Route::get('create', [StayController::class, 'create'])
        ->middleware('can:'.Permission::StaysCreate->value)
        ->name('create');

    Route::post('/', [StayController::class, 'store'])
        ->middleware('can:'.Permission::StaysCreate->value)
        ->name('store');

    Route::get('{stay}', [StayController::class, 'show'])
        ->middleware('can:'.Permission::StaysView->value)
        ->name('show');

    Route::get('{stay}/edit', [StayController::class, 'edit'])
        ->middleware('can:'.Permission::StaysUpdate->value)
        ->name('edit');

    Route::put('{stay}', [StayController::class, 'update'])
        ->middleware('can:'.Permission::StaysUpdate->value)
        ->name('update');

    // V1'de konaklama SİLİNMEZ, iptal edilir. Yetki adı stays.delete kalır.
    Route::patch('{stay}/cancel', [StayController::class, 'cancel'])
        ->middleware('can:'.Permission::StaysDelete->value)
        ->name('cancel');
});
