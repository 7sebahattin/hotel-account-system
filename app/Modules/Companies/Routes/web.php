<?php

declare(strict_types=1);

use App\Modules\Authorization\Enums\Permission;
use App\Modules\Companies\Http\Controllers\CompanyController;
use App\Modules\Companies\Http\Controllers\StatementController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('companies')->name('companies.')->group(function (): void {
    Route::get('/', [CompanyController::class, 'index'])
        ->middleware('can:'.Permission::CompaniesView->value)
        ->name('index');

    Route::get('create', [CompanyController::class, 'create'])
        ->middleware('can:'.Permission::CompaniesCreate->value)
        ->name('create');

    Route::post('/', [CompanyController::class, 'store'])
        ->middleware('can:'.Permission::CompaniesCreate->value)
        ->name('store');

    // Silinmiş kaydın da bulunabilmesi için withTrashed; görüntüleme yetkisi
    // CompanyPolicy::view() içinde ayrıca denetlenir.
    Route::get('{company}', [CompanyController::class, 'show'])
        ->middleware('can:'.Permission::CompaniesView->value)
        ->withTrashed()
        ->name('show');

    Route::get('{company}/edit', [CompanyController::class, 'edit'])
        ->middleware('can:'.Permission::CompaniesUpdate->value)
        ->name('edit');

    Route::put('{company}', [CompanyController::class, 'update'])
        ->middleware('can:'.Permission::CompaniesUpdate->value)
        ->name('update');

    Route::delete('{company}', [CompanyController::class, 'destroy'])
        ->middleware('can:'.Permission::CompaniesDelete->value)
        ->name('destroy');

    Route::patch('{company}/restore', [CompanyController::class, 'restore'])
        ->middleware('can:'.Permission::CompaniesRestore->value)
        ->withTrashed()
        ->name('restore');
});

/*
 * Cari ekstre. Companies modülünde durur çünkü `Ledger` hiçbir modülü tanımaz
 * (CLAUDE.md §4.3/3); hesabın tamamı LedgerStatementService'tedir.
 */
Route::middleware('auth')->name('statements.')->group(function (): void {
    // Firma bakiye listesi / ekstre seçim ekranı.
    Route::get('statements', [StatementController::class, 'index'])
        ->middleware('can:'.Permission::LedgerViewAll->value)
        ->name('index');

    Route::get('statements/print', [StatementController::class, 'printIndex'])
        ->middleware('can:'.Permission::LedgerViewAll->value)
        ->name('print-index');

    Route::get('companies/{company}/statement', [StatementController::class, 'show'])
        ->middleware('can:'.Permission::LedgerView->value)
        ->withTrashed()
        ->name('show');

    Route::get('companies/{company}/statement/print', [StatementController::class, 'print'])
        ->middleware('can:'.Permission::LedgerView->value)
        ->withTrashed()
        ->name('print');
});
