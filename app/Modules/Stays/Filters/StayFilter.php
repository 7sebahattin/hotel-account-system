<?php

declare(strict_types=1);

namespace App\Modules\Stays\Filters;

use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Filters\QueryFilter;
use App\Modules\Stays\Enums\StayStatus;
use Illuminate\Database\Eloquent\Builder;

class StayFilter extends QueryFilter
{
    /**
     * @return array<int, string>
     */
    protected function searchable(): array
    {
        return ['guest_name', 'room_no'];
    }

    /**
     * @return array<int, string>
     */
    protected function sortable(): array
    {
        return ['check_in_date', 'check_out_date', 'guest_name', 'amount', 'nights', 'created_at'];
    }

    protected function defaultSort(): string
    {
        return 'check_in_date';
    }

    /** Liste varsayılanı en yeni konaklama üstte. */
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

        // Tarih aralığı giriş tarihi üzerinden uygulanır.
        if ($this->request->filled('date_from')) {
            $query->whereDate('check_in_date', '>=', $this->request->date('date_from'));
        }

        if ($this->request->filled('date_to')) {
            $query->whereDate('check_in_date', '<=', $this->request->date('date_to'));
        }

        $currency = (string) $this->request->query('currency', '');

        if (Currency::tryFrom($currency) !== null) {
            $query->where('currency', $currency);
        }

        $status = (string) $this->request->query('status', '');

        if (StayStatus::tryFrom($status) !== null) {
            $query->where('status', $status);
        }
    }
}
