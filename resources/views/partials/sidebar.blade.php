@php
    use App\Modules\Authorization\Enums\Permission;
    use App\Modules\Authorization\Enums\RoleName;

    // Menü yetkiye duyarlıdır: kullanıcının erişemediği modül hiç render edilmez.
    $primary = [
        ['route' => 'home', 'label' => 'Ana Sayfa', 'can' => null],
        ['route' => 'companies.index', 'label' => 'Firmalar', 'can' => Permission::CompaniesView->value],
        ['route' => 'stays.index', 'label' => 'Konaklamalar', 'can' => Permission::StaysView->value],
    ];

    $secondary = [
        ['route' => 'users.index', 'label' => 'Kullanıcılar', 'can' => Permission::UsersView->value],
        ['route' => 'roles.index', 'label' => 'Roller / Yetkiler', 'can' => Permission::RolesManage->value],
        ['route' => 'audit.index', 'label' => 'Denetim Kayıtları', 'can' => Permission::AuditView->value],
    ];

    $visible = fn (array $items) => array_values(array_filter(
        $items,
        fn ($item) => $item['can'] === null || auth()->user()->can($item['can']),
    ));

    $roleName = auth()->user()->getRoleNames()->first();
    $roleLabel = $roleName ? (RoleName::tryFrom($roleName)?->label() ?? $roleName) : 'Rol atanmamış';
@endphp

<nav class="flex-1 overflow-y-auto px-3 py-4" aria-label="Ana menü">
    <ul class="space-y-1">
        @foreach ($visible($primary) as $item)
            @include('partials.sidebar-link', ['item' => $item])
        @endforeach
    </ul>

    @if ($visible($secondary) !== [])
        <hr class="my-4 border-line">

        <p class="px-3 pb-1 text-xs uppercase tracking-wide text-mut">Yönetim</p>

        <ul class="space-y-1">
            @foreach ($visible($secondary) as $item)
                @include('partials.sidebar-link', ['item' => $item])
            @endforeach
        </ul>
    @endif
</nav>

<div class="border-t border-line p-3">
    <p class="truncate text-sm text-tx">{{ auth()->user()->name }}</p>
    <p class="truncate text-xs text-mut">{{ $roleLabel }}</p>

    <form method="POST" action="{{ route('logout') }}" class="mt-2">
        @csrf
        <x-ui.button type="submit" variant="secondary" block>Çıkış</x-ui.button>
    </form>
</div>
