@props(['title', 'count' => null, 'subtitle' => null, 'titleHref' => null, 'icon' => null])

@php
    use App\Modules\Core\Support\NavIcons;
@endphp

<div {{ $attributes->class('flex flex-wrap items-start justify-between gap-3') }}>
    <div class="flex min-w-0 items-start gap-3">
        {{-- İsteğe bağlı sayfa ikonu (NavIcons anahtarı) — altın zeminli kutu. --}}
        @if ($icon)
            <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-acc-bg text-acc-tx" aria-hidden="true">
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="size-6">
                    <path d="{{ NavIcons::path($icon) }}" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </span>
        @endif

        <div class="min-w-0">
            <h1 class="text-[19px] font-medium text-tx">
                @if ($titleHref)
                    <a href="{{ $titleHref }}" class="rounded-sm hover:text-acc hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">{{ $title }}</a>
                @else
                    {{ $title }}
                @endif
                @unless (is_null($count))
                    <span class="ml-1 text-sm font-normal tabular-nums text-mut">({{ $count }})</span>
                @endunless
            </h1>

            @if ($subtitle)
                <p class="mt-1 text-sm text-mut">{{ $subtitle }}</p>
            @endif

            {{ $meta ?? '' }}
        </div>
    </div>

    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
