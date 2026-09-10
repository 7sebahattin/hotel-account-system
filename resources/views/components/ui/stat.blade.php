@props([
    'label',
    'value',
    'tone' => 'neutral',
    'hint' => null,
])

@php
    /*
        Sayısal özet kutusu.

        Renk YALNIZCA değeri boyar; etiket ve ipucu her zaman metin token'ı
        kullanır. Tek başına renk anlam taşımaz — anlam etikettedir (CLAUDE.md §11.6).

        Tutarlar dikey olarak karşılaştırıldığı için `tabular-nums` kullanılır
        (para birimi kartları alt alta durur ve rakamlar hizalanmalıdır).
    */
    $tones = [
        'neutral' => 'text-tx',
        'debit' => 'text-debit',
        'credit' => 'text-credit',
        'muted' => 'text-mut',
    ];
@endphp

<div {{ $attributes->class('min-w-0') }}>
    <p class="text-xs font-medium uppercase tracking-wide text-mut">{{ $label }}</p>

    {{--
        Tutar ASLA kırpılmaz: "21.250,5…" bir finans ekranında yanlış bilgidir.
        Dar ekranda yazı küçülür ve gerekirse satır kırılır.
    --}}
    <p class="mt-1 text-lg font-semibold break-words tabular-nums sm:text-xl {{ $tones[$tone] ?? $tones['neutral'] }}">
        {{ $value }}
    </p>

    @if ($hint)
        <p class="mt-0.5 text-xs text-mut">{{ $hint }}</p>
    @endif
</div>
