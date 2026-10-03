<?php

declare(strict_types=1);

namespace App\Modules\Users\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Users\Services\QuickMenuService;
use App\Modules\Users\Support\QuickShortcutRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class QuickMenuController extends Controller
{
    public function __construct(private readonly QuickMenuService $menus) {}

    public function edit(Request $request): View
    {
        $user = $request->user();
        $targets = array_filter(QuickShortcutRegistry::TARGETS,
            fn (string $route): bool => QuickShortcutRegistry::allowed($user, $route), ARRAY_FILTER_USE_KEY);

        $shortcuts = old('shortcuts', $this->menus->shortcuts($user));

        return view('users::quick-menu', [
            'shortcuts' => is_array($shortcuts) ? $shortcuts : [],
            'targets' => $targets,
            'icons' => QuickShortcutRegistry::ICONS,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = Validator::make($request->all(), [
            'shortcuts' => ['sometimes', 'array', 'max:12'],
            'shortcuts.*.label' => ['required', 'string', 'max:50'],
            'shortcuts.*.icon' => ['required', Rule::in(array_keys(QuickShortcutRegistry::ICONS))],
            'shortcuts.*.route' => ['required', Rule::in(array_keys(QuickShortcutRegistry::TARGETS))],
            'shortcuts.*.active' => ['required', 'boolean'],
        ])->validate();

        $shortcuts = [];
        foreach ($data['shortcuts'] ?? [] as $item) {
            if (! QuickShortcutRegistry::allowed($request->user(), $item['route'])) {
                throw ValidationException::withMessages(['shortcuts' => 'Bu işlem için yetkiniz bulunmuyor.']);
            }

            $label = trim($item['label']);
            if ($label === '') {
                throw ValidationException::withMessages(['shortcuts' => 'Kısayol adı boş olamaz.']);
            }

            $shortcuts[] = [
                'label' => $label,
                'icon' => $item['icon'],
                'route' => $item['route'],
                'active' => (bool) $item['active'],
            ];
        }

        $this->menus->save($request->user(), $shortcuts);

        return redirect()->route('quick-menu.edit')->with('success', 'Hızlı menü kaydedildi.');
    }
}
