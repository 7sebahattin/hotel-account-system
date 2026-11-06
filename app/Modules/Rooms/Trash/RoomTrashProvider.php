<?php

declare(strict_types=1);

namespace App\Modules\Rooms\Trash;

use App\Models\User;
use App\Modules\Authorization\Enums\Permission;
use App\Modules\Core\Trash\TrashItem;
use App\Modules\Core\Trash\TrashProvider;
use App\Modules\Rooms\Models\Room;
use App\Modules\Rooms\Services\RoomService;
use Illuminate\Support\Collection;

class RoomTrashProvider implements TrashProvider
{
    public function __construct(private readonly RoomService $service) {}

    public function key(): string
    {
        return 'rooms';
    }

    public function label(): string
    {
        return 'Odalar';
    }

    public function canView(User $user): bool
    {
        return $user->can(Permission::RoomsManage->value);
    }

    /** Çöpe atabilen (silme yetkisi olan) geri de alabilir. */
    public function canRestore(User $user): bool
    {
        return $user->can(Permission::RoomsManage->value);
    }

    public function items(): Collection
    {
        return $this->service->trashed()->map(fn (Room $record): TrashItem => new TrashItem(
            id: $record->getKey(),
            title: $record->label(),
            detail: $record->room_type ?: '—',
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
