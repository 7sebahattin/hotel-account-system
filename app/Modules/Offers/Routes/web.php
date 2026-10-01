<?php

declare(strict_types=1);

use App\Modules\Authorization\Enums\Permission;
use App\Modules\Offers\Http\Controllers\OfferController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:'.Permission::OffersView->value])
    ->prefix('offers')->name('offers.')->group(function (): void {
        Route::get('/', [OfferController::class, 'index'])->name('index');
        Route::get('create', [OfferController::class, 'create'])->middleware('can:'.Permission::OffersCreate->value)->name('create');
        Route::post('/', [OfferController::class, 'store'])->middleware('can:'.Permission::OffersCreate->value)->name('store');
        Route::get('{offer}', [OfferController::class, 'show'])->name('show');
        Route::get('{offer}/print', [OfferController::class, 'print'])->name('print');
        Route::get('{offer}/edit', [OfferController::class, 'edit'])->middleware('can:'.Permission::OffersUpdate->value)->name('edit');
        Route::put('{offer}', [OfferController::class, 'update'])->middleware('can:'.Permission::OffersUpdate->value)->name('update');
    });
