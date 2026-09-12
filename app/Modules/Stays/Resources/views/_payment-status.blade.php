@php $summary = $paymentSummaries[$stay->id] ?? $paymentSummary ?? null; @endphp
@if ($summary)
    <div class="mt-2 text-xs text-mut">
        <span class="block font-medium text-tx">{{ $summary['label'] }}</span>
        @unless ($stay->isCancelled())
            <span class="block tabular-nums">Ödenen: {{ $summary['paid']->format($stay->currency) }}</span>
            <span class="block tabular-nums">{{ $summary['legacy'] ? 'Eşleştirme sonrası kalan' : 'Kalan' }}: {{ $summary['remaining']->format($stay->currency) }}</span>
        @endunless
    </div>
@endif
