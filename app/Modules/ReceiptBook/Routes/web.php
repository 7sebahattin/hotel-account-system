<?php

declare(strict_types=1);

use App\Modules\Authorization\Enums\Permission;
use App\Modules\ReceiptBook\Http\Controllers\ReceiptBookController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('receipt-book')->name('receipt-book.')->group(function (): void {
    Route::middleware('can:'.Permission::ReceiptBookView->value)
        ->get('/', [ReceiptBookController::class, 'index'])->name('index');

    // Sabit segmentli rotalar, {entry} joker deseninden ÖNCE tanımlanır —
    // aksi hâlde "create" bir kayıt kimliği olarak yorumlanmaya ÇALIŞILIRDI.
    Route::middleware('can:'.Permission::ReceiptBookCreate->value)
        ->get('create', [ReceiptBookController::class, 'create'])->name('create');
    Route::middleware('can:'.Permission::ReceiptBookCreate->value)
        ->post('/', [ReceiptBookController::class, 'store'])->name('store');

    Route::middleware('can:'.Permission::ReceiptBookView->value)
        ->get('{entry}', [ReceiptBookController::class, 'show'])->name('show');
    Route::middleware('can:'.Permission::ReceiptBookView->value)
        ->get('{entry}/print', [ReceiptBookController::class, 'print'])->name('print');

    // Düzenleme/silme — yalnızca Admin (27 Eylül 2026, kullanıcı talimatı).
    Route::middleware('can:'.Permission::ReceiptBookUpdate->value)
        ->get('{entry}/edit', [ReceiptBookController::class, 'edit'])->name('edit');
    Route::middleware('can:'.Permission::ReceiptBookUpdate->value)
        ->put('{entry}', [ReceiptBookController::class, 'update'])->name('update');
    Route::middleware('can:'.Permission::ReceiptBookDelete->value)
        ->delete('{entry}', [ReceiptBookController::class, 'destroy'])->name('destroy');
});
