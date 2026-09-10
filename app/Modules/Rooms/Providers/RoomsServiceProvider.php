<?php

declare(strict_types=1);

namespace App\Modules\Rooms\Providers;

use App\Modules\Core\Providers\ModuleServiceProvider;
use App\Modules\Rooms\Models\Room;
use App\Modules\Rooms\Policies\RoomPolicy;

class RoomsServiceProvider extends ModuleServiceProvider
{
    protected string $namespace = 'rooms';

    protected array $policies = [
        Room::class => RoomPolicy::class,
    ];
}
