@forelse ($amounts as $code => $amount)
    <div class="monthly-amount-line">
        <span class="monthly-amount-primary tabular-nums">{{ $amount['amount']->format(\App\Modules\Core\Enums\Currency::from($code)) }}</span>
        @if ($code !== 'TRY')
            <span class="monthly-amount-currency text-xs">{{ $code }}</span>
            <x-ui.fx-note :equivalent="$amount['try_equivalent']" />
        @endif
    </div>
@empty
    <span class="text-mut">—</span>
@endforelse
