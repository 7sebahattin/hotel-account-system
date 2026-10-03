<?php

declare(strict_types=1);

use App\Modules\Authorization\Enums\Permission;
use App\Modules\Users\Http\Controllers\UserController;
use App\Modules\Users\Http\Controllers\QuickMenuController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('quick-menu')->name('quick-menu.')->group(function (): void {
    Route::get('edit', [QuickMenuController::class, 'edit'])->name('edit');
    Route::put('/', [QuickMenuController::class, 'update'])->name('update');
});

Route::middleware('auth')->prefix('users')->name('users.')->group(function (): void {
    Route::get('/', [UserController::class, 'index'])
        ->middleware('can:'.Permission::UsersView->value)
        ->name('index');

    Route::middleware('can:'.Permission::UsersManage->value)->group(function (): void {
        Route::get('create', [UserController::class, 'create'])->name('create');
        Route::post('/', [UserController::class, 'store'])->name('store');
        Route::get('{user}/edit', [UserController::class, 'edit'])->name('edit');
        Route::put('{user}', [UserController::class, 'update'])->name('update');
        Route::patch('{user}/active', [UserController::class, 'toggleActive'])->name('toggle-active');
        Route::delete('{user}', [UserController::class, 'destroy'])->name('destroy');
    });
});
