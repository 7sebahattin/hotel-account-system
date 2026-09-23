<?php

declare(strict_types=1);

namespace App\Modules\Stays\Filters;

use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Filters\QueryFilter;
use App\Modules\Payments\Services\PaymentAllocationService;
use App\Modules\Stays\Enums\StayStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class StayFilter extends QueryFilter
{
    /** Hızlı filtreler: `?hizli=` anahtarı → düğme etiketi. */
    public const array QUICK_FILTERS = [
        'bu-ay' => 'Bu ay',
        'gecen-ay' => 'Geçen ay',
        'aktif' => 'Aktif konaklamalar',
        'odenmeyen' => 'Ödenmeyenler',
        'bugun-giris' => 'Bugün giriş',
        'bugun-cikis' => 'Bugün çıkış',
    ];

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

    /**
     * Temel arama `stays` kolonlarıyla sınırlıdır; firma adı ayrı bir
     * tablodadır (kullanıcı talimatı Gün 12 §4). Aynı Türkçe katlama
     * mantığı (`turkishFoldColumnExpression()`) `whereHas` içinde de
     * kullanılır — SQL metni tekrar yazılmaz.
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

            $inner->orWhereHas('company', function (Builder $companyQuery) use ($pattern): void {
                $companyQuery->whereRaw($this->turkishFoldColumnExpression('name').' LIKE ?', [$pattern]);
            });

            // Oda Registry (K-100): yeni kayıtlarda `room_no` boş kalır, oda
            // bilgisi `rooms.room_no` üzerindendir — arama bunu da kapsar.
            $inner->orWhereHas('room', function (Builder $roomQuery) use ($pattern): void {
                $roomQuery->whereRaw($this->turkishFoldColumnExpression('room_no').' LIKE ?', [$pattern]);
            });
        });
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

        $this->applyQuickFilter($query);
    }

    /** Geçerli hızlı filtre anahtarı; tanınmayan değer yok sayılır. */
    public function quickFilter(): ?string
    {
        $key = (string) $this->request->query('hizli', '');

        return array_key_exists($key, self::QUICK_FILTERS) ? $key : null;
    }

    /**
     * Hızlı filtreler (23 Eylül 2026 liste tasarımı). Diğer filtrelerle AND
     * ile birleşir; yeni bir yetki/kapsam açmaz. "Bu ay"/"Geçen ay" giriş
     * tarihine göredir (tarih aralığı filtresiyle aynı kolon).
     */
    private function applyQuickFilter(Builder $query): void
    {
        $today = Carbon::today();

        match ($this->quickFilter()) {
            'bu-ay' => $query->whereBetween('check_in_date', [
                $today->copy()->startOfMonth()->toDateString(), $today->copy()->endOfMonth()->toDateString(),
            ]),
            'gecen-ay' => $query->whereBetween('check_in_date', [
                $today->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(),
                $today->copy()->subMonthNoOverflow()->endOfMonth()->toDateString(),
            ]),
            // Bu gece otelde: giriş yapılmış, çıkış günü henüz gelmemiş.
            'aktif' => $query->where('status', StayStatus::Active->value)
                ->whereDate('check_in_date', '<=', $today)
                ->whereDate('check_out_date', '>', $today),
            // Kalan tutarı olan aktif konaklamalar: geçerli ödeme dağılımı
            // toplamı konaklama tutarından az (hiç ödenmemiş veya kısmen ödenmiş).
            'odenmeyen' => $this->whereUnpaid($query),
            'bugun-giris' => $query->whereDate('check_in_date', $today),
            'bugun-cikis' => $query->whereDate('check_out_date', $today),
            default => null,
        };
    }

    private function whereUnpaid(Builder $query): void
    {
        $paid = app(PaymentAllocationService::class)->paidAmountSubquery('stays.id');

        $query->where('status', StayStatus::Active->value)
            ->whereRaw('stays.amount > COALESCE(('.$paid->toSql().'), 0)', $paid->getBindings());
    }
}
