@php
    $quickItems = app(\App\Modules\Users\Services\QuickMenuService::class)->visible(auth()->user());
@endphp

<div data-quick-action class="fixed bottom-6 right-6 z-30 hidden lg:block">
    <div id="hizli-islem-menu" data-quick-action-panel hidden
         class="absolute bottom-full right-0 mb-3 max-h-[calc(100dvh-7rem)] w-64 overflow-y-auto rounded-2xl border border-nav-line bg-nav p-2 text-nav-tx shadow-2xl">
        <div class="flex items-center justify-between gap-2 px-2 py-2">
            <p class="text-[11px] font-bold uppercase tracking-widest text-nav-mut">Hızlı İşlemler</p>
            <a href="{{ route('quick-menu.edit') }}" class="inline-flex min-h-11 items-center rounded-md px-2 text-xs text-nav-mut hover:bg-nav-well hover:text-nav-tx focus-visible:outline-2 focus-visible:outline-acc" aria-label="Hızlı menü ayarları">Ayarla</a>
        </div>
        @if ($quickItems === [])
            <p class="border-t border-nav-line px-2 py-3 text-sm text-nav-mut">Henüz etkin kısayol yok.</p>
        @else
            <ul class="border-t border-nav-line pt-1">
                @foreach ($quickItems as $item)
                    <li>
                        <a href="{{ route($item['route']) }}" class="flex min-h-11 items-center gap-3 rounded-lg px-2 text-sm text-nav-tx hover:bg-nav-well focus-visible:outline-2 focus-visible:outline-acc">
                            <x-ui.icon :name="$item['icon']" class="size-4 text-nav-mut" />
                            <span class="truncate">{{ $item['label'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
    <button type="button" data-quick-action-toggle
            class="ml-auto flex size-14 items-center justify-center rounded-full border border-acc-strong bg-acc text-on-acc shadow-lg transition-transform duration-150 hover:-translate-y-0.5 hover:bg-acc-strong active:translate-y-0 active:shadow-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-acc-tx motion-reduce:transition-none"
            aria-label="Hızlı işlemleri aç" aria-expanded="false" aria-controls="hizli-islem-menu">
        <x-ui.icon name="plus" class="size-6 transition-transform duration-150 motion-reduce:transition-none" />
    </button>
</div>
