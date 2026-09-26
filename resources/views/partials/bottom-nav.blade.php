@php
    use App\Modules\Authorization\Enums\Permission;
    use App\Modules\Core\Support\NavIcons;

    /* Mobil birincil gezinme. */
    $primaryItems = [
        ['route' => 'home', 'label' => 'Ana Sayfa', 'can' => null, 'icon' => 'home'],
        ['route' => 'stays.index', 'label' => 'Konaklamalar', 'can' => Permission::StaysView->value, 'icon' => 'bed'],
        ['route' => 'companies.index', 'label' => 'Cari hesaplar', 'can' => Permission::CompaniesView->value, 'icon' => 'building'],
        ['route' => 'payments.index', 'label' => 'Tahsilatlar', 'can' => Permission::PaymentsView->value, 'icon' => 'cash'],
    ];

    $user = auth()->user();

    $visiblePrimary = array_values(array_filter(
        $primaryItems,
        fn (array $item): bool => $item['can'] === null || $user->can($item['can']),
    ));

    $patterns = [
        'home' => ['home'],
        'stays.index' => ['stays.*'],
        'companies.index' => ['companies.*'],
        'payments.index' => ['payments.*'],
    ];

    $moreActive = request()->routeIs('statements.*', 'users.*', 'roles.*', 'audit.*', 'personnel.*');

    $columnCount = count($visiblePrimary) + 1;
@endphp

<nav data-bottom-nav
     class="fixed inset-x-0 bottom-0 z-20 border-t border-line bg-card md:hidden"
     aria-label="Alt menü">
    <ul class="grid" style="grid-template-columns: repeat({{ $columnCount }}, minmax(0, 1fr));">
        @foreach ($visiblePrimary as $item)
            @php $active = request()->routeIs(...($patterns[$item['route']] ?? [$item['route']])); @endphp
            <li class="min-w-0">
                <a href="{{ route($item['route']) }}"
                   @if ($active) aria-current="page" @endif
                   class="flex min-h-11 flex-col items-center justify-center gap-0.5 px-1 py-2 text-mut">
                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5"
                         @class(['size-5 shrink-0', 'text-acc-tx' => $active])
                         aria-hidden="true">
                        <path d="{{ NavIcons::path($item['icon']) }}" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span @class(['truncate text-[11px] leading-none', 'font-medium text-tx' => $active])>
                        {{ $item['label'] }}
                    </span>
                </a>
            </li>
        @endforeach

        <li class="min-w-0">
            <button type="button" data-sidebar-toggle
                    @if ($moreActive) aria-current="page" @endif
                    aria-expanded="false" aria-controls="kenar-cubugu"
                    class="flex min-h-11 w-full flex-col items-center justify-center gap-0.5 px-1 py-2 text-mut">
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5"
                     @class(['size-5 shrink-0', 'text-acc-tx' => $moreActive])
                     aria-hidden="true">
                    <path d="{{ NavIcons::path('more') }}" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span @class(['truncate text-[11px] leading-none', 'font-medium text-tx' => $moreActive])>
                    Daha Fazla
                </span>
            </button>
        </li>
    </ul>

    {{-- iPhone alt çentiği: gezinme çubuğunun kendisi güvenli alana kadar uzanır. --}}
    <div style="height: env(safe-area-inset-bottom);" aria-hidden="true"></div>
</nav>
