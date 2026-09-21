@props(['compact' => false])

@php
    /*
        Marka alanı. Logo dosyası varsa kullanılır, yoksa monogram çizilir —
        böylece logo eklenene kadar ekran "kırık görsel" göstermez.

        Logo eklemek için: public/images/logo.svg (veya config('hotel.brand.logo')
        ile başka bir yol). Başka hiçbir yerde değişiklik gerekmez.
    */
    $logo = (string) config('hotel.brand.logo');
    $hasLogo = $logo !== '' && is_file(public_path($logo));
    $name = (string) config('hotel.brand.name');
    $tagline = (string) config('hotel.brand.tagline');
@endphp

<span class="flex min-w-0 items-center gap-2.5">
    @if ($hasLogo)
        <img src="{{ asset($logo) }}" alt="" aria-hidden="true"
             class="size-8 shrink-0 rounded-md object-contain">
    @else
        {{-- Monogram: markanın ilk harfi, altın zemin üzerinde koyu metin. --}}
        <span aria-hidden="true"
              class="flex size-8 shrink-0 items-center justify-center rounded-md bg-acc text-sm font-semibold text-on-acc">
            {{ mb_strtoupper(mb_substr($name, 0, 1)) }}
        </span>
    @endif

    <span class="min-w-0">
        <span class="block truncate text-sm font-semibold tracking-tight text-nav-tx">{{ $name }}</span>

        @unless ($compact)
            <span class="block truncate text-xs text-nav-mut">{{ $tagline }} · v{{ config('release.version') }}</span>
        @endunless
    </span>
</span>
