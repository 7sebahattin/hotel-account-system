<?php

declare(strict_types=1);

namespace App\Modules\MonthlyAccount\Services;

use App\Modules\MonthlyAccount\Exceptions\MonthlyPaymentProviderInUse;
use App\Modules\MonthlyAccount\Exceptions\MonthlyPaymentProviderRenameBlocked;
use App\Modules\MonthlyAccount\Models\MonthlyAccountItem;
use App\Modules\MonthlyAccount\Models\MonthlyPaymentProvider;
use Illuminate\Support\Facades\DB;

class MonthlyPaymentProviderService
{
    /** @param array{name: string} $data */
    public function create(array $data): MonthlyPaymentProvider
    {
        return DB::transaction(fn (): MonthlyPaymentProvider => MonthlyPaymentProvider::query()->create([
            'name' => $data['name'],
            'is_active' => true,
        ]));
    }

    /** @param array{name: string} $data */
    public function update(MonthlyPaymentProvider $provider, array $data): void
    {
        if ($data['name'] !== $provider->name
            && MonthlyAccountItem::query()->where('bank_name', $provider->name)->exists()) {
            throw MonthlyPaymentProviderRenameBlocked::make($provider->name);
        }

        DB::transaction(fn () => $provider->update(['name' => $data['name']]));
    }

    public function setActive(MonthlyPaymentProvider $provider, bool $active): void
    {
        DB::transaction(fn () => $provider->update(['is_active' => $active]));
    }

    public function delete(MonthlyPaymentProvider $provider): void
    {
        if (MonthlyAccountItem::query()->where('bank_name', $provider->name)->exists()) {
            throw MonthlyPaymentProviderInUse::make($provider->name);
        }

        DB::transaction(fn () => $provider->delete());
    }
}
