<?php

declare(strict_types=1);

use App\Modules\Personnel\Http\Controllers\ScanController;
use App\Modules\Personnel\Http\Middleware\AuthenticateTerminal;
use Illuminate\Support\Facades\Route;

Route::post('api/personnel/scans', [ScanController::class, 'api'])
    ->middleware(AuthenticateTerminal::class)
    ->name('personnel.api.scans.store');
