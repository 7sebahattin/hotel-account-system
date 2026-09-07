@props(['tone' => 'info', 'title' => null])

@php
    // Durum kutuları yalnızca gerçekten durum/uyarı bildirirken kullanılır;
    // normal veri kartları renkli kutuya dönüştürülmez.
    $tones = [
        'success' => ['border' => 'border-l-success', 'text' => 'text-success', 'bg' => 'bg-success-bg'],
        'danger' => ['border' => 'border-l-danger', 'text' => 'text-danger', 'bg' => 'bg-danger-bg'],
        'info' => ['border' => 'border-l-line', 'text' => 'text-tx', 'bg' => 'bg-well'],
    ];

    $tone = $tones[$tone] ?? $tones['info'];
@endphp

<div role="alert" {{ $attributes->class([
    'rounded-md border-l-4 px-4 py-3 text-sm',
    $tone['bg'], $tone['border'], $tone['text'],
]) }}>
    @if ($title)
        <p class="font-medium">{{ $title }}</p>
    @endif

    {{ $slot }}
</div>
