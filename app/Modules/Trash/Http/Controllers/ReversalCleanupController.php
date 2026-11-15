<?php

declare(strict_types=1);

namespace App\Modules\Trash\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Companies\Services\CompanyService;
use App\Modules\Core\Trash\TrashActionFailed;
use App\Modules\Ledger\Services\ReversalCleanupService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * GEÇİCİ ekran (15 Kasım 2026, kullanıcı talimatı): çöp kutusundan önceki
 * dönemden kalan "orijinal + ters kayıt" çiftlerini Admin elle kalıcı siler.
 * Yalnızca aktif Admin; iş kuralı `ReversalCleanupService`tedir.
 */
class ReversalCleanupController extends Controller
{
    public function __construct(
        private readonly ReversalCleanupService $cleanup,
        private readonly CompanyService $companies,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeAdmin($request);

        $all = $this->cleanup->pairs();
        $names = $this->companies->namesByIds($all->map(fn (array $pair): int => $pair['original']->company_id)->all());

        $showAll = $request->query('goster') === 'tumu';
        $companyId = (int) $request->query('company_id', 0);

        $orphanCount = $all->where('source_exists', false)->count();
        $companyOptions = $all->map(fn (array $pair): int => $pair['original']->company_id)->unique()
            ->mapWithKeys(fn (int $id): array => [$id => $names[$id] ?? "Cari #{$id}"])->sort()->all();

        $pairs = $all
            ->when(! $showAll, fn ($items) => $items->where('source_exists', false))
            ->when($companyId > 0, fn ($items) => $items->filter(fn (array $pair): bool => $pair['original']->company_id === $companyId))
            ->values();

        return view('trash::reversals', [
            'pairs' => $pairs,
            'names' => $names,
            'showAll' => $showAll,
            'companyId' => $companyId,
            'companyOptions' => $companyOptions,
            'orphanCount' => $orphanCount,
            'totalCount' => $all->count(),
        ]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer', 'min:1'],
        ], [], ['ids' => 'seçili ters kayıtlar']);

        try {
            $count = $this->cleanup->purgePairs(array_map('intval', $validated['ids']), $request->user());
        } catch (TrashActionFailed $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "{$count} ters kayıt çifti (orijinal + iptal satırı) kalıcı olarak silindi. Bakiyeler değişmedi.");
    }

    private function authorizeAdmin(Request $request): void
    {
        $user = $request->user();
        abort_unless($user->is_active && $user->hasRole(RoleName::Admin->value), 403);
    }
}
