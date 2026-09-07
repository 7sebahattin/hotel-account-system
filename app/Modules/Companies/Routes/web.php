<?php

declare(strict_types=1);

use App\Modules\Authorization\Enums\Permission;
use App\Modules\Companies\Http\Controllers\CompanyController;
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
