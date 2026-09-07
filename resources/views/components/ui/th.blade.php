@props(['align' => 'left'])

<th scope="col" {{ $attributes->class([
    'px-4 py-3 text-xs font-normal uppercase tracking-wide text-mut',
    'text-left' => $align === 'left',
    'text-right' => $align === 'right',
]) }}>{{ $slot }}</th>
