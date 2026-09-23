{{-- "Tahsilatlar": son tahsilatlar (iptaller dahil — pano gerçeği gizlemez). --}}
@forelse ($summary->recentPayments as $payment)
    <a href="{{ route('payments.show', $payment) }}"
       @class(['block px-4 py-3 hover:bg-well', 'border-t border-line' => ! $loop->first])>
        <div class="flex items-baseline justify-between gap-3">
            <span class="min-w-0 truncate text-sm text-tx">
                {{ $summary->companyName((int) $payment->company_id) }}
            </span>

            {{-- İptal edilmiş tahsilat "alacak" rengiyle gösterilmez. --}}
            <span class="shrink-0 text-right">
                <span @class([
                    'block text-sm font-medium tabular-nums',
                    'text-credit' => ! $payment->isCancelled(),
                    'text-mut line-through' => $payment->isCancelled(),
                ])>{{ $payment->amountMoney()->format($payment->currency) }}</span>
                <x-ui.fx-note :rate="$payment->exchange_rate_to_try" :equivalent="$payment->tryEquivalentMoney()" class="text-right" />
            </span>
        </div>

        <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-mut">
            <span class="tabular-nums">{{ $payment->payment_date->format('d.m.Y') }}</span>

            @if ($payment->payment_method)
                <span aria-hidden="true">·</span>
                <span>{{ $payment->payment_method->label() }}</span>
            @endif

            @if ($payment->isCancelled())
                <x-ui.badge tone="danger">İptal</x-ui.badge>
            @endif
        </div>
    </a>
@empty
    <x-ui.empty class="m-4 border-0 bg-transparent py-8">Henüz tahsilat yok.</x-ui.empty>
@endforelse

<div class="border-t border-line px-4 py-1">
    <a href="{{ route('payments.index') }}" class="inline-flex min-h-11 items-center text-sm text-acc-tx underline underline-offset-2 hover:text-tx">Tüm tahsilatlar</a>
</div>
