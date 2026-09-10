<?php

declare(strict_types=1);

use App\Modules\Authorization\Enums\Permission;
use App\Modules\Authorization\Http\Controllers\RoleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:'.Permission::RolesManage->value])
    ->prefix('roles')
    ->name('roles.')
    ->group(function (): void {
        Route::get('/', [RoleController::class, 'index'])->name('index');
        Route::get('create', [RoleController::class, 'create'])->name('create');
        Route::post('/', [RoleController::class, 'store'])->name('store');
        Route::get('{role}/edit', [RoleController::class, 'edit'])->name('edit');
        Route::put('{role}', [RoleController::class, 'update'])->name('update');
        Route::delete('{role}', [RoleController::class, 'destroy'])->name('destroy');
    });
