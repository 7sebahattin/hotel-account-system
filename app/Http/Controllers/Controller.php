<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    // CLAUDE.md §7.1: yetki kararı Policy'de verilir; controller $this->authorize() çağırır.
    use AuthorizesRequests;
}
