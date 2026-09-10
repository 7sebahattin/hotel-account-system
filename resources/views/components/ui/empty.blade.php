@props(['message' => 'Kayıt bulunamadı.', 'filtered' => false, 'clearHref' => null])

@php
    // Aktif filtre varken "kayıt yok" ile "bu filtrelerle eşleşen kayıt yok"
    // ayrımı kullanıcıya doğru sonraki adımı gösterir (kullanıcı talimatı
    // Gün 12 §16): birincisinde yapacak bir şey yoktur, ikincisinde filtreyi
    // temizlemek yeterlidir.
    $text = $filtered ? 'Bu filtrelerle eşleşen kayıt bulunamadı.' : $message;
@endphp

<div {{ $attributes->class('rounded-xl border border-dashed border-field bg-card px-6 py-10 text-center text-sm text-mut') }}>
    <p>{{ $slot->isEmpty() ? $text : $slot }}</p>

    @if ($filtered && $clearHref)
        <a href="{{ $clearHref }}"
           class="mt-3 inline-flex min-h-11 items-center rounded-lg border border-field px-4 text-sm text-tx hover:bg-well">
            Filtreleri temizle
        </a>
    @endif
</div>
