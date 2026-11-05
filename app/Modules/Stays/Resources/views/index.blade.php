@extends('layouts.app')
@section('title', 'Konaklamalar')

@php
    use App\Modules\Core\Support\TurkishDate;
    use App\Modules\Stays\Filters\StayFilter;

    /*
        Konaklama listesi — 23 Eylül 2026 tasarımı (kullanıcının referans
        görseli). Filtreleme mantığı StayFilter'dadır; bu ekran yalnızca
        gösterir. Toplamlar para birimi bazındadır ve birleşmez (§6/L5).
    */
    $totalCount = $totals->sum('count');
    $totalNights = $totals->sum('nights');
    // Ortalama gece: tek ondalık, tamsayı aritmetiğiyle ("1,3").
    $averageNights = $totalCount > 0 ? intdiv($totalNights * 10 + intdiv($totalCount, 2), $totalCount) : null;
    $currencyTones = ['green', 'blue', 'rose', 'purple', 'amber'];
    // Kartlar enum sırasıyla (TRY önce) dizilir; toplamlar yine ayrı kalır.
    $currencyOrder = array_flip(\App\Modules\Core\Enums\Currency::values());
    $orderedTotals = $totals->sortBy(fn (array $t): int => $currencyOrder[$t['currency']->value] ?? PHP_INT_MAX);

    $quick = $filter->quickFilter();
    // "Daha fazla filtre" kapalı başlar (açık panel listeyi örterdi); seçili değer sayısı etikette görünür.
    $moreFilterCount = (int) request()->filled('currency') + (int) request()->filled('per_page');

    $sortOptions = [
        'check_in_date:desc' => 'Giriş tarihine göre (Yeni → Eski)',
        'check_in_date:asc' => 'Giriş tarihine göre (Eski → Yeni)',
        'check_out_date:desc' => 'Çıkış tarihine göre (Yeni → Eski)',
        'check_out_date:asc' => 'Çıkış tarihine göre (Eski → Yeni)',
        'amount:desc' => 'Tutara göre (Yüksek → Düşük)',
        'amount:asc' => 'Tutara göre (Düşük → Yüksek)',
        'guest_name:asc' => 'Misafir adına göre (A → Z)',
        'created_at:desc' => 'Eklenme zamanına göre (Yeni → Eski)',
    ];
    $currentSortKey = $filter->currentSort().':'.$filter->currentDirection();
    $currentSortLabel = $sortOptions[$currentSortKey]
        ?? 'Sıralama: '.$filter->currentSort().' ('.($filter->currentDirection() === 'asc' ? 'artan' : 'azalan').')';

    $fieldClass = 'block min-h-11 w-full rounded-lg border border-field bg-card px-3 text-tx focus:border-acc-tx';
@endphp

