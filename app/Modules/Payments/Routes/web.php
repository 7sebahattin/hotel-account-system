<?php

declare(strict_types=1);

use App\Modules\Authorization\Enums\Permission;
use App\Modules\Payments\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('payments')->name('payments.')->group(function (): void {
    Route::get('/', [PaymentController::class, 'index'])
        ->middleware('can:'.Permission::PaymentsView->value)
        ->name('index');

    // Çöp kutusu — {payment} joker rotalarından ÖNCE tanımlanmalıdır.
    Route::get('trash', [PaymentController::class, 'trash'])
        ->middleware('can:'.Permission::PaymentsView->value)
        ->name('trash');

    Route::get('create', [PaymentController::class, 'create'])
        ->middleware('can:'.Permission::PaymentsCreate->value)
        ->name('create');

    Route::get('allocation-options', [PaymentController::class, 'allocationOptions'])
        ->middleware('can:'.Permission::PaymentsCreate->value)
        ->name('allocation-options');

    Route::post('/', [PaymentController::class, 'store'])
        ->middleware('can:'.Permission::PaymentsCreate->value)
        ->name('store');

    Route::get('{payment}', [PaymentController::class, 'show'])
        ->middleware('can:'.Permission::PaymentsView->value)
        ->name('show');

    // Yazdırılabilir Tahsilat Makbuzu — görüntüleyebilen herkes yazdırabilir,
    // yeni bir yetki AÇILMADI (v2.1.1-B §12).
    Route::get('{payment}/receipt', [PaymentController::class, 'receipt'])
        ->middleware('can:'.Permission::PaymentsView->value)
        ->name('receipt');

    // Düzenleme YALNIZCA metadata içindir (referans no / açıklama). Yeni bir
    // yetki açılmadı: tahsilat girebilen herkes kendi girdiği kaydın referansını
    // düzeltebilmelidir; kayıt bazlı kısıt PaymentPolicy::update'tedir.
    Route::get('{payment}/edit', [PaymentController::class, 'edit'])
        ->middleware('can:'.Permission::PaymentsCreate->value)
        ->name('edit');

    Route::put('{payment}', [PaymentController::class, 'update'])
        ->middleware('can:'.Permission::PaymentsCreate->value)
        ->name('update');

    // V1'de tahsilat SİLİNMEZ, iptal edilir.
    Route::patch('{payment}/cancel', [PaymentController::class, 'cancel'])
        ->middleware('can:'.Permission::PaymentsCancel->value)
        ->name('cancel');

    // Çöp kutusu: iptal edilmiş tahsilatı herkes (payments.create) çöpe atar
    // ve geri alır; KALICI silme yalnızca Admin'dedir (PaymentPolicy::forceDelete).
    Route::patch('{payment}/trash', [PaymentController::class, 'moveToTrash'])
        ->middleware('can:'.Permission::PaymentsCreate->value)
        ->name('move-to-trash');

    Route::patch('{payment}/restore', [PaymentController::class, 'restore'])
        ->middleware('can:'.Permission::PaymentsCreate->value)
        ->name('restore');

    Route::delete('{payment}', [PaymentController::class, 'destroy'])
        ->middleware('can:'.Permission::PaymentsForceDelete->value)
        ->name('destroy');
});
