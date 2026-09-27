<?php

declare(strict_types=1);

namespace App\Modules\Personnel\Providers;

use App\Modules\Core\Providers\ModuleServiceProvider;
use App\Modules\Personnel\Models\PersonnelCard;
use App\Modules\Personnel\Models\PersonnelEmployee;
use App\Modules\Personnel\Models\PersonnelPayrollEntry;
use App\Modules\Personnel\Models\PersonnelShift;
use App\Modules\Personnel\Models\PersonnelTerminal;
use App\Modules\Personnel\Policies\PersonnelCardPolicy;
use App\Modules\Personnel\Policies\PersonnelEmployeePolicy;
use App\Modules\Personnel\Policies\PersonnelPayrollEntryPolicy;
use App\Modules\Personnel\Policies\PersonnelShiftPolicy;
use App\Modules\Personnel\Policies\PersonnelTerminalPolicy;
use Illuminate\Support\Facades\Route;

class PersonnelServiceProvider extends ModuleServiceProvider
{
    protected string $namespace = 'personnel';

    protected array $policies = [
        PersonnelEmployee::class => PersonnelEmployeePolicy::class,
        PersonnelCard::class => PersonnelCardPolicy::class,
        PersonnelShift::class => PersonnelShiftPolicy::class,
        PersonnelTerminal::class => PersonnelTerminalPolicy::class,
        PersonnelPayrollEntry::class => PersonnelPayrollEntryPolicy::class,
    ];

    public function boot(): void
    {
        parent::boot();

        Route::group([], $this->path('Routes/api.php'));
    }
}
