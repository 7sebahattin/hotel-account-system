@props(['tone' => 'info', 'title' => null])

@php
    // Durum kutuları yalnızca gerçekten durum/uyarı bildirirken kullanılır;
    // normal veri kartları renkli kutuya dönüştürülmez.
    $tones = [
        'success' => ['border' => 'border-l-success', 'text' => 'text-success', 'bg' => 'bg-success-bg'],
        'danger' => ['border' => 'border-l-danger', 'text' => 'text-danger', 'bg' => 'bg-danger-bg'],
        'info' => ['border' => 'border-l-field', 'text' => 'text-tx', 'bg' => 'bg-info-bg'],
    ];

    $role = $tone === 'danger' ? 'alert' : 'status';
    $tone = $tones[$tone] ?? $tones['info'];
@endphp

{{--
    Duyuru rolü tona göre seçilir: hata kesintiye uğratır (`alert`), başarı ve
    bilgi ekran okuyucuya sırası gelince okunur (`status`).
--}}
<div role="{{ $role }}" {{ $attributes->class([
    'rounded-md border-l-4 px-4 py-3 text-sm',
    $tone['bg'], $tone['border'], $tone['text'],
]) }}>
    @if ($title)
        <p class="font-medium">{{ $title }}</p>
    @endif

    {{ $slot }}
</div>
