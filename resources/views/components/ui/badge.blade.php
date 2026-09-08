@props(['tone' => 'neutral'])

@php
    $tones = [
        'success' => 'bg-success-bg text-success ring-success/20',
        'danger' => 'bg-danger-bg text-danger ring-danger/20',
        'accent' => 'bg-acc-bg text-acc-tx ring-acc/30',
        'debit' => 'bg-acc-bg text-debit ring-debit/20',
        'credit' => 'bg-success-bg text-credit ring-credit/20',
        'neutral' => 'bg-well text-tx ring-line',
    ];
@endphp

<span {{ $attributes->class([
    'inline-flex shrink-0 items-center rounded-md px-2 py-0.5 text-xs ring-1 ring-inset',
    $tones[$tone] ?? $tones['neutral'],
]) }}>{{ $slot }}</span>
