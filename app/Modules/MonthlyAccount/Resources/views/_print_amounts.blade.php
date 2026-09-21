@forelse ($amounts as $code => $amount)
    <div class="print-amount">
        <span class="print-amount-primary">{{ $amount['amount']->format(\App\Modules\Core\Enums\Currency::from($code)) }}@if ($code !== 'TRY') {{ $code }}@endif</span>
        @if ($code !== 'TRY')
            <span class="print-amount-fx">≈ {{ $amount['try_equivalent']->format(\App\Modules\Core\Enums\Currency::TRY) }}</span>
        @endif
    </div>
@empty
    <span class="print-empty">—</span>
@endforelse
