<?php

declare(strict_types=1);

use App\Modules\Authorization\Enums\Permission;
use App\Modules\Trash\Http\Controllers\TrashController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:'.Permission::TrashView->value])->prefix('trash')->name('trash.')->group(function (): void {
    Route::get('/', [TrashController::class, 'index'])->name('index');

    Route::patch('{type}/{id}/restore', [TrashController::class, 'restore'])
        ->whereNumber('id')
        ->name('restore');

    // KALICI silme: yalnızca Admin (trash.force_delete + sağlayıcı rol kontrolü).
    Route::delete('{type}/{id}', [TrashController::class, 'destroy'])
        ->whereNumber('id')
        ->middleware('can:'.Permission::TrashForceDelete->value)
        ->name('destroy');
});
