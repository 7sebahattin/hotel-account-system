<?php

declare(strict_types=1);

namespace App\Modules\Companies\Filters;

use App\Modules\Core\Filters\QueryFilter;
use Illuminate\Database\Eloquent\Builder;

class CompanyFilter extends QueryFilter
{
    /**
     * @return array<int, string>
     */
    protected function searchable(): array
    {
        return ['name', 'tax_number', 'phone', 'email', 'contact_person', 'city'];
    }

    /**
     * @return array<int, string>
     */
    protected function sortable(): array
    {
        return ['name', 'city', 'tax_number', 'created_at'];
    }

    protected function defaultSort(): string
    {
        return 'name';
    }

    protected function applyFilters(Builder $query): void
    {
        if (in_array($this->request->query('account_type'), ['company', 'individual'], true)) {
            $query->where('account_type', $this->request->query('account_type'));
        }
        if ($this->request->filled('city')) {
            $query->where('city', $this->request->string('city')->value());
        }

        // '1' / '0' dışındaki değerler (boş = tümü) filtre uygulamaz.
        $active = $this->request->query('is_active');

        if ($active === '1' || $active === '0') {
            $query->where('is_active', $active === '1');
        }
    }
}
