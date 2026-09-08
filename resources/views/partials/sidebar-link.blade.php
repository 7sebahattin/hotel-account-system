@php
    use App\Modules\Core\Support\NavIcons;

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
            <path d="{{ NavIcons::path($item['icon']) }}" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>

        <span class="truncate">{{ $item['label'] }}</span>
    </a>
</li>
