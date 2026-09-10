<?php

declare(strict_types=1);

namespace App\Modules\CashBook\Filters;

use App\Modules\CashBook\Enums\CashMovementDirection;
use App\Modules\CashBook\Enums\CashMovementType;
use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Filters\QueryFilter;
use Illuminate\Database\Eloquent\Builder;

/**
 * Kasa hareketleri listesi (Gün 12 §8) — kasa defterinin EN ÖNEMLİ filtre
 * alanı. Yetki kapsaması (personel yalnızca kendi hareketlerini görür)
 * BURADA değil, `CashMovementController::index()`'te — bu sınıf yalnızca
 * ZATEN kapsamlanmış bir sorguyu daraltır (kullanıcı talimatı §17).
 */
class CashMovementFilter extends QueryFilter
{
    /**
     * @return array<int, string>
     */
    protected function searchable(): array
    {
        return ['description', 'counterparty'];
    }

    /**
     * @return array<int, string>
     */
    protected function sortable(): array
    {
        return ['entry_at', 'amount', 'created_at'];
    }

    protected function defaultSort(): string
    {
        return 'entry_at';
    }

    /** Liste varsayılanı en yeni hareket üstte. */
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
        if ($this->request->filled('date_from')) {
            $query->whereDate('entry_at', '>=', $this->request->date('date_from'));
        }

        if ($this->request->filled('date_to')) {
            $query->whereDate('entry_at', '<=', $this->request->date('date_to'));
        }

        $currency = Currency::tryFrom((string) $this->request->query('currency', ''));

        if ($currency !== null) {
            $query->where('currency', $currency->value);
        }

        // Tekil değer (`type=expense`) ve çoklu değer (`type[]=room_income&
        // type[]=extra_income`, "Gelirler" hızlı filtresi için) ikisi de
        // desteklenir — yeni bir sorgu mantığı DEĞİL, aynı beyaz listenin
        // birden çok değerle uygulanmasıdır.
        $typeParam = $this->request->query('type');
        $types = collect(is_array($typeParam) ? $typeParam : [$typeParam])
            ->map(static fn ($value): ?CashMovementType => CashMovementType::tryFrom((string) $value))
            ->filter()
            ->map(static fn (CashMovementType $type): string => $type->value)
            ->values()
            ->all();

        if ($types !== []) {
            $query->whereIn('type', $types);
        }

        $direction = CashMovementDirection::tryFrom((string) $this->request->query('direction_filter', ''));

        if ($direction !== null) {
            $query->where('direction', $direction->value);
        }

        if ($this->request->filled('session_id')) {
            $query->where('cash_session_id', $this->request->integer('session_id'));
        }

        // `user_id`: yetki kapsaması controller'da zaten uygulanmıştır; bu
        // yalnızca o kapsam İÇİNDE ek bir daraltmadır (view_all olan bir
        // yönetici "yalnızca Mehmet'in hareketleri" diyebilsin diye). Personel
        // için değeri kendi id'sinden FARKLI olsa bile erişimi GENİŞLETMEZ —
        // kapsam zaten AND'lenmiştir, en kötü ihtimalle sonuç boş döner.
        if ($this->request->filled('user_id')) {
            $query->where('created_by', $this->request->integer('user_id'));
        }
    }
}
