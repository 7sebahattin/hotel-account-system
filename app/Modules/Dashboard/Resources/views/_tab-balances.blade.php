{{--
    "Borç / Alacak": seçili para biriminde bakiyesi sıfırdan farklı hesaplar,
    mutlak tutara göre. Yön her zaman metinle de yazılır (renk tek başına
    anlam taşımaz, CLAUDE.md §11.6).
--}}
@php $currency = $summary->selectedCurrency; @endphp

@forelse ($summary->accountBalances as $row)
    <a href="{{ route('statements.show', $row->companyId) }}"
       @class(['flex min-h-11 items-center justify-between gap-3 px-4 py-3 hover:bg-well', 'border-t border-line' => ! $loop->first])>
        <span class="min-w-0">
            <span class="block truncate text-sm text-tx">{{ $row->companyName }}</span>
            <span class="text-xs text-mut">{{ $row->balance->isNegative() ? 'Biz borçluyuz' : 'Bize borçlu' }}</span>
        </span>
        <span @class([
            'shrink-0 text-sm font-medium tabular-nums',
            'text-debit' => ! $row->balance->isNegative(),
            'text-credit' => $row->balance->isNegative(),
        ])>
            {{ $row->balance->isNegative() ? 'Alacak' : 'Borç' }}
            {{ ($row->balance->isNegative() ? $row->balance->negate() : $row->balance)->format($currency) }}
        </span>
    </a>
@empty
    <x-ui.empty class="m-4 border-0 bg-transparent py-8">{{ $currency->value }} için açık bakiyesi olan hesap yok.</x-ui.empty>
@endforelse

<div class="border-t border-line px-4 py-1">
    <a href="{{ route('statements.index') }}" class="inline-flex min-h-11 items-center text-sm text-acc-tx underline underline-offset-2 hover:text-tx">Bakiye listesi</a>
</div>
