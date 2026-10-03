<?php

declare(strict_types=1);

namespace App\Modules\Users\Services;

use App\Models\User;
use App\Modules\Users\Models\UserQuickMenu;
use App\Modules\Users\Support\QuickShortcutRegistry;

final class QuickMenuService
{
    /** @return list<array{label: string, icon: string, route: string, active: bool}> */
    public function shortcuts(User $user): array
    {
        $stored = UserQuickMenu::query()->where('user_id', $user->getKey())->first();

        return $stored === null ? QuickShortcutRegistry::defaults($user) : ($stored->shortcuts ?? []);
    }

    /** @return list<array{label: string, icon: string, route: string, active: bool}> */
    public function visible(User $user): array
    {
        return array_values(array_filter($this->shortcuts($user),
            fn (array $item): bool => ($item['active'] ?? false)
                && isset(QuickShortcutRegistry::ICONS[$item['icon'] ?? ''])
                && QuickShortcutRegistry::allowed($user, $item['route'] ?? '')
        ));
    }

    /** @param list<array{label: string, icon: string, route: string, active: bool}> $shortcuts */
    public function save(User $user, array $shortcuts): void
    {
        UserQuickMenu::query()->updateOrCreate(['user_id' => $user->getKey()], ['shortcuts' => $shortcuts]);
    }
}
