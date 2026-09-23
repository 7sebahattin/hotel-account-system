{{-- "Konaklamalar": devam eden ve yaklaşan konaklamalar (tüm para birimleri; her satır kendi para biriminde). --}}
@forelse ($summary->upcomingStays as $stay)
    <a href="{{ route('stays.show', $stay) }}"
       @class(['block px-4 py-3 hover:bg-well', 'border-t border-line' => ! $loop->first])>
        <div class="flex items-baseline justify-between gap-3">
            <span class="min-w-0 truncate text-sm text-tx">{{ $stay->guest_name }}</span>

            <span class="shrink-0 text-right">
                <span class="block text-sm font-medium tabular-nums text-tx">
                    {{ $stay->amountMoney()->format($stay->currency) }}
                </span>
                <x-ui.fx-note :rate="$stay->exchange_rate_to_try" :equivalent="$stay->tryEquivalentMoney()" class="text-right" />
            </span>
        </div>

        <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-mut">
            <span class="truncate">{{ $summary->companyName((int) $stay->company_id) }}</span>
            <span aria-hidden="true">·</span>
            <span class="tabular-nums">
                {{ $stay->check_in_date->format('d.m.Y') }} – {{ $stay->check_out_date->format('d.m.Y') }}
            </span>
        </div>
    </a>
@empty
    <x-ui.empty class="m-4 border-0 bg-transparent py-8">Devam eden veya yaklaşan konaklama yok.</x-ui.empty>
@endforelse

<div class="border-t border-line px-4 py-1">
    <a href="{{ route('stays.index') }}" class="inline-flex min-h-11 items-center text-sm text-acc-tx underline underline-offset-2 hover:text-tx">Tüm konaklamalar</a>
</div>
