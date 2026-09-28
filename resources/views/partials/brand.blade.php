@props(['compact' => false])

@php
    $name = (string) config('hotel.brand.name');
    $tagline = (string) config('hotel.brand.tagline');
@endphp

<span class="flex min-w-0 items-center gap-2.5">
    <x-brand-logo variant="on-dark" :decorative="true" class="size-8 shrink-0" />

    <span class="min-w-0">
        <span class="block truncate text-sm font-semibold tracking-tight text-nav-tx">{{ $name }}</span>

        @unless ($compact)
            <span class="block truncate text-xs text-nav-mut">{{ $tagline }} · v{{ config('release.version') }}</span>
        @endunless
    </span>
</span>
