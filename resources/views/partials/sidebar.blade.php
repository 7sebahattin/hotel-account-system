@php
    use App\Modules\Authorization\Enums\Permission;
    use App\Modules\Authorization\Enums\RoleName;

    /*
        Menü yetkiye duyarlıdır: kullanıcının erişemediği bölüm hiç render edilmez
        (gizlenmez). Gruplar okunabilirlik için başlıklıdır; yalnızca içinde
        görünür öğe kalan grup çizilir.
    */
    $groups = [
        [
            'label' => null, // ana grup başlıksızdır
            'items' => [
                ['route' => 'home', 'label' => 'Ana Sayfa', 'can' => null, 'icon' => 'home'],
            ],
        ],
        [
            'label' => 'Kasa',
            'items' => [
                ['route' => 'cashbook.index', 'label' => 'Kasa Defteri', 'can' => Permission::CashBookView->value, 'icon' => 'safe'],
                ['route' => 'cashbook.movements.index', 'label' => 'Kasa Hareketleri', 'can' => Permission::CashBookView->value, 'icon' => 'cash'],
                ['route' => 'cashbook.transfers.index', 'label' => 'Devir Geçmişi', 'can' => Permission::CashBookView->value, 'icon' => 'swap'],
                ['route' => 'cashbook.day', 'label' => 'Günlük Defter', 'can' => Permission::CashBookViewAll->value, 'icon' => 'log'],
            ],
        ],
        [
            'label' => 'Cari',
            'items' => [
                ['route' => 'companies.index', 'label' => 'Firmalar', 'can' => Permission::CompaniesView->value, 'icon' => 'building'],
                ['route' => 'stays.index', 'label' => 'Konaklamalar', 'can' => Permission::StaysView->value, 'icon' => 'bed'],
                ['route' => 'payments.index', 'label' => 'Tahsilatlar', 'can' => Permission::PaymentsView->value, 'icon' => 'cash'],
                ['route' => 'statements.index', 'label' => 'Cari Ekstre', 'can' => Permission::LedgerViewAll->value, 'icon' => 'ledger'],
            ],
        ],
        [
            'label' => 'Yönetim',
            'items' => [
                ['route' => 'users.index', 'label' => 'Kullanıcılar', 'can' => Permission::UsersView->value, 'icon' => 'users'],
                ['route' => 'roles.index', 'label' => 'Roller / Yetkiler', 'can' => Permission::RolesManage->value, 'icon' => 'shield'],
                ['route' => 'rooms.index', 'label' => 'Odalar', 'can' => Permission::RoomsManage->value, 'icon' => 'bed'],
                ['route' => 'cashbook.categories.index', 'label' => 'Kasa Hareket Türleri', 'can' => Permission::CashBookManageCategories->value, 'icon' => 'log'],
                ['route' => 'audit.index', 'label' => 'Denetim Kayıtları', 'can' => Permission::AuditView->value, 'icon' => 'log'],
            ],
        ],
    ];

    $user = auth()->user();

    $visibleGroups = [];

    foreach ($groups as $group) {
        $items = array_values(array_filter(
            $group['items'],
            fn (array $item): bool => $item['can'] === null || $user->can($item['can']),
        ));

        if ($items !== []) {
            $visibleGroups[] = ['label' => $group['label'], 'items' => $items];
        }
    }

    $roleName = $user->getRoleNames()->first();
    $roleLabel = $roleName ? (RoleName::tryFrom($roleName)?->label() ?? $roleName) : 'Rol atanmamış';
    $initials = mb_strtoupper(mb_substr($user->name, 0, 1));
@endphp

<nav class="flex-1 overflow-y-auto px-3 py-4" aria-label="Ana menü">
    @foreach ($visibleGroups as $index => $group)
        @if ($group['label'] !== null)
            <p @class([
                'px-3 pb-1.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-nav-mut',
                'pt-5' => $index > 0,
            ])>{{ $group['label'] }}</p>
        @endif

        <ul @class(['space-y-0.5', 'mb-1' => $group['label'] === null])>
            @foreach ($group['items'] as $item)
                @include('partials.sidebar-link', ['item' => $item])
            @endforeach
        </ul>
    @endforeach
</nav>

<div class="border-t border-nav-line p-3">
    <div class="flex min-w-0 items-center gap-2.5 px-1 pb-2.5">
        <span aria-hidden="true"
              class="flex size-8 shrink-0 items-center justify-center rounded-full bg-nav-well text-xs font-semibold text-nav-tx">
            {{ $initials }}
        </span>

        <span class="min-w-0">
            <span class="block truncate text-sm text-nav-tx">{{ $user->name }}</span>
            <span class="block truncate text-xs text-nav-mut">{{ $roleLabel }}</span>
        </span>
    </div>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit"
                class="flex min-h-11 w-full items-center justify-center gap-2 rounded-lg border border-nav-line text-sm text-nav-mut transition-colors hover:bg-nav-well hover:text-nav-tx">
            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" class="size-4" aria-hidden="true">
                <path d="M12 14l4-4-4-4M16 10H7M8 3H5a1 1 0 00-1 1v12a1 1 0 001 1h3" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Çıkış
        </button>
    </form>
</div>
