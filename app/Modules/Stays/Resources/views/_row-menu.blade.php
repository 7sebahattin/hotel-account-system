{{--
    Satır işlem menüsü (⋮). Ayrıntıya gitmek için satırın kendisi tıklanır;
    menü yalnızca ek kısayolları taşır ve her öğe kendi yetkisine bağlıdır.
    Hiç öğe yoksa menü çizilmez. Native <details> — JS gerektirmez.
--}}
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

@if ($items !== [])
    <details class="relative ml-auto" data-popover data-stay-row-menu>
        <summary class="flex size-8 cursor-pointer list-none items-center justify-center rounded-lg text-mut hover:bg-well hover:text-tx"
                 aria-label="{{ $stay->guest_name }} için işlem menüsü">
            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.4" class="size-5" aria-hidden="true">
                <path d="{{ \App\Modules\Core\Support\NavIcons::path('dots-vertical') }}" stroke-linecap="round"/>
            </svg>
        </summary>
        <ul class="absolute right-0 z-20 mt-1 w-44 rounded-xl border border-line bg-card p-1">
            @foreach ($items as $item)
                <li>
                    <a href="{{ $item['href'] }}" class="flex min-h-10 items-center rounded-lg px-3 text-sm text-tx hover:bg-well">{{ $item['label'] }}</a>
                </li>
            @endforeach
        </ul>
    </details>
@endif
