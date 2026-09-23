{{--
    Konaklamanın ödeme durumu: rozet + ödenen/kalan. Rozet rengi tek başına
    anlam taşımaz — etiket her zaman yazılıdır (CLAUDE.md §11.6). "Ödenmedi"
    kırmızı (iptal/tehlike) DEĞİL gül tonudur; iptal ayrı, kırmızı rozettir.
--}}
@php
    $summary = $paymentSummaries[$stay->id] ?? $paymentSummary ?? null;
    $tones = [
        'Ödendi' => 'bg-success-bg text-success ring-success/20',
        'Kısmen ödendi' => 'bg-tint-amber-bg text-tint-amber-tx ring-tint-amber-line',
        'Ödenmedi' => 'bg-tint-rose-bg text-tint-rose-tx ring-tint-rose-line',
        'İptal' => 'bg-danger-bg text-danger ring-danger/20',
    ];
@endphp
@if ($summary)
    <div class="text-xs" data-payment-status>
        <span @class([
            'inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2 py-0.5 font-medium ring-1 ring-inset',
            $tones[$summary['label']] ?? 'bg-tint-blue-bg text-tint-blue-tx ring-tint-blue-line',
        ])>
            <span class="size-1.5 shrink-0 rounded-full bg-current" aria-hidden="true"></span>
            {{ $summary['label'] }}
        </span>
        @unless ($stay->isCancelled())
            <span class="mt-1 block whitespace-nowrap tabular-nums text-mut">Ödenen: {{ $summary['paid']->format($stay->currency) }}</span>
            <span class="block whitespace-nowrap tabular-nums text-mut">{{ $summary['legacy'] ? 'Eşleştirme sonrası kalan' : 'Kalan' }}: {{ $summary['remaining']->format($stay->currency) }}</span>
        @endunless
    </div>
@endif
