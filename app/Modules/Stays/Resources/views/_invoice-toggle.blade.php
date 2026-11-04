{{--
    Fatura kesildi / kesilmedi TAKİP işareti (yalnızca bir bayrak; e-Fatura değildir).

    Solda (kapalı) kırmızı, sağda (açık) yeşil geçiş anahtarı. Anlam yalnızca
    renge bağlanmaz: tik/çarpı ikonu ve metin etiketi her zaman yazılıdır
    (CLAUDE.md §11.6). Her geçişte onay penceresi çıkar. `$interactive` false ise
    (mobil kart bir bağlantı olduğu için) yalnızca durum etiketi gösterilir.
--}}
@php
    $invoiced = $stay->isInvoiced();
    $canToggle = ($interactive ?? false) && auth()->user()->can('toggleInvoice', $stay);
    $label = $invoiced ? 'Fatura kesildi' : 'Fatura kesilmedi';
    $confirmText = $invoiced
        ? 'Bu konaklama için "Fatura kesilmedi" olarak değiştirilsin mi?'
        : 'Bu konaklama için "Fatura kesildi" olarak işaretlensin mi?';
@endphp
@unless ($stay->isCancelled())
    <div class="mt-2 flex items-center gap-2 text-xs" data-invoice-status>
        @if ($canToggle)
            <form method="POST" action="{{ route('stays.invoice', $stay) }}" class="inline-flex"
                  onsubmit="return confirm({{ \Illuminate\Support\Js::from($confirmText) }})">
                @csrf @method('PATCH')
                <input type="hidden" name="invoiced" value="{{ $invoiced ? 0 : 1 }}">
                <button type="submit" role="switch" aria-checked="{{ $invoiced ? 'true' : 'false' }}"
                        aria-label="{{ $label }}. Değiştirmek için tıklayın"
                        title="{{ $label }} — değiştirmek için tıklayın"
                        @class([
                            'relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors',
                            'bg-success' => $invoiced,
                            'bg-danger' => ! $invoiced,
                        ])>
                    <span @class([
                        'inline-block size-5 rounded-full bg-card transition-transform',
                        'translate-x-5' => $invoiced,
                        'translate-x-0.5' => ! $invoiced,
                    ])></span>
                </button>
            </form>
        @endif

        <span @class([
            'inline-flex items-center gap-1 whitespace-nowrap font-medium',
            'text-success' => $invoiced,
            'text-danger' => ! $invoiced,
        ])>
            @if ($invoiced)
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" class="size-4" aria-hidden="true"><path d="M4 10.5l4 4 8-9" stroke-linecap="round" stroke-linejoin="round" /></svg>
            @else
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" class="size-4" aria-hidden="true"><path d="M5 5l10 10M15 5L5 15" stroke-linecap="round" /></svg>
            @endif
            {{ $label }}
        </span>
    </div>
@endunless
