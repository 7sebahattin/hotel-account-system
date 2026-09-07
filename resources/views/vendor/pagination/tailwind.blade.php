@if ($paginator->hasPages())
    @php
        $link = 'inline-flex min-h-11 items-center rounded-lg border px-3 text-sm';
        $idle = $link.' border-field bg-well text-tx hover:border-mut';
        $current = $link.' border-acc bg-acc/15 text-acc';
        $disabled = $link.' cursor-not-allowed border-line bg-well text-mut opacity-50';
    @endphp

    <nav role="navigation" aria-label="Sayfalama" class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-mut">
            <span class="tabular-nums">{{ $paginator->firstItem() }}</span>–<span class="tabular-nums">{{ $paginator->lastItem() }}</span>
            / toplam <span class="tabular-nums">{{ $paginator->total() }}</span> kayıt
        </p>

        <div class="flex flex-wrap items-center gap-1">
            @if ($paginator->onFirstPage())
                <span class="{{ $disabled }}" aria-disabled="true">Önceki</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $idle }}">Önceki</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="{{ $disabled }}" aria-hidden="true">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="{{ $current }} tabular-nums" aria-current="page">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="{{ $idle }} tabular-nums"
                               aria-label="Sayfa {{ $page }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $idle }}">Sonraki</a>
            @else
                <span class="{{ $disabled }}" aria-disabled="true">Sonraki</span>
            @endif
        </div>
    </nav>
@endif
