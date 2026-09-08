@php
    /*
        Aktif öğe ÜÇ işaretle belirtilir: sol altın şerit, açık zemin ve
        `aria-current`. Yalnızca renge güvenilmez (CLAUDE.md §11.6).

        `routeIs` desen alır: 'companies.index' yerine 'companies.*' verilirse
        alt ekranlarda da aktif kalır. Ekstre ekranları Companies modülünde
        olduğu için Firmalar ile Cari Ekstre ayrı desenler kullanır.
    */
    $patterns = [
        'home' => ['home'],
        'companies.index' => ['companies.*'],
        'stays.index' => ['stays.*'],
        'payments.index' => ['payments.*'],
        'statements.index' => ['statements.*'],
        'users.index' => ['users.*'],
        'roles.index' => ['roles.*'],
        'audit.index' => ['audit.*'],
    ];

    $active = request()->routeIs(...($patterns[$item['route']] ?? [$item['route']]));

    $icons = [
        'home' => 'M3 9l7-6 7 6v8a1 1 0 01-1 1h-4v-5H8v5H4a1 1 0 01-1-1V9z',
        'building' => 'M4 17V4a1 1 0 011-1h7a1 1 0 011 1v13M4 17h13M4 17H2m11 0h5V9a1 1 0 00-1-1h-4M6 6h2M6 9h2M6 12h2',
        'bed' => 'M3 16v-3h14v3M3 13V7m0 6h14M17 13v-2a2 2 0 00-2-2H9v4M6 9.5a1.5 1.5 0 100-3 1.5 1.5 0 000 3z',
        'cash' => 'M2 6h16v8H2V6zm8 5.5a1.5 1.5 0 100-3 1.5 1.5 0 000 3zM5 6v8m10-8v8',
        'ledger' => 'M5 3h9a1 1 0 011 1v13a1 1 0 01-1 1H5a1 1 0 01-1-1V4a1 1 0 011-1zm0 0v14M8 7h5M8 10h5M8 13h3',
        'users' => 'M13 16v-1a3 3 0 00-3-3H6a3 3 0 00-3 3v1M8 9a3 3 0 100-6 3 3 0 000 6zm9 7v-1a3 3 0 00-2-2.8M13 3.2a3 3 0 010 5.6',
        'shield' => 'M10 2l6 2.5V9c0 4-2.6 7.2-6 9-3.4-1.8-6-5-6-9V4.5L10 2z',
        'log' => 'M4 4h12v12H4V4zm3 3h6M7 10h6M7 13h4',
    ];
@endphp

<li>
    <a href="{{ route($item['route']) }}"
       @if ($active) aria-current="page" @endif
       @class([
           'group flex min-h-11 items-center gap-2.5 rounded-lg border-l-2 pl-2.5 pr-3 text-sm transition-colors',
           'border-acc bg-nav-well font-medium text-nav-tx' => $active,
           'border-transparent text-nav-mut hover:bg-nav-well/60 hover:text-nav-tx' => ! $active,
       ])>
        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5"
             @class(['size-[18px] shrink-0', 'text-acc' => $active])
             aria-hidden="true">
            <path d="{{ $icons[$item['icon']] ?? $icons['home'] }}" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>

        <span class="truncate">{{ $item['label'] }}</span>
    </a>
</li>
