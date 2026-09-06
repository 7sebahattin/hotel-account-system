<?php

use App\Modules\Auth\Providers\AuthServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,

    // Modül sağlayıcıları (CLAUDE.md §4.1)
    AuthServiceProvider::class,
];
