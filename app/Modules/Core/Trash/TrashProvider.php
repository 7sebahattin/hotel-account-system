<?php

declare(strict_types=1);

namespace App\Modules\Core\Trash;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Bir kayıt türünün çöp kutusu davranışı. Her modül kendi sağlayıcısını
 * `ModuleServiceProvider::$trashProviders` ile kaydeder; Trash modülü yalnızca
 * bu sözleşmeyi bilir, başka modülün modeline dokunmaz (CLAUDE.md §4.3).
 */
interface TrashProvider
{
    /** URL'de kullanılan kısa anahtar. Örn: 'payments' */
    public function key(): string;

    /** Çöp kutusunda grup başlığı. Örn: 'Tahsilatlar' */
    public function label(): string;

    /** Kullanıcı bu grubu görebilir mi (grubun kendi modül yetkisi). */
    public function canView(User $user): bool;

    /** Kullanıcı bu gruptan kayıt geri alabilir mi. */
    public function canRestore(User $user): bool;

    /** @return Collection<int, TrashItem> */
    public function items(): Collection;

    public function count(): int;

    /** @throws TrashActionFailed */
    public function restore(int $id, User $actor): void;

    /**
     * Kaydı veritabanından KALICI siler (yalnızca Admin).
     *
     * @throws TrashActionFailed
     */
    public function forceDelete(int $id, User $actor): void;
}
