<?php

declare(strict_types=1);

use App\Modules\AuditLog\Http\Controllers\AuditLogController;
use App\Modules\Authorization\Enums\Permission;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:'.Permission::AuditView->value])
    ->get('audit-log', [AuditLogController::class, 'index'])
    ->name('audit.index');
