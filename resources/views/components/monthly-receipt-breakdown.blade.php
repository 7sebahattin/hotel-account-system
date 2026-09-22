@props(['summaryData' => null])

@php
    /** @var array<string, mixed>|null $summaryData */
    $try = \App\Modules\Core\Enums\Currency::TRY;
    $receiptBreakdownMethods = [
        'cash' => 'Nakit',
        'credit_card' => 'Kredi kartı',
        'link' => 'Link ödeme',
        'bank_transfer' => 'Havale',
        'extra' => 'Ekstra',
    ];
    $methodTotals = [];

    if ($summaryData !== null) {
        foreach ($receiptBreakdownMethods as $method => $label) {
            $total = \App\Modules\Core\Support\Money::zero();

            foreach (($summaryData['methods'][$method] ?? []) as $amount) {
                $total = $total->add($amount['try_equivalent']);
            }

            $methodTotals[$method] = $total;
        }
    }
@endphp

@if ($summaryData !== null)
    <div {{ $attributes->class(['receipt-breakdown', 'space-y-0.5', 'text-[11px]', 'leading-tight']) }}>
        @foreach ($receiptBreakdownMethods as $method => $label)
            <div class="receipt-breakdown-line flex justify-between gap-2">
                <span class="receipt-breakdown-label whitespace-nowrap opacity-80">{{ $label }}:</span>
                <span class="receipt-breakdown-value ml-auto whitespace-nowrap font-medium tabular-nums">{{ $methodTotals[$method]->format($try) }}</span>
            </div>
        @endforeach
        <div class="receipt-breakdown-line receipt-breakdown-total mt-1 flex justify-between gap-2 border-t border-line pt-1 font-semibold">
            <span class="receipt-breakdown-label whitespace-nowrap">Toplam:</span>
            <span class="receipt-breakdown-value ml-auto whitespace-nowrap tabular-nums">{{ $summaryData['receipt_total']->format($try) }}</span>
        </div>
    </div>
@else
    <span class="money-nowrap">—</span>
@endif
