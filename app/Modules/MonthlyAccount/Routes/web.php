<?php

declare(strict_types=1);

use App\Modules\Authorization\Enums\Permission;
use App\Modules\MonthlyAccount\Http\Controllers\MonthlyAccountController;
use App\Modules\MonthlyAccount\Http\Controllers\MonthlyPaymentProviderController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('monthly-account')->name('monthly-account.')->group(function (): void {
    Route::middleware('can:'.Permission::MonthlyAccountManageProviders->value)
        ->prefix('providers')->name('providers.')->group(function (): void {
            Route::get('/', [MonthlyPaymentProviderController::class, 'index'])->name('index');
            Route::post('/', [MonthlyPaymentProviderController::class, 'store'])->name('store');
            Route::put('{provider}', [MonthlyPaymentProviderController::class, 'update'])->whereNumber('provider')->name('update');
            Route::patch('{provider}/active', [MonthlyPaymentProviderController::class, 'toggleActive'])->whereNumber('provider')->name('toggle-active');
            Route::delete('{provider}', [MonthlyPaymentProviderController::class, 'destroy'])->whereNumber('provider')->name('destroy');
        });

    Route::get('/', [MonthlyAccountController::class, 'index'])->middleware('can:'.Permission::MonthlyAccountView->value)->name('index');
    Route::get('print', [MonthlyAccountController::class, 'print'])->middleware('can:'.Permission::MonthlyAccountView->value)->name('print');
    Route::get('print-summary', [MonthlyAccountController::class, 'printSummary'])->middleware('can:'.Permission::MonthlyAccountView->value)->name('print-summary');
    Route::get('create', [MonthlyAccountController::class, 'create'])->middleware('can:'.Permission::MonthlyAccountCreate->value)->name('create');
    Route::post('/', [MonthlyAccountController::class, 'store'])->middleware('can:'.Permission::MonthlyAccountCreate->value)->name('store');
    Route::get('{day}/print', [MonthlyAccountController::class, 'printDay'])->whereNumber('day')->middleware('can:'.Permission::MonthlyAccountView->value)->name('print-day');
    Route::get('{day}', [MonthlyAccountController::class, 'show'])->whereNumber('day')->middleware('can:'.Permission::MonthlyAccountView->value)->name('show');
    Route::get('{day}/edit', [MonthlyAccountController::class, 'edit'])->whereNumber('day')->middleware('can:'.Permission::MonthlyAccountUpdate->value)->name('edit');
    Route::put('{day}', [MonthlyAccountController::class, 'update'])->whereNumber('day')->middleware('can:'.Permission::MonthlyAccountUpdate->value)->name('update');
    Route::delete('{day}', [MonthlyAccountController::class, 'destroy'])->whereNumber('day')->middleware('can:'.Permission::MonthlyAccountDelete->value)->name('destroy');
});
