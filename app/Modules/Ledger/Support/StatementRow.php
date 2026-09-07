<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Support;

use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
use App\Modules\Ledger\Enums\LedgerEntryType;
use App\Modules\Ledger\Models\LedgerEntry;

/**
 * Ekstredeki tek satır: cari hareket + O PARA BİRİMİNİN o andaki yürüyen bakiyesi.
 *
 * `runningBalance` yalnızca satırın kendi para birimi içindir; farklı para
 * birimleri asla tek bakiyede toplanmaz (CLAUDE.md §6/L5).
 */
final readonly class StatementRow
{
    public function __construct(
        public LedgerEntry $entry,
        public Money $runningBalance,
    ) {}

    public function type(): LedgerEntryType
    {
        return LedgerEntryType::fromEntry($this->entry);
    }

    public function currency(): Currency
    {
        return $this->entry->currency;
    }

    public function isReversal(): bool
    {
        return $this->entry->is_reversal;
    }
}
