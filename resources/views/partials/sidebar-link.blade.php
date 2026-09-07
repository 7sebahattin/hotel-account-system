@php $active = request()->routeIs($item['route']); @endphp

<li>
    <a href="{{ route($item['route']) }}"
       @if ($active) aria-current="page" @endif
       @class([
           'flex min-h-11 items-center rounded-lg border-l-2 px-3 text-sm',
           'border-acc bg-well text-tx' => $active,
           'border-transparent text-mut hover:bg-well hover:text-tx' => ! $active,
       ])>
        {{ $item['label'] }}
    </a>
</li>
