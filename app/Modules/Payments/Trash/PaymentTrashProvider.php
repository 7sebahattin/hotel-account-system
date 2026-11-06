<?php

declare(strict_types=1);

namespace App\Modules\Payments\Trash;

use App\Models\User;
use App\Modules\Authorization\Enums\Permission;
use App\Modules\Core\Trash\TrashItem;
use App\Modules\Core\Trash\TrashProvider;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Services\PaymentService;
use Illuminate\Support\Collection;

class PaymentTrashProvider implements TrashProvider
{
    public function __construct(private readonly PaymentService $payments) {}

    public function key(): string
    {
        return 'payments';
    }

    public function label(): string
    {
        return 'Tahsilatlar';
    }

    public function canView(User $user): bool
    {
        return $user->can(Permission::PaymentsView->value);
    }

    public function canRestore(User $user): bool
    {
        return $user->can(Permission::PaymentsCreate->value);
    }

    public function items(): Collection
    {
        return $this->payments->trashed()->map(fn (Payment $payment): TrashItem => new TrashItem(
            id: $payment->id,
            title: ($payment->company?->name ?? '—').' · '.$payment->amountMoney()->format($payment->currency),
            detail: $payment->payment_date->format('d.m.Y')
                .' · '.($payment->payment_method?->label() ?? 'Yöntem yok')
                .($payment->receipt_no ? ' · '.$payment->receipt_no : '')
                .($payment->cancellation_reason ? ' · '.$payment->cancellation_reason : ''),
            trashedAt: $payment->trashed_at,
            trashedBy: $payment->trasher?->name,
            url: route('payments.show', $payment),
        ));
    }

    public function count(): int
    {
        return $this->payments->trashedCount();
    }

    public function restore(int $id, User $actor): void
    {
        $this->payments->restoreFromTrash(Payment::query()->findOrFail($id), $actor);
    }

    public function forceDelete(int $id, User $actor): void
    {
        $this->payments->forceDelete(Payment::query()->findOrFail($id), $actor);
    }
}