@section('content')
    <x-ui.page-header title="Konaklamalar" icon="bed"
                      :subtitle="'Toplam '.number_format($stays->total(), 0, ',', '.').' konaklama kaydı'">
        <x-slot:actions>
            <x-ui.today data-stay-clock />

            <x-ui.button variant="secondary" :href="route('stays.print', request()->query())">
                <x-ui.icon name="printer" /> Yazdır
            </x-ui.button>

            @can('create', \App\Modules\Stays\Models\Stay::class)
                <x-ui.button :href="route('stays.create')"><x-ui.icon name="plus" /> Yeni konaklama</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    {{-- ===================================================== ÖZET KARTLARI --}}
    <section class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4" data-stay-totals aria-label="Filtreye göre özet">
        <x-ui.kpi-card variant="plain" tone="purple" icon="bed" label="Toplam konaklama"
                       :value="number_format($stays->total(), 0, ',', '.')" hint="kayıt bulundu" />

        @foreach ($orderedTotals as $code => $t)
            <x-ui.kpi-card variant="plain" :tone="$currencyTones[$loop->index % count($currencyTones)]"
                           :glyph="$t['currency']->symbol()" :label="$code.' toplamı'"
                           :value="$t['total']->format($t['currency'])"
                           :hint="number_format($t['count'], 0, ',', '.').' konaklama'">
                @if ($t['cancelledCount'] > 0)
                    <p class="mt-0.5 text-xs text-tx">
                        Aktif: {{ $t['activeTotal']->format($t['currency']) }}
                        · İptal ({{ $t['cancelledCount'] }}): {{ $t['cancelledTotal']->format($t['currency']) }}
                    </p>
                @endif
            </x-ui.kpi-card>
        @endforeach

        <x-ui.kpi-card variant="plain" tone="amber" icon="moon" label="Ortalama gece"
                       :value="$averageNights === null ? '—' : intdiv($averageNights, 10).','.($averageNights % 10)"
                       hint="gece / konaklama" />
    </section>

    {{-- ========================================================= FİLTRELER --}}
    <section class="mt-4 rounded-xl border border-line bg-card p-4" data-stay-filters aria-label="Filtreler">
        <form method="GET" action="{{ route('stays.index') }}">
            {{-- Filtre dışı durum: sıralama ve hızlı filtre gönderimde korunur. --}}
            @foreach (['sort', 'direction', 'hizli'] as $keep)
                @if (request()->filled($keep))
                    <input type="hidden" name="{{ $keep }}" value="{{ request($keep) }}">
                @endif
            @endforeach

            {{-- Esnek satır: geniş ekranda tek sıra, daraldıkça alt satıra kayar. --}}
            <div class="flex flex-wrap items-end gap-3">
                <div class="min-w-0 flex-[2_1_14rem]">
                    <label for="q" class="sr-only">Ara</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-mut"><x-ui.icon name="search" /></span>
                        <input id="q" name="q" type="search" value="{{ request('q') }}"
                               placeholder="Misafir adı, cari hesap veya oda ile ara…"
                               class="{{ $fieldClass }} pl-9">
                    </div>
                </div>

                <fieldset class="min-w-0 flex-[1_1_18rem] sm:min-w-[20rem]">
                    <legend class="sr-only">Giriş tarihi aralığı</legend>
                    <div class="flex items-center gap-2">
                        <label for="date_from" class="sr-only">Giriş tarihi başlangıç</label>
                        <input id="date_from" name="date_from" type="date" value="{{ request('date_from') }}"
                               title="Giriş tarihi (başlangıç)" class="{{ $fieldClass }} min-w-0 flex-1 sm:min-w-[9rem]">
                        <span class="shrink-0 text-mut"><x-ui.icon name="arrow-right" /></span>
                        <label for="date_to" class="sr-only">Giriş tarihi bitiş</label>
                        <input id="date_to" name="date_to" type="date" value="{{ request('date_to') }}"
                               title="Giriş tarihi (bitiş)" class="{{ $fieldClass }} min-w-0 flex-1 sm:min-w-[9rem]">
                    </div>
                </fieldset>

                <div class="min-w-0 flex-[1_1_10rem] sm:w-44 sm:flex-none">
                    <x-ui.select label="Cari hesap" name="company_id">
                        <option value="">Tümü</option>
                        @foreach ($companies as $id => $name)
                            <option value="{{ $id }}" @selected((string) request('company_id') === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </x-ui.select>
                </div>

                <div class="min-w-0 flex-[1_1_8rem] sm:w-36 sm:flex-none">
                    <x-ui.select label="Durum" name="status">
                        <option value="">Tümü</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </x-ui.select>
                </div>

                <x-ui.button type="submit" class="w-full sm:w-auto"><x-ui.icon name="filter" /> Filtrele</x-ui.button>
            </div>

            {{-- Hızlı filtreler: diğer filtrelerle birlikte uygulanan bağlantılar. --}}
            <div class="mt-4 flex flex-wrap items-center gap-2">
                <span class="text-sm font-medium text-tx">Hızlı filtreler:</span>

                @foreach (['' => 'Tümü'] + StayFilter::QUICK_FILTERS as $key => $label)
                    @php $active = ($quick ?? '') === $key; @endphp
                    <a href="{{ request()->fullUrlWithQuery(['hizli' => $key === '' ? null : $key, 'page' => null]) }}"
                       @if ($active) aria-current="true" @endif
                       @class([
                           'inline-flex min-h-11 items-center rounded-full border px-3 text-sm lg:min-h-8',
                           'border-acc bg-acc font-semibold text-on-acc' => $active,
                           'border-line bg-well text-tx hover:border-field' => ! $active,
                       ])>{{ $label }}</a>
                @endforeach

                {{-- Seyrek kullanılan filtreler: native <details> açılır panel (JS'siz). --}}
                <details class="relative ml-auto" data-popover data-more-filters>
                    <summary class="flex min-h-11 cursor-pointer list-none items-center gap-1 rounded-lg px-2 text-sm text-acc-tx hover:text-tx">
                        Daha fazla filtre
                        @if ($moreFilterCount > 0)
                            <x-ui.badge tone="accent">{{ $moreFilterCount }}</x-ui.badge>
                        @endif
                        <x-ui.icon name="chevron-down" />
                    </summary>
                    <div class="absolute right-0 z-20 mt-2 w-72 max-w-[calc(100vw-2rem)] rounded-xl border border-line bg-card p-4">
                        <div class="grid gap-3">
                            <x-ui.select label="Para birimi" name="currency">
                                <option value="">Tümü</option>
                                @foreach ($currencies as $currency)
                                    <option value="{{ $currency->value }}" @selected(request('currency') === $currency->value)>{{ $currency->value }}</option>
                                @endforeach
                            </x-ui.select>

                            <x-ui.select label="Sayfa başına" name="per_page">
                                @foreach ($perPageOptions as $option)
                                    <option value="{{ $option }}" @selected((int) request('per_page', 25) === $option)>{{ $option }}</option>
                                @endforeach
                            </x-ui.select>

                            <x-ui.button type="submit" variant="secondary">Uygula</x-ui.button>
                        </div>
                    </div>
                </details>

                @if ($filter->hasActiveFilters())
                    <a href="{{ route('stays.index') }}"
                       class="inline-flex min-h-11 items-center gap-1.5 text-sm text-acc-tx hover:text-tx lg:min-h-8">
                        <x-ui.icon name="refresh" /> Filtreleri temizle
                    </a>
                @endif
            </div>
        </form>
    </section>

    {{-- ========================================================== LİSTE --}}
    <section class="mt-4 rounded-xl border border-line bg-card" aria-labelledby="konaklama-listesi">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-4 py-2">
            <h2 id="konaklama-listesi" class="text-base font-semibold text-tx">Konaklama Listesi</h2>

            <details class="relative" data-popover>
                <summary class="flex min-h-11 cursor-pointer list-none items-center gap-2 rounded-lg border border-field px-3 text-sm text-tx hover:bg-well">
                    <x-ui.icon name="sort" /> <span>{{ $currentSortLabel }}</span> <x-ui.icon name="chevron-down" />
                </summary>
                <ul class="absolute right-0 z-20 mt-2 w-72 rounded-xl border border-line bg-card p-1" aria-label="Sıralama seçenekleri">
                    @foreach ($sortOptions as $key => $label)
                        @php [$sortColumn, $sortDirection] = explode(':', $key); @endphp
                        <li>
                            <a href="{{ request()->fullUrlWithQuery(['sort' => $sortColumn, 'direction' => $sortDirection, 'page' => null]) }}"
                               @if ($key === $currentSortKey) aria-current="true" @endif
                               @class(['flex min-h-11 items-center rounded-lg px-3 text-sm hover:bg-well', 'bg-acc-bg font-semibold text-tx' => $key === $currentSortKey, 'text-tx' => $key !== $currentSortKey])>
                                {{ $label }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </details>
        </div>

        {{--
            Masaüstü tablo: satırın tamamı ayrıntıya gider (stays.js).
            9 kolon + ödeme durumu 1024 px'te yatay kaydırma gerektirirdi
            (§11.5 "ana tablolarda yatay scroll yok"); bu yüzden tablo xl
            (1280 px) ve üstündedir, altında kart listesi gösterilir.
        --}}
        <div class="hidden xl:block">
            <table class="min-w-full text-sm">
                <thead class="border-b border-line">
                    <tr>
                        <x-ui.sort-header :filter="$filter" column="check_in_date">Giriş</x-ui.sort-header>
                        <x-ui.sort-header :filter="$filter" column="check_out_date">Çıkış</x-ui.sort-header>
                        <x-ui.sort-header :filter="$filter" column="nights" align="right">Gece</x-ui.sort-header>
                        <x-ui.sort-header :filter="$filter" column="guest_name">Misafir</x-ui.sort-header>
                        <x-ui.th>Oda</x-ui.th>
                        <x-ui.th>Cari hesap</x-ui.th>
                        <x-ui.sort-header :filter="$filter" column="amount" align="right">Tutar</x-ui.sort-header>
                        <x-ui.th>Durum</x-ui.th>
                        <x-ui.th>Oluşturan / İşlem</x-ui.th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($stays as $stay)
                        <tr class="stay-row border-b border-line last:border-0"
                            data-stay-row-url="{{ route('stays.show', $stay) }}"
                            tabindex="0"
                            role="link"
                            aria-label="{{ $stay->guest_name }} konaklamasını görüntüle">
                            <td class="px-3 py-3 whitespace-nowrap">
                                <span class="block font-medium tabular-nums text-tx">{{ $stay->check_in_date->format('d.m.Y') }}</span>
                                <span class="block text-xs text-mut">{{ TurkishDate::shortWeekday($stay->check_in_date) }}</span>
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap">
                                <span class="block font-medium tabular-nums text-tx">{{ $stay->check_out_date->format('d.m.Y') }}</span>
                                <span class="block text-xs text-mut">{{ TurkishDate::shortWeekday($stay->check_out_date) }}</span>
                            </td>
                            <td class="px-3 py-3 text-right tabular-nums text-tx">{{ $stay->nights }}</td>
                            <td class="px-3 py-3 font-medium text-tx">{{ $stay->guest_name }}</td>
                            <td class="px-3 py-3">
                                <span class="block tabular-nums text-tx">{{ $stay->roomLabel() ?? '—' }}</span>
                                @if ($stay->room?->room_name || $stay->room?->room_type)
                                    <span class="block text-xs text-mut">{{ $stay->room->room_name ?: $stay->room->room_type }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-tx">{{ $stay->company?->name ?? '—' }}</td>
                            <td class="px-3 py-3 text-right whitespace-nowrap font-semibold tabular-nums text-tx">
                                {{ $stay->amountMoney()->format($stay->currency) }}
                                <x-ui.fx-note :rate="$stay->exchange_rate_to_try" :equivalent="$stay->tryEquivalentMoney()" class="max-w-32 ml-auto whitespace-normal text-right font-normal" />
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap">@include('stays::_payment-status')@include('stays::_invoice-toggle', ['interactive' => true])</td>
                            <td class="px-3 py-3">
                                <div class="flex items-center gap-2">
                                    @include('stays::_creator', ['stay' => $stay])
                                    @include('stays::_row-menu', ['stay' => $stay])
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-10 text-center text-sm text-mut">
                                @if ($filter->hasActiveFilters())
                                    Bu filtrelerle eşleşen kayıt bulunamadı.
                                    <a href="{{ route('stays.index') }}"
                                       class="ml-1 text-acc-tx underline underline-offset-2 hover:text-tx">Filtreleri temizle</a>
                                @else
                                    Kayıt bulunamadı.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Mobil/tablet: kartın tamamı ayrıntıya gider. --}}
        <div class="xl:hidden">
            @forelse ($stays as $stay)
                <a href="{{ route('stays.show', $stay) }}"
                   @class(['block px-4 py-3 hover:bg-well', 'border-t border-line' => ! $loop->first])>
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate font-medium text-tx">{{ $stay->guest_name }}</p>
                            <p class="truncate text-sm text-mut">{{ $stay->company?->name ?? '—' }}</p>
                        </div>
                        <span class="shrink-0 text-right">
                            <span class="block font-semibold tabular-nums text-tx">{{ $stay->amountMoney()->format($stay->currency) }}</span>
                            <x-ui.fx-note :rate="$stay->exchange_rate_to_try" :equivalent="$stay->tryEquivalentMoney()" class="text-right" />
                        </span>
                    </div>

                    <div class="mt-2 flex items-end justify-between gap-3">
                        <p class="text-sm text-tx">
                            <span class="tabular-nums">{{ $stay->check_in_date->format('d.m.Y') }}</span>
                            <span class="text-mut">{{ TurkishDate::shortWeekday($stay->check_in_date) }}</span>
                            → <span class="tabular-nums">{{ $stay->check_out_date->format('d.m.Y') }}</span>
                            <span class="text-mut">{{ TurkishDate::shortWeekday($stay->check_out_date) }}</span>
                            <br>
                            <span class="text-mut"><span class="tabular-nums">{{ $stay->nights }}</span> gece
                                @if ($stay->roomLabel()) · Oda <span class="tabular-nums">{{ $stay->roomLabel() }}</span> @endif</span>
                        </p>

                        <span class="shrink-0 text-right">@include('stays::_payment-status')@include('stays::_invoice-toggle')</span>
                    </div>
                </a>
            @empty
                <x-ui.empty class="m-4" :filtered="$filter->hasActiveFilters()" :clear-href="route('stays.index')" />
            @endforelse
        </div>

        @if ($stays->hasPages())
            <div class="border-t border-line px-4 py-3">{{ $stays->links() }}</div>
        @endif
    </section>
@endsection
