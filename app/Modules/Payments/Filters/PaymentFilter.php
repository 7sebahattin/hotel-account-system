<?php

declare(strict_types=1);

namespace App\Modules\Payments\Filters;

use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Filters\QueryFilter;
use App\Modules\Payments\Enums\PaymentMethod;
use App\Modules\Payments\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;

class PaymentFilter extends QueryFilter
{
    /**
     * @return array<int, string>
     */
    protected function searchable(): array
    {
        return ['reference_no', 'description'];
    }

    /**
     * @return array<int, string>
     */
    protected function sortable(): array
    {
        return ['payment_date', 'amount', 'reference_no', 'created_at'];
    }

    protected function defaultSort(): string
    {
        return 'payment_date';
    }

    /** Liste varsayılanı en yeni tahsilat üstte. */
    public function currentDirection(): string
    {
        return strtolower((string) $this->request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
    }

    protected function applySort(Builder $query): void
    {
        $sort = (string) $this->request->query('sort', $this->defaultSort());

        if (! in_array($sort, $this->sortable(), true)) {
            $sort = $this->defaultSort();
        }

        $query->orderBy($sort, $this->currentDirection())->orderByDesc('id');
    }

    protected function applyFilters(Builder $query): void
    {
        if ($this->request->filled('company_id')) {
            $query->where('company_id', $this->request->integer('company_id'));
        }

        if ($this->request->filled('date_from')) {
            $query->whereDate('payment_date', '>=', $this->request->date('date_from'));
        }

        if ($this->request->filled('date_to')) {
            $query->whereDate('payment_date', '<=', $this->request->date('date_to'));
        }

        $currency = (string) $this->request->query('currency', '');

        if (Currency::tryFrom($currency) !== null) {
            $query->where('currency', $currency);
        }

        $method = (string) $this->request->query('payment_method', '');

        if (PaymentMethod::tryFrom($method) !== null) {
            $query->where('payment_method', $method);
        }

        $status = (string) $this->request->query('status', '');

        if (PaymentStatus::tryFrom($status) !== null) {
            $query->where('status', $status);
        }
    }
}
