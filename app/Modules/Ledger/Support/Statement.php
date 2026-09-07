<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Support;

/**
 * Bir firmanın cari ekstresi.
 *
 * Satırlar kronolojiktir (entry_date ASC, id ASC) ve her satır kendi para
 * biriminin yürüyen bakiyesini taşır. Özetler yalnızca hareketi olan para
 * birimleri için üretilir ve ASLA tek toplamda birleştirilmez.
 */
final readonly class Statement
{
    /**
     * @param  array<int, StatementRow>  $rows
     * @param  array<string, CurrencySummary>  $summaries  para birimi koduna göre
     */
    public function __construct(
        public array $rows,
        public array $summaries,
    ) {}

    public function isEmpty(): bool
    {
        return $this->rows === [];
    }

    public function hasSummaries(): bool
    {
        return $this->summaries !== [];
    }

    /** Devreden bakiye taşıyan para birimleri — ekstrenin başında gösterilir. */
    public function carryForwards(): array
    {
        return array_filter($this->summaries, fn (CurrencySummary $s) => $s->hasCarryForward());
    }
}
