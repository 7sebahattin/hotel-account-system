@props(['title', 'href' => null, 'linkLabel' => 'Tümü'])

{{-- Başlıklı liste kartı: pano bölümleri aynı iskeleti kullansın diye. --}}
<section {{ $attributes->class('flex min-w-0 flex-col rounded-xl border border-line bg-card') }}>
    <div class="flex items-center justify-between gap-3 border-b border-line px-4 py-3">
        <h2 class="text-sm font-semibold text-tx">{{ $title }}</h2>

        @if ($href)
            <a href="{{ $href }}" class="shrink-0 rounded text-xs text-acc-tx underline underline-offset-2 hover:text-tx">
                {{ $linkLabel }}
            </a>
        @endif
    </div>

    <div class="flex-1">{{ $slot }}</div>
</section>
