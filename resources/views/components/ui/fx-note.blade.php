{{--
    Yabancı para birimli tutarların TL karşılığı — küçük, soluk, İKİNCİL
    metin (v2.1.1-C §2). Tabloları KALABALIKLAŞTIRMAZ: `equivalent` NULL ise
    hiç render edilmez, geçmiş boşluğu (eksik kur) burada SESSİZCE atlanır —
    "Geçmiş Döviz Düzeltme" ekranı bu boşluğu ayrıca listeler.

    İKİ MOD vardır:
    - TEK işlem: `rate` DE verilir → "≈ 25.600,00 TL · Kur: 51,20".
    - TOPLAM/bakiye (v2.1.1-D §3 — birden çok işlemin TOPLAMI, TEK bir kur
      YOKTUR): yalnızca `equivalent` verilir, `rate` boş bırakılır →
      "≈ 25.600,00 TL" (kur satırı olmadan).

    Kullanım: <x-ui.fx-note :rate="$stay->exchange_rate_to_try" :equivalent="$stay->tryEquivalentMoney()" />
              <x-ui.fx-note :equivalent="$runningTl" /> (toplam/bakiye modu)
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

@if ($equivalent !== null)
    <p {{ $attributes->class(['mt-0.5 text-xs text-mut tabular-nums']) }}>
        ≈ {{ $equivalent->format(\App\Modules\Core\Enums\Currency::TRY) }}
        @if ($formattedRate !== null)
            <span class="text-mut">· Kur: {{ $formattedRate }}</span>
        @endif
    </p>
@endif
