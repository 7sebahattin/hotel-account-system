@props([
    // [['href' => ..., 'label' => ..., 'target' => null], ...] — yetki süzmesi çağıranda yapılır.
    'items',
    'label',
])

{{--
    Satır işlem menüsü (⋮). Satırın kendisi ayrıntıya gider; menü yalnızca
    ek kısayolları taşır. Öğe yoksa hiç çizilmez. Native <details> — JS
    gerekmez; `data-popover` dışarı tıklama/Escape ile kapanmayı ekler.
--}}
@if ($items !== [])
    <details {{ $attributes->class('relative ml-auto') }} data-popover data-row-menu>
        <summary class="flex size-8 cursor-pointer list-none items-center justify-center rounded-lg text-mut hover:bg-well hover:text-tx"
                 aria-label="{{ $label }}">
            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.4" class="size-5" aria-hidden="true">
                <path d="{{ \App\Modules\Core\Support\NavIcons::path('dots-vertical') }}" stroke-linecap="round"/>
            </svg>
        </summary>
        <ul class="absolute right-0 z-20 mt-1 w-48 rounded-xl border border-line bg-card p-1">
            @foreach ($items as $item)
                <li>
                    <a href="{{ $item['href'] }}"
                       @if (! empty($item['target'])) target="{{ $item['target'] }}" rel="noopener" @endif
                       class="flex min-h-10 items-center rounded-lg px-3 text-sm text-tx hover:bg-well">{{ $item['label'] }}</a>
                </li>
            @endforeach
        </ul>
    </details>
@endif
