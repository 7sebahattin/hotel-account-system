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
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            dateFrom: $request->filled('date_from') ? $request->date('date_from')?->startOfDay() : null,
            dateTo: $request->filled('date_to') ? $request->date('date_to')?->startOfDay() : null,
            currency: Currency::tryFrom((string) $request->query('currency', '')),
            type: LedgerEntryType::tryFrom((string) $request->query('type', '')),
            search: $request->filled('q') ? trim((string) $request->query('q')) : null,
            newestFirst: $request->query('direction') === 'desc',
        );
    }

    public function hasDisplayFilters(): bool
    {
        return $this->type !== null || $this->search !== null;
    }

    /** @return array<string, string> Sayfalama ve yazdırma bağlantıları için. */
    public function toQuery(): array
    {
        return array_filter([
            'date_from' => $this->dateFrom?->toDateString(),
            'date_to' => $this->dateTo?->toDateString(),
            'currency' => $this->currency?->value,
            'type' => $this->type?->value,
            'q' => $this->search,
            'direction' => $this->newestFirst ? 'desc' : null,
        ], fn ($value) => $value !== null && $value !== '');
    }
}
