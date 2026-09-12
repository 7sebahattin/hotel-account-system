@php
    $entry = $row->entry;
    $type = (string) $entry->source_type;

    // Etiket, satırın türünden DEĞİL kaynağın türünden gelir: bir tahsilatın
    // ters kaydı da "Tahsilat kaydı"na bağlanır, "İptal kaydı"na değil.
    $source = [
        'stay' => ['route' => 'stays.show', 'label' => 'Konaklama kaydı'],
        'payment' => ['route' => 'payments.show', 'label' => 'Tahsilat kaydı'],
        'payment_allocation' => ['route' => 'payments.show', 'label' => 'Döviz tahsilatı'],
    ][$type] ?? null;

    // Ledger'ın doğruluğu kaynak kayda BAĞLI DEĞİLDİR: kayıt silinmiş olsa bile
    // ekstre satırı geçerlidir ve ekran hata vermez.
    $exists = $source !== null
        && $entry->source_id !== null
        && ($type === 'payment_allocation'
            ? isset($existingSources[$type][$entry->source_id])
            : in_array($entry->source_id, $existingSources[$type] ?? [], true));
    $targetId = $type === 'payment_allocation' ? ($existingSources[$type][$entry->source_id] ?? null) : $entry->source_id;
@endphp

@if ($exists)
    {{--
        Görsel metin küçük kalsın diye (dar "Kaynak" kolonu / mobil kart),
        `min-h-11` dokunma yüksekliğini padding tahmini olmadan garanti eder.
    --}}
    <a href="{{ route($source['route'], $targetId) }}"
       class="inline-flex min-h-11 items-center text-mut underline hover:text-acc">
        {{ $source['label'] }}
    </a>
@else
    <span class="text-mut">Kaynak kayıt bulunamadı</span>
@endif
