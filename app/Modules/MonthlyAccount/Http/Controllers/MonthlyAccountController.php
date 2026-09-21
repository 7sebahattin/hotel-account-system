<?php

declare(strict_types=1);

namespace App\Modules\MonthlyAccount\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Enums\Currency;
use App\Modules\MonthlyAccount\Http\Requests\DeleteMonthlyAccountRequest;
use App\Modules\MonthlyAccount\Http\Requests\MonthlyAccountFilterRequest;
use App\Modules\MonthlyAccount\Http\Requests\SaveMonthlyAccountRequest;
use App\Modules\MonthlyAccount\Models\MonthlyAccountDay;
use App\Modules\MonthlyAccount\Services\MonthlyAccountService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class MonthlyAccountController extends Controller
{
    public function __construct(private readonly MonthlyAccountService $accounts) {}

    public function index(MonthlyAccountFilterRequest $request): View
    {
        return view('monthly-account::index', $this->monthData($request));
    }

    public function print(MonthlyAccountFilterRequest $request): View
    {
        return view('monthly-account::print', $this->monthData($request) + [
            'generatedAt' => now(),
            'preparedBy' => $request->user(),
        ]);
    }

    public function create(MonthlyAccountFilterRequest $request): View
    {
        $data = $request->validated();
        $owner = $this->accounts->ownerFor($request->user(), (int) ($data['owner_id'] ?? $request->user()->getKey()), true);

        return view('monthly-account::create', [
            'day' => null,
            'owner' => $owner,
            'currencies' => Currency::active(),
            'bankNames' => $this->accounts->bankNames(),
            'date' => $data['date'] ?? now()->toDateString(),
        ]);
    }

    public function store(SaveMonthlyAccountRequest $request): RedirectResponse
    {
        $day = $this->accounts->create($request->validated(), $request->user());

        return $this->redirectToMonth($day)->with('success', 'Günlük hesap kaydedildi.');
    }

    public function show(MonthlyAccountDay $day): View
    {
        $this->authorize('view', $day);
        $day->load(['owner', 'creator', 'updater', 'currentRevision.items', 'currentRevision.creator', 'revisions.creator']);

        return view('monthly-account::show', ['day' => $day, 'revision' => $day->currentRevision]);
    }

    public function edit(MonthlyAccountDay $day): View
    {
        $this->authorize('update', $day);
        $day->load(['owner', 'currentRevision.items']);

        return view('monthly-account::edit', [
            'day' => $day,
            'owner' => $day->owner,
            'currencies' => Currency::cases(),
            'bankNames' => $this->accounts->bankNames($day),
            'date' => $day->account_date->toDateString(),
        ]);
    }

    public function update(SaveMonthlyAccountRequest $request, MonthlyAccountDay $day): RedirectResponse
    {
        $saved = $this->accounts->update($day, $request->validated(), $request->user());

        return $this->redirectToMonth($saved)->with('success', 'Günlük hesap yeni sürüm olarak kaydedildi.');
    }

    public function destroy(DeleteMonthlyAccountRequest $request, MonthlyAccountDay $day): RedirectResponse
    {
        $this->accounts->delete($day, (int) $request->validated('version'), $request->user());

        return $this->redirectToMonth($day)->with('success', 'Günlük hesap silindi. Önceki sürümler geçmişte korundu.');
    }

    private function redirectToMonth(MonthlyAccountDay $day): RedirectResponse
    {
        return redirect()->route('monthly-account.index', [
            'month' => $day->account_date->format('Y-m'), 'owner_id' => $day->owner_id,
        ]);
    }

    /** @return array{month: CarbonImmutable, owner: \App\Models\User, days: \Illuminate\Database\Eloquent\Collection, summary: array} */
    private function monthData(MonthlyAccountFilterRequest $request): array
    {
        $data = $request->validated();
        $month = CarbonImmutable::createFromFormat('!Y-m', $data['month'] ?? now()->format('Y-m'));
        $owner = $this->accounts->ownerFor($request->user(), (int) ($data['owner_id'] ?? $request->user()->getKey()));
        $days = $this->accounts->month($owner, $month);

        return [
            'month' => $month,
            'owner' => $owner,
            'days' => $days,
            'summary' => $this->accounts->summarize($days),
        ];
    }
}
