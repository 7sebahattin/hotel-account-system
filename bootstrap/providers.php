<?php

use App\Modules\AuditLog\Providers\AuditLogServiceProvider;
use App\Modules\Auth\Providers\AuthServiceProvider;
use App\Modules\Authorization\Providers\AuthorizationServiceProvider;
use App\Modules\Branding\Providers\BrandingServiceProvider;
use App\Modules\Companies\Providers\CompaniesServiceProvider;
use App\Modules\Dashboard\Providers\DashboardServiceProvider;
use App\Modules\FxCorrection\Providers\FxCorrectionServiceProvider;
use App\Modules\Ledger\Providers\LedgerServiceProvider;
use App\Modules\MonthlyAccount\Providers\MonthlyAccountServiceProvider;
use App\Modules\Offers\Providers\OffersServiceProvider;
use App\Modules\Payments\Providers\PaymentsServiceProvider;
use App\Modules\Personnel\Providers\PersonnelServiceProvider;
use App\Modules\ReceiptBook\Providers\ReceiptBookServiceProvider;
use App\Modules\Rooms\Providers\RoomsServiceProvider;
use App\Modules\Stays\Providers\StaysServiceProvider;
use App\Modules\Users\Providers\UsersServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,

    // Modül sağlayıcıları (CLAUDE.md §4.1)
    AuditLogServiceProvider::class,
    AuthorizationServiceProvider::class,
    BrandingServiceProvider::class,
    AuthServiceProvider::class,
    CompaniesServiceProvider::class,
    DashboardServiceProvider::class,
    FxCorrectionServiceProvider::class,
    LedgerServiceProvider::class,
    MonthlyAccountServiceProvider::class,
    OffersServiceProvider::class,
    PaymentsServiceProvider::class,
    PersonnelServiceProvider::class,
    ReceiptBookServiceProvider::class,
    RoomsServiceProvider::class,
    StaysServiceProvider::class,
    UsersServiceProvider::class,
];
