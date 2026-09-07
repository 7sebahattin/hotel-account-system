<?php

declare(strict_types=1);

namespace App\Modules\Core\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Liste ekranlarının ortak filtre/sıralama/sayfalama altyapısı (CLAUDE.md §9).
 *
 * Sıralama alanları beyaz liste ile sınırlıdır; istekten gelen serbest kolon adı
 * asla sorguya girmez.
 */
abstract class QueryFilter
{
    /** Sayfa boyutu için izin verilen değerler. */
    final public const PER_PAGE_OPTIONS = [25, 50, 100];

    final public const DEFAULT_PER_PAGE = 25;

    /**
     * Metin aramasının uygulanacağı kolonlar.
     *
     * @return array<int, string>
     */
    abstract protected function searchable(): array;

    /**
     * Sıralamaya izin verilen kolonlar (beyaz liste).
     *
     * @return array<int, string>
     */
    abstract protected function sortable(): array;

    abstract protected function defaultSort(): string;

    public function __construct(protected readonly Request $request) {}

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @return Builder<covariant \Illuminate\Database\Eloquent\Model>
     */
    public function apply(Builder $query): Builder
    {
        $this->applySearch($query);
        $this->applyFilters($query);
        $this->applySort($query);

        return $query;
    }

    /** Modüle özel filtreler; alt sınıf doldurur. */
    protected function applyFilters(Builder $query): void {}

    public function perPage(): int
    {
        $requested = $this->request->integer('per_page', self::DEFAULT_PER_PAGE);

        return in_array($requested, self::PER_PAGE_OPTIONS, true)
            ? $requested
            : self::DEFAULT_PER_PAGE;
    }

    /**
     * Serbest metin araması — Türkçe'nin iki i'sine duyarlı.
     *
     * Türkçe'de büyük harfe çevirim çift yönlüdür: i↔İ ve ı↔I. Bu yüzden hiçbir
     * collation tek başına yetmez:
     *   - utf8mb4_turkish_ci: "ilgaz" araması ALL CAPS "ILGAZ" kaydını bulamaz.
     *   - utf8mb4_unicode_ci: "çınar" araması ALL CAPS "ÇINAR" kaydını bulamaz.
     * Türk firma verisi çoğunlukla tümü büyük harfle girilir; iki durum da gerçektir.
     *
     * Çözüm: karşılaştırmadan önce her iki tarafta da noktalı/noktasız i tek
     * karaktere indirgenir, ardından utf8mb4_unicode_ci ile karşılaştırılır.
     * Sıralama bundan etkilenmez; ORDER BY kolonun utf8mb4_turkish_ci
     * collation'ını kullanmaya devam eder (bkz. applySort).
     *
     * Maliyet: ifade indeks kullanmaz. LIKE '%...%' zaten indeks kullanmadığı
     * için ek kayıp yoktur ve V1 veri hacminde (yılda binlerce kayıt) kabul edilebilir.
     */
    protected function applySearch(Builder $query): void
    {
        $term = trim((string) $this->request->query('q', ''));

        if ($term === '' || $this->searchable() === []) {
            return;
        }

        $pattern = '%'.addcslashes($this->foldTurkishI($term), '%_\\').'%';

        $query->where(function (Builder $inner) use ($pattern): void {
            foreach ($this->searchable() as $column) {
                // Kolon adları beyaz listeden gelir; değer bağlanarak geçirilir.
                $inner->orWhereRaw(
                    sprintf(
                        "REPLACE(REPLACE(`%s`, 'ı', 'i'), 'İ', 'i') COLLATE utf8mb4_unicode_ci LIKE ?",
                        $column,
                    ),
                    [$pattern],
                );
            }
        });
    }

    /** Noktasız ı ve noktalı İ harflerini i'ye indirger. */
    private function foldTurkishI(string $value): string
    {
        return str_replace(['ı', 'İ'], ['i', 'i'], $value);
    }

    protected function applySort(Builder $query): void
    {
        $sort = (string) $this->request->query('sort', $this->defaultSort());

        if (! in_array($sort, $this->sortable(), true)) {
            $sort = $this->defaultSort();
        }

        $direction = strtolower((string) $this->request->query('direction', 'asc')) === 'desc'
            ? 'desc'
            : 'asc';

        // name üzerinde sıralama kolonun utf8mb4_turkish_ci collation'ını kullanır:
        // Ç>C, ı<i, Ö>O, Ş>S, Ü>U doğru sıralanır.
        $query->orderBy($sort, $direction);
    }

    /** Görünümde sıralama bağlantıları için geçerli sıralama durumu. */
    public function currentSort(): string
    {
        $sort = (string) $this->request->query('sort', $this->defaultSort());

        return in_array($sort, $this->sortable(), true) ? $sort : $this->defaultSort();
    }

    public function currentDirection(): string
    {
        return strtolower((string) $this->request->query('direction', 'asc')) === 'desc' ? 'desc' : 'asc';
    }
}
