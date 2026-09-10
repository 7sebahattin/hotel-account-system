{{--
    Yabancı para birimli tutarların TL karşılığı — küçük, soluk, İKİNCİL
    metin (v2.1.1-C §2). Tabloları KALABALIKLAŞTIRMAZ: yalnızca kur KAYITLI
    ise render edilir, geçmiş boşluğu (eksik kur) burada SESSİZCE atlanır —
    "Geçmiş Döviz Düzeltme" ekranı bu boşluğu ayrıca listeler.

    Kullanım: <x-ui.fx-note :rate="$stay->exchange_rate_to_try" :equivalent="$stay->tryEquivalentMoney()" />
--}}
@props(['rate' => null, 'equivalent' => null])

@php
    // Kur DECIMAL(10,4) olarak metin gelir ('51.2000'); kayan nokta
    // kullanılmadan Türkçe gösterime çevrilir (CLAUDE.md §13).
    $formatRate = static function (?string $rate): ?string {
        if ($rate === null) {
            return null;
        }

        [$whole, $fraction] = array_pad(explode('.', $rate, 2), 2, '00');
        $fraction = rtrim($fraction, '0');
        $fraction = str_pad($fraction, 2, '0');

        $grouped = strrev(implode('.', str_split(strrev($whole), 3)));

        return $grouped.','.$fraction;
    };

    $formattedRate = $formatRate($rate);
@endphp

@if ($formattedRate !== null && $equivalent !== null)
    <p {{ $attributes->class(['mt-0.5 text-xs text-mut tabular-nums']) }}>
        ≈ {{ $equivalent->format(\App\Modules\Core\Enums\Currency::TRY) }}
        <span class="text-mut">· Kur: {{ $formattedRate }}</span>
    </p>
@endif
