<?php

declare(strict_types=1);

namespace App\Modules\CashBook\Filters;

use App\Modules\CashBook\Enums\CashTransferStatus;
use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Filters\QueryFilter;
use Illuminate\Database\Eloquent\Builder;

/**
 * Devir geçmişi listesi (Gün 12 §10). Yetki kapsaması (personel yalnızca
 * kendi devirlerini görür) `CashTransferController::index()`'te uygulanır.
 */
class CashTransferFilter extends QueryFilter
{
    /**
     * @return array<int, string>
     */
    protected function searchable(): array
    {
        return ['note'];
    }

    /**
     * @return array<int, string>
     */
    protected function sortable(): array
    {
        return ['initiated_at', 'responded_at'];
    }

    protected function defaultSort(): string
    {
        return 'initiated_at';
    }

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

    /**
     * Temel arama yalnızca `note` kolonunu kapsar; "teslim eden/alan adı"
     * (kullanıcı talimatı §10) ayrı tablodadır. Aynı Türkçe katlama SQL'i
     * (`turkishFoldColumnExpression()`) ilişkili tabloda da kullanılır.
     */
    protected function applySearch(Builder $query): void
    {
        $term = trim((string) $this->request->query('q', ''));

        if ($term === '') {
            return;
        }

        $pattern = $this->searchPattern();

        $query->where(function (Builder $inner) use ($pattern): void {
            foreach ($this->searchable() as $column) {
                $inner->orWhereRaw($this->turkishFoldColumnExpression($column).' LIKE ?', [$pattern]);
            }

            $inner->orWhereHas('fromUser', function (Builder $userQuery) use ($pattern): void {
                $userQuery->whereRaw($this->turkishFoldColumnExpression('name').' LIKE ?', [$pattern]);
            });

            $inner->orWhereHas('toUser', function (Builder $userQuery) use ($pattern): void {
                $userQuery->whereRaw($this->turkishFoldColumnExpression('name').' LIKE ?', [$pattern]);
            });
        });
    }

    protected function applyFilters(Builder $query): void
    {
        if ($this->request->filled('date_from')) {
            $query->whereDate('initiated_at', '>=', $this->request->date('date_from'));
        }

        if ($this->request->filled('date_to')) {
            $query->whereDate('initiated_at', '<=', $this->request->date('date_to'));
        }

        $status = CashTransferStatus::tryFrom((string) $this->request->query('status', ''));

        if ($status !== null) {
            $query->where('status', $status->value);
        }

        // Yetki kapsaması controller'da zaten uygulanmıştır (§17); bunlar o
        // kapsam İÇİNDE ek daraltmadır — bkz. CashMovementFilter aynı yorum.
        if ($this->request->filled('from_user_id')) {
            $query->where('from_user_id', $this->request->integer('from_user_id'));
        }

        if ($this->request->filled('to_user_id')) {
            $query->where('to_user_id', $this->request->integer('to_user_id'));
        }

        $currency = Currency::tryFrom((string) $this->request->query('currency', ''));

        if ($currency !== null) {
            $query->whereHas('amounts', function (Builder $amountQuery) use ($currency): void {
                $amountQuery->where('currency', $currency->value);
            });
        }
    }
}
