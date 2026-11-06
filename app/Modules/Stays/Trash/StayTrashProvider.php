<?php

declare(strict_types=1);

namespace App\Modules\Stays\Trash;

use App\Models\User;
use App\Modules\Authorization\Enums\Permission;
use App\Modules\Core\Trash\TrashItem;
use App\Modules\Core\Trash\TrashProvider;
use App\Modules\Stays\Models\Stay;
use App\Modules\Stays\Services\StayService;
use Illuminate\Support\Collection;

class StayTrashProvider implements TrashProvider
{
    public function __construct(private readonly StayService $stays) {}

    public function key(): string
    {
        return 'stays';
    }

    public function label(): string
    {
        return 'Konaklamalar';
    }

    public function canView(User $user): bool
    {
        return $user->can(Permission::StaysView->value);
    }

    /** Konaklamayı çöpe atabilen (stays.delete) geri de alabilir. */
    public function canRestore(User $user): bool
    {
        return $user->can(Permission::StaysDelete->value);
    }

    public function items(): Collection
    {
        return $this->stays->trashed()->map(fn (Stay $stay): TrashItem => new TrashItem(
            id: $stay->id,
            title: $stay->guest_name.' · '.$stay->amountMoney()->format($stay->currency),
            detail: ($stay->company?->name ?? '—')
                .' · '.$stay->check_in_date->format('d.m.Y').' → '.$stay->check_out_date->format('d.m.Y'),
            trashedAt: $stay->deleted_at,
            trashedBy: $stay->updater?->name,
            url: null,
        ));
    }

    public function count(): int
    {
        return $this->stays->trashedCount();
    }

    public function restore(int $id, User $actor): void
    {
        $this->stays->restoreFromTrash($this->stays->findIncludingTrashed($id), $actor);
    }

    public function forceDelete(int $id, User $actor): void
    {
        $this->stays->forceDelete($this->stays->findIncludingTrashed($id), $actor);
    }
}
