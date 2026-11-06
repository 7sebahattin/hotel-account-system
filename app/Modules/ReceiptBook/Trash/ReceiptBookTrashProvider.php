<?php

declare(strict_types=1);

namespace App\Modules\ReceiptBook\Trash;

use App\Models\User;
use App\Modules\Authorization\Enums\Permission;
use App\Modules\Core\Trash\TrashItem;
use App\Modules\Core\Trash\TrashProvider;
use App\Modules\ReceiptBook\Models\ReceiptBookEntry;
use App\Modules\ReceiptBook\Services\ReceiptBookService;
use Illuminate\Support\Collection;

class ReceiptBookTrashProvider implements TrashProvider
{
    public function __construct(private readonly ReceiptBookService $service) {}

    public function key(): string
    {
        return 'receipt-book';
    }

    public function label(): string
    {
        return 'Tahsilat makbuzları';
    }

    public function canView(User $user): bool
    {
        return $user->can(Permission::ReceiptBookView->value);
    }

    /** Çöpe atabilen (silme yetkisi olan) geri de alabilir. */
    public function canRestore(User $user): bool
    {
        return $user->can(Permission::ReceiptBookDelete->value);
    }

    public function items(): Collection
    {
        return $this->service->trashed()->map(fn (ReceiptBookEntry $record): TrashItem => new TrashItem(
            id: $record->getKey(),
            title: $record->customer_name.' · '.$record->amountMoney()->format($record->currency),
            detail: ($record->receipt_no ?? '—').' · '.$record->receipt_date->format('d.m.Y'),
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
