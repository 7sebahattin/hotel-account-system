<?php

declare(strict_types=1);

namespace App\Modules\Users\Trash;

use App\Models\User;
use App\Modules\Authorization\Enums\Permission;
use App\Modules\Core\Trash\TrashItem;
use App\Modules\Core\Trash\TrashProvider;
use App\Modules\Users\Services\UserService;
use Illuminate\Support\Collection;

class UserTrashProvider implements TrashProvider
{
    public function __construct(private readonly UserService $service) {}

    public function key(): string
    {
        return 'users';
    }

    public function label(): string
    {
        return 'Kullanıcılar';
    }

    public function canView(User $user): bool
    {
        return $user->can(Permission::UsersView->value);
    }

    /** Çöpe atabilen (silme yetkisi olan) geri de alabilir. */
    public function canRestore(User $user): bool
    {
        return $user->can(Permission::UsersManage->value);
    }

    public function items(): Collection
    {
        return $this->service->trashed()->map(fn (User $record): TrashItem => new TrashItem(
            id: $record->getKey(),
            title: $record->name,
            detail: $record->email,
            trashedAt: $record->deleted_at,
            trashedBy: null,
            url: null,
        ));
    }

    public function count(): int
    {
        return $this->service->trashedCount();
    }

    public function restore(int $id, User $actor): void
    {
        $this->service->restoreFromTrash($this->service->findIncludingTrashed($id));
    }

    public function forceDelete(int $id, User $actor): void
    {
        $this->service->forceDelete($this->service->findIncludingTrashed($id), $actor);
    }
}
