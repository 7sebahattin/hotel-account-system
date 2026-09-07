@if ($paginator->hasPages())
    @php
        $link = 'inline-flex min-h-11 items-center rounded-lg border px-3 text-sm';
        $idle = $link.' border-field bg-well text-tx hover:border-mut';
        $disabled = $link.' cursor-not-allowed border-line bg-well text-mut opacity-50';
    @endphp

    <nav role="navigation" aria-label="Sayfalama" class="flex items-center gap-1">
        @if ($paginator->onFirstPage())
            <span class="{{ $disabled }}" aria-disabled="true">Önceki</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $idle }}">Önceki</a>
        @endif

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $idle }}">Sonraki</a>
        @else
            <span class="{{ $disabled }}" aria-disabled="true">Sonraki</span>
        @endif
    </nav>
@endif
