<?php

declare(strict_types=1);

namespace App\Modules\Offers\Providers;

use App\Modules\Core\Providers\ModuleServiceProvider;
use App\Modules\Offers\Models\Offer;
use App\Modules\Offers\Policies\OfferPolicy;

class OffersServiceProvider extends ModuleServiceProvider
{
    protected string $namespace = 'offers';

    protected array $policies = [Offer::class => OfferPolicy::class];
}
