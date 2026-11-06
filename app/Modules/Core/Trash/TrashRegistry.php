<?php

declare(strict_types=1);

namespace App\Modules\Core\Trash;

use App\Models\User;

/**
 * Çöp kutusu sağlayıcılarının kayıt defteri. Sınıf adı saklanır; sağlayıcı
 * yalnızca gerçekten gerektiğinde üretilir (her istekte sorgu/yükleme yok).
 */
class TrashRegistry
{
    /** @var array<int, class-string<TrashProvider>> */
    private array $classes = [];

    /** @param class-string<TrashProvider> $class */
    public function register(string $class): void
    {
        if (! in_array($class, $this->classes, true)) {
            $this->classes[] = $class;
        }
    }

    /** @return array<string, TrashProvider> anahtar => sağlayıcı */
    public function all(): array
    {
        $providers = [];
        foreach ($this->classes as $class) {
            $provider = app($class);
            $providers[$provider->key()] = $provider;
        }

        return $providers;
    }

    /** @return array<string, TrashProvider> */
    public function visibleTo(User $user): array
    {
        return array_filter($this->all(), fn (TrashProvider $provider): bool => $provider->canView($user));
    }

    public function find(string $key): ?TrashProvider
    {
        return $this->all()[$key] ?? null;
    }
}
