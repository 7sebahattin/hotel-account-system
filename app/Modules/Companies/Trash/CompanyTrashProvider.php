<?php

declare(strict_types=1);

namespace App\Modules\Companies\Trash;

use App\Models\User;
use App\Modules\Authorization\Enums\Permission;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Services\CompanyService;
use App\Modules\Core\Trash\TrashItem;
use App\Modules\Core\Trash\TrashProvider;
use Illuminate\Support\Collection;

class CompanyTrashProvider implements TrashProvider
{
    public function __construct(private readonly CompanyService $service) {}

    public function key(): string
    {
        return 'companies';
    }

    public function label(): string
    {
        return 'Cari hesaplar (firmalar)';
    }

    public function canView(User $user): bool
    {
        return $user->can(Permission::CompaniesView->value);
    }

    /** Çöpe atabilen (silme yetkisi olan) geri de alabilir. */
    public function canRestore(User $user): bool
    {
        return $user->can(Permission::CompaniesDelete->value);
    }

    public function items(): Collection
    {
        return $this->service->trashed()->map(fn (Company $record): TrashItem => new TrashItem(
            id: $record->getKey(),
            title: $record->name,
            detail: trim(($record->city ?? '').' '.($record->tax_number ? '· VN '.$record->tax_number : ''), ' ·') ?: '—',
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
        $this->service->restore($this->service->findIncludingTrashed($id));
    }

    public function forceDelete(int $id, User $actor): void
    {
        $this->service->forceDelete($this->service->findIncludingTrashed($id), $actor);
    }
}
