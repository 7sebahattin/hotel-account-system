<?php

use App\Modules\AuditLog\Providers\AuditLogServiceProvider;
use App\Modules\Auth\Providers\AuthServiceProvider;
use App\Modules\Authorization\Providers\AuthorizationServiceProvider;
use App\Modules\Companies\Providers\CompaniesServiceProvider;
use App\Modules\Ledger\Providers\LedgerServiceProvider;
use App\Modules\Stays\Providers\StaysServiceProvider;
use App\Modules\Users\Providers\UsersServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,

    // Modül sağlayıcıları (CLAUDE.md §4.1)
    AuditLogServiceProvider::class,
    AuthorizationServiceProvider::class,
    AuthServiceProvider::class,
    CompaniesServiceProvider::class,
    LedgerServiceProvider::class,
    StaysServiceProvider::class,
    UsersServiceProvider::class,
];
