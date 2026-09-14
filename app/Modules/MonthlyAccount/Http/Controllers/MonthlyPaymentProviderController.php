<?php

declare(strict_types=1);

namespace App\Modules\MonthlyAccount\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MonthlyAccount\Exceptions\MonthlyPaymentProviderInUse;
use App\Modules\MonthlyAccount\Exceptions\MonthlyPaymentProviderRenameBlocked;
use App\Modules\MonthlyAccount\Http\Requests\MonthlyPaymentProviderRequest;
use App\Modules\MonthlyAccount\Models\MonthlyAccountItem;
use App\Modules\MonthlyAccount\Models\MonthlyPaymentProvider;
use App\Modules\MonthlyAccount\Services\MonthlyPaymentProviderService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MonthlyPaymentProviderController extends Controller
{
    public function __construct(private readonly MonthlyPaymentProviderService $providers) {}

    public function index(): View
    {
        $this->authorize('viewAny', MonthlyPaymentProvider::class);
        $providers = MonthlyPaymentProvider::query()->orderBy('name')->paginate(25);
        $usedNames = MonthlyAccountItem::query()
            ->whereIn('bank_name', $providers->getCollection()->pluck('name'))
            ->distinct()
            ->pluck('bank_name')
            ->all();

        return view('monthly-account::providers.index', [
            'providers' => $providers,
            'usedNames' => $usedNames,
            'activeCount' => MonthlyPaymentProvider::query()->where('is_active', true)->count(),
        ]);
    }

    public function store(MonthlyPaymentProviderRequest $request): RedirectResponse
    {
        $this->providers->create($request->validated());

        return redirect()->route('monthly-account.providers.index')->with('success', 'Banka / ödeme kuruluşu eklendi.');
    }

    public function update(MonthlyPaymentProviderRequest $request, MonthlyPaymentProvider $provider): RedirectResponse
    {
        try {
            $this->providers->update($provider, $request->validated());
        } catch (MonthlyPaymentProviderRenameBlocked $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Banka / ödeme kuruluşu güncellendi.');
    }

    public function toggleActive(Request $request, MonthlyPaymentProvider $provider): RedirectResponse
    {
        $this->authorize('update', $provider);
        $this->providers->setActive($provider, ! $provider->is_active);

        return back()->with('success', $provider->is_active ? 'Kuruluş aktifleştirildi.' : 'Kuruluş pasifleştirildi.');
    }

    public function destroy(Request $request, MonthlyPaymentProvider $provider): RedirectResponse
    {
        $this->authorize('delete', $provider);

        try {
            $this->providers->delete($provider);
        } catch (MonthlyPaymentProviderInUse $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Banka / ödeme kuruluşu silindi.');
    }
}
