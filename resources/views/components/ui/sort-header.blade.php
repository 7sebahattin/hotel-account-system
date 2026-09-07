@props(['filter', 'column', 'align' => 'left'])

@php
    $isActive = $filter->currentSort() === $column;
    $next = ($isActive && $filter->currentDirection() === 'asc') ? 'desc' : 'asc';
    $url = request()->fullUrlWithQuery(['sort' => $column, 'direction' => $next, 'page' => null]);
    $ariaSort = $isActive ? ($filter->currentDirection() === 'asc' ? 'ascending' : 'descending') : 'none';
@endphp

<th scope="col" aria-sort="{{ $ariaSort }}"
    @class([
        'px-4 py-3 text-xs font-normal uppercase tracking-wide text-mut',
        'text-left' => $align === 'left',
        'text-right' => $align === 'right',
    ])>
    <a href="{{ $url }}" @class(['hover:text-tx', 'text-tx' => $isActive])>
        {{ $slot }}
        <span aria-hidden="true">{{ $isActive ? ($filter->currentDirection() === 'asc' ? '↑' : '↓') : '' }}</span>
    </a>
</th>
