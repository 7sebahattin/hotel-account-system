@props(['title', 'count' => null, 'subtitle' => null, 'titleHref' => null])

<div {{ $attributes->class('flex flex-wrap items-start justify-between gap-3') }}>
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

    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
