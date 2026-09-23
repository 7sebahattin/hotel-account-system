@props([
    'label',
    'value',
    'icon' => 'chart',
    'tone' => 'blue',
    'hint' => null,
    // İkon yerine kısa metin (ör. para birimi simgesi "₺", "£").
    'glyph' => null,
    // `colored`: etiket ve tutar ton renginde (pano). `plain`: yalnız ikon
    // renkli, etiket/tutar ana metin renginde (liste ekranı özetleri).
    'variant' => 'colored',
])

@php
    use App\Modules\Core\Support\NavIcons;

    /*
        Pano özet kartı (23 Eylül 2026, kullanıcı onaylı §11.1 istisnası).

        Renkli zemin YALNIZCA bu kartta kullanılır. Sınıflar tam yazılır
        (Tailwind yalnızca kaynakta gördüğü sınıf adlarını üretir); renkler
        `--color-tint-*` token'larından gelir, ham hex yoktur. Anlam etikette
        durur, renk tek başına bilgi taşımaz (§11.6). Gölge yoktur; ayrım
        1px sınırla yapılır.
    */
    $tones = [
        'amber' => ['card' => 'border-tint-amber-line bg-tint-amber-bg', 'icon' => 'bg-tint-amber-icon text-tint-amber-tx', 'tx' => 'text-tint-amber-tx'],
        'green' => ['card' => 'border-tint-green-line bg-tint-green-bg', 'icon' => 'bg-tint-green-icon text-tint-green-tx', 'tx' => 'text-tint-green-tx'],
        'blue' => ['card' => 'border-tint-blue-line bg-tint-blue-bg', 'icon' => 'bg-tint-blue-icon text-tint-blue-tx', 'tx' => 'text-tint-blue-tx'],
        'purple' => ['card' => 'border-tint-purple-line bg-tint-purple-bg', 'icon' => 'bg-tint-purple-icon text-tint-purple-tx', 'tx' => 'text-tint-purple-tx'],
        'rose' => ['card' => 'border-tint-rose-line bg-tint-rose-bg', 'icon' => 'bg-tint-rose-icon text-tint-rose-tx', 'tx' => 'text-tint-rose-tx'],
    ];
    $t = $tones[$tone] ?? $tones['blue'];
@endphp

<div {{ $attributes->class(['flex min-w-0 items-start gap-3 rounded-xl border p-4', $t['card']]) }}>
    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl {{ $t['icon'] }}" aria-hidden="true">
        @if ($glyph !== null)
            <span @class(['font-bold leading-none', 'text-xl' => mb_strlen($glyph) === 1, 'text-xs' => mb_strlen($glyph) > 1])>{{ $glyph }}</span>
        @else
            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="size-5">
                <path d="{{ NavIcons::path($icon) }}" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        @endif
    </span>

    {{--
        Metin sütunu bir container'dır: tutarın punto boyu KARTIN genişliğine
        göre seçilir (5 kart yan yana dururken rakam ortadan bölünmesin diye).
        Tutar ASLA kırpılmaz; gerekirse yalnızca simgeden önce satır kırılır.
    --}}
    <div class="@container min-w-0 flex-1">
        @if ($variant === 'plain')
            <p class="text-sm text-tx">{{ $label }}</p>
            <p class="mt-1 text-base font-bold tabular-nums text-tx @[9.75rem]:text-lg @[11.5rem]:text-xl">{{ $value }}</p>
        @else
            <p class="text-xs font-semibold uppercase tracking-wide {{ $t['tx'] }}">{{ $label }}</p>
            <p class="mt-1 text-base font-bold tabular-nums @[9.75rem]:text-lg @[11.5rem]:text-xl {{ $t['tx'] }}">{{ $value }}</p>
        @endif

        @if ($hint)
            <p class="mt-0.5 text-xs text-tx">{{ $hint }}</p>
        @endif

        {{ $slot }}
    </div>
</div>
