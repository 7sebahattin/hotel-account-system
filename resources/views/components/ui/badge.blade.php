@props(['tone' => 'neutral'])

@php
    $tones = [
        'success' => 'bg-success-bg text-success',
        'danger' => 'bg-danger-bg text-danger',
        'accent' => 'bg-acc/15 text-acc',
        'neutral' => 'bg-well text-mut',
    ];
@endphp

<span {{ $attributes->class([
    'inline-flex shrink-0 items-center rounded-md px-2 py-0.5 text-xs',
    $tones[$tone] ?? $tones['neutral'],
]) }}>{{ $slot }}</span>
