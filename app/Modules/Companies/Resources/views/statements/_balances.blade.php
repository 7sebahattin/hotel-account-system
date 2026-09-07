{{-- Para birimi bazında bakiye. Farklı para birimleri ASLA toplanmaz. --}}
@forelse ($companyBalances as $code => $balance)
    @php $currency = \App\Modules\Core\Enums\Currency::from($code); @endphp
    <span class="mr-3 inline-block whitespace-nowrap text-sm">
        <span @class([
            'tabular-nums',
            'text-danger' => $balance->isPositive(),
            'text-success' => $balance->isNegative(),
            'text-mut' => $balance->isZero(),
        ])>{{ $balance->format($currency) }}</span>
        <span class="text-xs text-mut">
            @if ($balance->isPositive()) borçlu
            @elseif ($balance->isNegative()) alacaklı
            @else kapalı
            @endif
        </span>
    </span>
@empty
    <span class="text-sm text-mut">Hareket yok</span>
@endforelse
