@props([
    // [['href' => ..., 'label' => ..., 'active' => bool], ...]
    'items',
    'label',
    'variant' => 'underline',
])

{{--
    Bağlantı tabanlı sekmeler: her sekme ayrı bir adrestir (yer imi ve geri
    tuşu doğal çalışır), JS yoktur. Etkin sekme `aria-current="page"` taşır.
    `underline` kart içi sekme, `pill` küçük seçici (para birimi, dönem) içindir.
--}}
<nav aria-label="{{ $label }}" {{ $attributes->class('min-w-0') }}>
    <ul @class([
        'flex gap-1 overflow-x-auto',
        'border-b border-line' => $variant === 'underline',
        'rounded-lg border border-line bg-well p-0.5' => $variant === 'pill',
    ])>
        @foreach ($items as $item)
            <li class="shrink-0">
                <a href="{{ $item['href'] }}"
                   @if ($item['active']) aria-current="page" @endif
                   @class([
                       'inline-flex min-h-11 items-center whitespace-nowrap text-sm',
                       '-mb-px border-b-2 px-3' => $variant === 'underline',
                       'border-acc font-semibold text-tx' => $variant === 'underline' && $item['active'],
                       'border-transparent text-mut hover:text-tx' => $variant === 'underline' && ! $item['active'],
                       'rounded-md px-3' => $variant === 'pill',
                       'border border-line bg-card font-semibold text-tx' => $variant === 'pill' && $item['active'],
                       'text-tx hover:bg-card' => $variant === 'pill' && ! $item['active'],
                   ])>{{ $item['label'] }}</a>
            </li>
        @endforeach
    </ul>
</nav>
