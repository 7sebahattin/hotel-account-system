<?php

declare(strict_types=1);

use App\Modules\AuditLog\Http\Controllers\AuditLogController;
use App\Modules\Authorization\Enums\Permission;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:'.Permission::AuditView->value])->group(function (): void {
    Route::get('audit-log', [AuditLogController::class, 'index'])->name('audit.index');

    // Salt okunur detay: kayıt bazlı karar `AuditLogPolicy::view()`tedir.
    Route::get('audit-log/{auditLog}', [AuditLogController::class, 'show'])->name('audit.show');
});
