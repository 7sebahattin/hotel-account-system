{{--
    Çökertilebilir filtre paneli (kullanıcı talimatı v2.1.1-E).

    Tüm liste ekranlarının filtre formu artık VARSAYILAN OLARAK KAPALI bir
    <details> kabuğunun içindedir: kapalıyken tek satır (min-h-11) yer kaplar,
    açıldığında Gün 12'deki AYNI grid düzeni görünür (yeni bir "drawer"
    bileşeni AÇILMADI — CLAUDE.md §9'daki tek filtre UI deseni korunur, yalnız
    bir kabuğa alındı).

    Açık/kapalı kararı SUNUCUDA verilir: aktif filtre varsa panel `open`
    başlar (aksi hâlde kullanıcı neyin filtrelendiğini göremez ve "Temizle"ye
    ulaşamazdı). Native <details>/<summary>: Alpine.js kurulu DEĞİLDİR
    (CLAUDE.md §11.7); tıklama, klavye ve erişilebilirlik tarayıcının kendi
    davranışıdır — kenar çubuğu grupları (K-105) ile AYNI desen.
--}}
@props([
    'count' => 0,                              // aktif filtre sayısı (QueryFilter::activeFilterCount())
    'clearHref' => null,                       // "Temizle" bağlantısı
    'perPageOptions' => null,                  // verilirse panel altında sayfa boyutu seçici
    'open' => null,                            // null => aktif filtre varsa açık
    'label' => 'Filtreler',
    'submitLabel' => 'Filtrele',
    'grid' => 'sm:grid-cols-2 lg:grid-cols-4', // false/null => slot ham render edilir
])

@php
    $activeCount = (int) $count;
    $isOpen = $open ?? ($activeCount > 0);
    $currentPerPage = (int) request('per_page', 25);
@endphp

<details class="filter-panel mt-4 rounded-xl border border-line bg-card" @if ($isOpen) open @endif>
    <summary class="flex min-h-11 cursor-pointer list-none items-center gap-2 rounded-xl px-4 text-sm text-tx select-none hover:bg-well">
        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5"
             class="size-4 shrink-0 text-mut" aria-hidden="true">
            <path d="M3 5h14M6 10h8M9 15h2" stroke-linecap="round" />
        </svg>

        <span class="font-medium">{{ $label }}</span>

        @if ($activeCount > 0)
            <x-ui.badge tone="accent">{{ $activeCount }} aktif</x-ui.badge>
        @endif

        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5"
             class="filter-panel-chevron ml-auto size-4 shrink-0 text-mut" aria-hidden="true">
            <path d="M6 8l4 4 4-4" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
    </summary>

    <form method="GET" {{ $attributes->class('border-t border-line p-4') }}>
        {{-- Filtre dışı taşınması gereken query param'lar (ör. `trashed`). --}}
        {{ $hidden ?? '' }}

        @if ($grid)
            <div class="grid gap-3 {{ $grid }}">{{ $slot }}</div>
        @else
            {{ $slot }}
        @endif

        <div class="mt-4 flex flex-wrap items-end gap-3">
            <x-ui.button type="submit">{{ $submitLabel }}</x-ui.button>

            @if ($clearHref)
                <x-ui.button variant="ghost" :href="$clearHref">Temizle</x-ui.button>
            @endif

            @if ($perPageOptions)
                <label for="per_page" class="ml-auto flex items-center gap-2 text-sm text-mut">
                    Sayfa başına
                    <select id="per_page" name="per_page" onchange="this.form.submit()"
                            class="min-h-11 rounded-lg border border-field bg-well px-2 text-tx focus:border-acc">
                        @foreach ($perPageOptions as $option)
                            <option value="{{ $option }}" @selected($currentPerPage === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
        </div>
    </form>
</details>
