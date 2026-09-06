@php
    $links = [
        ['route' => 'home',         'label' => 'Ana sayfa', 'can' => null],
        ['route' => 'users.index',  'label' => 'Kullanıcılar', 'can' => \App\Modules\Authorization\Enums\Permission::UsersView->value],
        ['route' => 'roles.index',  'label' => 'Roller', 'can' => \App\Modules\Authorization\Enums\Permission::RolesManage->value],
        ['route' => 'audit.index',  'label' => 'Denetim kaydı', 'can' => \App\Modules\Authorization\Enums\Permission::AuditView->value],
    ];
@endphp

<nav class="flex flex-wrap items-center gap-1">
    @foreach ($links as $link)
        @if ($link['can'] === null || auth()->user()->can($link['can']))
            <a href="{{ route($link['route']) }}"
               @class([
                   'inline-flex min-h-11 items-center rounded-md px-3 text-sm font-medium',
                   'bg-slate-900 text-white' => request()->routeIs($link['route']),
                   'text-slate-700 hover:bg-slate-100' => ! request()->routeIs($link['route']),
               ])>
                {{ $link['label'] }}
            </a>
        @endif
    @endforeach
</nav>
