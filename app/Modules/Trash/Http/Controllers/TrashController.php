<?php

declare(strict_types=1);

namespace App\Modules\Trash\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Authorization\Enums\Permission;
use App\Modules\Core\Trash\TrashActionFailed;
use App\Modules\Core\Trash\TrashProvider;
use App\Modules\Core\Trash\TrashRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TrashController extends Controller
{
    public function __construct(private readonly TrashRegistry $registry) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $groups = $this->registry->visibleTo($user);

        $counts = [];
        foreach ($groups as $key => $provider) {
            $counts[$key] = $provider->count();
        }

        // Seçili grup: ?tur=; yoksa içinde kayıt olan ilk grup; o da yoksa ilk grup.
        $selected = (string) $request->query('tur', '');
        if (! isset($groups[$selected])) {
            $selected = (string) (collect($counts)->filter(fn (int $count): bool => $count > 0)->keys()->first() ?? array_key_first($groups));
        }

        $provider = $groups[$selected] ?? null;

        return view('trash::index', [
            'groups' => $groups,
            'counts' => $counts,
            'selected' => $selected,
            'items' => $provider?->items() ?? collect(),
            'canRestore' => $provider?->canRestore($user) ?? false,
            'canForceDelete' => $user->can(Permission::TrashForceDelete->value),
        ]);
    }

    public function restore(Request $request, string $type, int $id): RedirectResponse
    {
        $provider = $this->providerFor($request, $type);
        abort_unless($provider->canRestore($request->user()), 403);

        try {
            $provider->restore($id, $request->user());
        } catch (TrashActionFailed $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('trash.index', ['tur' => $type])->with('success', 'Kayıt çöp kutusundan geri alındı.');
    }

    public function destroy(Request $request, string $type, int $id): RedirectResponse
    {
        $provider = $this->providerFor($request, $type);

        try {
            $provider->forceDelete($id, $request->user());
        } catch (TrashActionFailed $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('trash.index', ['tur' => $type])->with('success', 'Kayıt veritabanından kalıcı olarak silindi.');
    }

    private function providerFor(Request $request, string $type): TrashProvider
    {
        $provider = $this->registry->find($type);
        abort_if($provider === null || ! $provider->canView($request->user()), 404);

        return $provider;
    }
}
