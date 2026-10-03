<?php

declare(strict_types=1);

namespace App\Modules\Users\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

final class QuickShortcutRegistry
{
    /** @var array<string, array{label: string, icon: string, permissions: list<string>}> */
    public const TARGETS = [
        'payments.create' => ['label' => 'Cari Tahsilat', 'icon' => 'cash', 'permissions' => ['payments.create']],
        'stays.create' => ['label' => 'Yeni Konaklama', 'icon' => 'bed', 'permissions' => ['stays.create']],
        'companies.create' => ['label' => 'Yeni Cari Hesap', 'icon' => 'building', 'permissions' => ['companies.create']],
        'offers.create' => ['label' => 'Yeni Teklif', 'icon' => 'log', 'permissions' => ['offers.view', 'offers.create']],
    ];

    /** @var array<string, string> */
    public const ICONS = [
        'cash' => 'Tahsilat',
        'bed' => 'Konaklama',
        'building' => 'Cari hesap',
        'log' => 'Belge',
        'calendar' => 'Takvim',
        'plus' => 'Ekle',
        'wallet' => 'Cüzdan',
    ];

    public static function allowed(User $user, string $route): bool
    {
        $target = self::TARGETS[$route] ?? null;

        if ($target === null || ! Route::has($route)) {
            return false;
        }

        foreach ($target['permissions'] as $permission) {
            if (! $user->can($permission)) {
                return false;
            }
        }

        return true;
    }

    /** @return list<array{label: string, icon: string, route: string, active: bool}> */
    public static function defaults(User $user): array
    {
        $shortcuts = [];
        foreach (['payments.create', 'stays.create', 'companies.create'] as $route) {
            if (self::allowed($user, $route)) {
                $shortcuts[] = [
                    'label' => self::TARGETS[$route]['label'],
                    'icon' => self::TARGETS[$route]['icon'],
                    'route' => $route,
                    'active' => true,
                ];
            }
        }

        return $shortcuts;
    }
}
