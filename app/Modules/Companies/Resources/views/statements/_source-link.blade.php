@php
    $entry = $row->entry;
    $type = (string) $entry->source_type;

    // Etiket, satırın türünden DEĞİL kaynağın türünden gelir: bir tahsilatın
    // ters kaydı da "Tahsilat kaydı"na bağlanır, "İptal kaydı"na değil.
    $source = [
        'stay' => ['route' => 'stays.show', 'label' => 'Konaklama kaydı'],
        'payment' => ['route' => 'payments.show', 'label' => 'Tahsilat kaydı'],
    ][$type] ?? null;

    // Ledger'ın doğruluğu kaynak kayda BAĞLI DEĞİLDİR: kayıt silinmiş olsa bile
    // ekstre satırı geçerlidir ve ekran hata vermez.
    $exists = $source !== null
        && $entry->source_id !== null
        && in_array($entry->source_id, $existingSources[$type] ?? [], true);
@endphp

@if ($exists)
    <a href="{{ route($source['route'], $entry->source_id) }}" class="text-mut underline hover:text-acc">
        {{ $source['label'] }}
    </a>
@else
    <span class="text-mut">Kaynak kayıt bulunamadı</span>
@endif
