@php
    use App\Modules\Core\Support\NavIcons;

    /* Aktiflik çağıran kenar çubuğundan gelir. */
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
