{{-- Konaklama satırı ⋮ menüsü: her öğe kendi yetkisine bağlıdır. --}}
@php
    $items = [];

    if (auth()->user()?->can('update', $stay)) {
        $items[] = ['href' => route('stays.edit', $stay), 'label' => 'Düzenle'];
    }

    if (! $stay->isCancelled() && auth()->user()?->can('payments.create')) {
        $items[] = ['href' => route('payments.create', ['company_id' => $stay->company_id]), 'label' => 'Tahsilat al'];
    }

    if (auth()->user()?->can('ledger.view')) {
        $items[] = ['href' => route('statements.show', $stay->company_id), 'label' => 'Cari ekstre'];
    }
@endphp

<x-ui.row-menu :items="$items" :label="$stay->guest_name.' için işlem menüsü'" data-stay-row-menu />
