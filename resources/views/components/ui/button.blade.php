@props([
    'variant' => 'primary',
    'href' => null,
    'type' => 'submit',
    'block' => false,
])

@php
    // Altın dolgu yalnızca ana aksiyonda; silme/iptal asla primary değildir.
    $styles = [
        'primary' => 'bg-acc font-semibold text-on-acc hover:bg-acc-strong',
        'secondary' => 'border border-field bg-card text-tx hover:bg-well',
        'danger' => 'border border-danger/40 bg-danger-bg text-danger hover:border-danger',
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
