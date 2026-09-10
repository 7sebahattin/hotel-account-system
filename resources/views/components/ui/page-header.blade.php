@props(['title', 'count' => null, 'subtitle' => null])

<div {{ $attributes->class('flex flex-wrap items-start justify-between gap-3') }}>
    <div class="min-w-0">
        <h1 class="text-[19px] font-medium text-tx">
            {{ $title }}
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
