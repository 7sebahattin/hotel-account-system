{{-- Cari ekstre satırı ⋮ menüsü: her öğe kendi yetkisine bağlıdır (sayfa zaten ledger.view_all ister). --}}
@php
    $items = [['href' => route('statements.show', $company), 'label' => 'Ekstreyi aç']];
    $items[] = ['href' => route('statements.print', $company), 'label' => 'Ekstreyi yazdır', 'target' => '_blank'];

    if (auth()->user()?->can('view', $company)) {
        $items[] = ['href' => route('companies.show', $company), 'label' => 'Cari hesap kartı'];
    }

    if (auth()->user()?->can('payments.create')) {
        $items[] = ['href' => route('payments.create', ['company_id' => $company->id]), 'label' => 'Tahsilat al'];
    }
@endphp

<x-ui.row-menu :items="$items" :label="$company->name.' için işlem menüsü'" data-statement-row-menu />
