@php
    use App\Modules\Authorization\Enums\Permission;
    use App\Modules\Core\Support\NavIcons;

    /* Yetkiye göre cari işlem kısayolları. */
    $user = auth()->user();

    $candidates = [
        ['route' => 'payments.create', 'params' => [], 'label' => 'Cari Tahsilat', 'icon' => 'cash', 'permission' => Permission::PaymentsCreate->value],
        ['route' => 'stays.create', 'params' => [], 'label' => 'Yeni Konaklama', 'icon' => 'bed', 'permission' => Permission::StaysCreate->value],
        ['route' => 'companies.create', 'params' => [], 'label' => 'Yeni Cari Hesap', 'icon' => 'building', 'permission' => Permission::CompaniesCreate->value],
    ];

    $items = array_values(array_filter($candidates, fn (array $item): bool => $user->can($item['permission'])));
@endphp

@if ($items !== [])
    <div data-quick-action class="relative shrink-0">
        <button type="button" data-quick-action-toggle
                class="flex size-11 items-center justify-center rounded-lg text-mut hover:bg-well hover:text-tx md:size-9 md:border md:border-field"
                aria-label="Hızlı işlem" aria-haspopup="true" aria-expanded="false" aria-controls="hizli-islem-menu">
            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" class="size-5" aria-hidden="true">
                <path d="{{ NavIcons::path('plus') }}" stroke-linecap="round"/>
            </svg>
        </button>

        <div id="hizli-islem-menu" data-quick-action-panel hidden
             class="absolute right-0 top-full z-30 mt-2 w-56 rounded-xl border border-line bg-card py-1.5 shadow-none ring-1 ring-line">
            <p class="px-3 pb-1.5 pt-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-mut">
                Hızlı işlem
            </p>

            <ul>
                @foreach ($items as $item)
                    <li>
                        <a href="{{ route($item['route'], $item['params']) }}"
                           class="flex min-h-11 items-center gap-2.5 px-3 text-sm text-tx hover:bg-well">
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5"
                                 class="size-4 shrink-0 text-mut" aria-hidden="true">
                                <path d="{{ NavIcons::path($item['icon']) }}" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            {{ $item['label'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
