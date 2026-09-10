<?php

declare(strict_types=1);

use App\Modules\Authorization\Enums\Permission;
use App\Modules\Rooms\Http\Controllers\RoomController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:'.Permission::RoomsManage->value])
    ->prefix('rooms')->name('rooms.')->group(function (): void {
        Route::get('/', [RoomController::class, 'index'])->name('index');
        Route::get('create', [RoomController::class, 'create'])->name('create');
        Route::post('/', [RoomController::class, 'store'])->name('store');
        Route::get('{room}/edit', [RoomController::class, 'edit'])->name('edit');
        Route::put('{room}', [RoomController::class, 'update'])->name('update');
        Route::patch('{room}/active', [RoomController::class, 'toggleActive'])->name('toggle-active');
        Route::delete('{room}', [RoomController::class, 'destroy'])->name('destroy');
    });
