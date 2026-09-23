{{-- Para birimi bazında bakiye. Farklı para birimleri ASLA toplanmaz. --}}
@forelse ($companyBalances as $code => $balance)
    @php
        $currency = \App\Modules\Core\Enums\Currency::from($code);
        $balanceFx = ($companyFx ?? [])[$code] ?? null;
    @endphp
    <span class="mr-3 inline-block whitespace-nowrap align-top text-sm">
        <span @class([
            'tabular-nums',
            'text-debit' => $balance->isPositive(),
            'text-credit' => $balance->isNegative(),
            'text-success' => $balance->isZero(),
        ])>{{ $balance->format($currency) }}</span>
        <span class="text-xs text-mut">
            @if ($balance->isPositive()) borçlu
            @elseif ($balance->isNegative()) alacaklı
            @else kapalı
            @endif
        </span>
        @if ($currency !== \App\Modules\Core\Enums\Currency::TRY && $balanceFx)
            <x-ui.fx-note :equivalent="$balanceFx['net']" />
            @if (! $balanceFx['complete'])
                <span class="block text-xs text-mut">eksik veri</span>
            @endif
        @endif
    </span>
@empty
    <span class="text-sm text-mut">Hareket yok</span>
@endforelse
