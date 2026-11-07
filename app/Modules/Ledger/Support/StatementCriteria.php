<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Support;

use App\Modules\Core\Enums\Currency;
use App\Modules\Ledger\Enums\LedgerEntryType;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Ekstre filtreleri.
 *
 * İki filtre türü ayrılır ve bu ayrım bilinçlidir:
 *
 * - `dateFrom/dateTo/currency` bakiyenin EVRENİNİ belirler; SQL'de uygulanır
 *   ve devreden bakiye bunlara göre hesaplanır.
 * - `type/search` yalnızca GÖSTERİMİ daraltır; yürüyen bakiye hesaplandıktan
 *   SONRA uygulanır. Aksi hâlde "yalnızca tahsilatlar" seçen bir kullanıcıya
 *   gerçek bakiyesi olmayan bir kolon gösterilirdi.
 */
final readonly class StatementCriteria
{
    public function __construct(
        public ?Carbon $dateFrom = null,
        public ?Carbon $dateTo = null,
        public ?Currency $currency = null,
        public ?LedgerEntryType $type = null,
        public ?string $search = null,
        public bool $newestFirst = false,
        public ?string $quick = null,
    ) {}

    /**
     * Hızlı dönem filtreleri (7 Kasım 2026). Seçilince tarih aralığı SUNUCUDA belirlenir
     * ve özet yalnızca CARİ BAKİYEYİ gösterir (borç/alacak toplamı ve devreden gizlenir).
     */
    public const QUICK_RANGES = ['bu-ay' => 'Bu ay', 'gecen-ay' => 'Geçen ay'];

    public static function fromRequest(Request $request): self
    {
        $requested = (string) $request->query('hizli', '');
        $quick = array_key_exists($requested, self::QUICK_RANGES) ? $requested : null;

        $dateFrom = $request->filled('date_from') ? $request->date('date_from')?->startOfDay() : null;
        $dateTo = $request->filled('date_to') ? $request->date('date_to')?->startOfDay() : null;

        if ($quick !== null) {
            $month = $quick === 'gecen-ay' ? Carbon::now()->subMonthNoOverflow() : Carbon::now();
            $dateFrom = $month->copy()->startOfMonth()->startOfDay();
            $dateTo = $month->copy()->endOfMonth()->startOfDay();
        }

        return new self(
            dateFrom: $dateFrom,
            dateTo: $dateTo,
            currency: Currency::tryFrom((string) $request->query('currency', '')),
            type: LedgerEntryType::tryFrom((string) $request->query('type', '')),
            search: $request->filled('q') ? trim((string) $request->query('q')) : null,
            newestFirst: $request->query('direction', 'desc') !== 'asc',
            quick: $quick,
        );
    }

    /** Hızlı dönem seçiliyse özet yalnızca cari bakiyeyi gösterir. */
    public function isBalanceOnly(): bool
    {
        return $this->quick !== null;
    }

    public function hasDisplayFilters(): bool
    {
        return $this->type !== null || $this->search !== null;
    }

    /** @return array<string, string> Sayfalama ve yazdırma bağlantıları için. */
    public function toQuery(): array
    {
        return array_filter([
            'hizli' => $this->quick,
            // Hızlı dönemde tarihler `hizli`den türer; ayrıca taşınmaz.
            'date_from' => $this->quick === null ? $this->dateFrom?->toDateString() : null,
            'date_to' => $this->quick === null ? $this->dateTo?->toDateString() : null,
            'currency' => $this->currency?->value,
            'type' => $this->type?->value,
            'q' => $this->search,
            'direction' => $this->newestFirst ? null : 'asc',
        ], fn ($value) => $value !== null && $value !== '');
    }
}
