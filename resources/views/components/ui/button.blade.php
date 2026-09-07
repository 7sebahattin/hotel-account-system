@props([
    'variant' => 'primary',
    'href' => null,
    'type' => 'submit',
    'block' => false,
])

@php
    // Turuncu yalnızca ana aksiyonda; silme/iptal asla primary değildir.
    $styles = [
        'primary' => 'bg-acc font-bold text-[#111] hover:bg-orange-400',
        'secondary' => 'border border-field bg-well text-tx hover:border-mut',
        'danger' => 'border border-danger/50 bg-danger-bg text-danger hover:border-danger',
        'ghost' => 'text-mut hover:text-tx',
    ];

    $classes = implode(' ', array_filter([
        'inline-flex min-h-11 items-center justify-center gap-2 rounded-lg px-4 text-sm',
        'transition-colors disabled:cursor-not-allowed disabled:opacity-50',
        $styles[$variant] ?? $styles['primary'],
        $block ? 'w-full' : null,
    ]));
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>{{ $slot }}</button>
@endif
