@extends('layouts.app')
@section('title', 'Cari Ekstre')

@php
    use App\Modules\Companies\Services\StatementListService as StatementList;
    use App\Modules\Core\Enums\Currency;

    /*
        Cari Ekstre firma listesi — 23 Eylül 2026 tasarımı (kullanıcının
        referans görseli). Durum/son hareket/sıralama mantığı
        StatementListService'tedir; hareketi olan hesaplar her zaman üstte.
        Bakiyeler para birimi bazındadır ve birleşmez (§6/L5).
    */
    $percent = static fn (int $part, int $whole): int => $whole > 0 ? intdiv($part * 100 + intdiv($whole, 2), $whole) : 0;
    // "toplam firmaların %31'i / %47'si / %0'ı" — ek sayının okunuşuna göre.
    $share = static function (int $part) use ($percent, $stats): string {
        $p = $percent($part, $stats['total']);

        return 'toplam firmaların %'.$p."'".\App\Modules\Core\Support\TurkishSuffix::possessive($p);
    };
    $debtorHint = $share($stats['debtors']);
    $closedHint = $share($stats['closed']);

    $quickFilters = [
        ['label' => 'Tümü', 'params' => ['durum' => null, 'son_hareket' => null, 'has_balance' => null],
            'active' => $statusFilter === null && $recency === null && ! $hasBalanceOnly],
        ['label' => 'Borçlu Firmalar', 'params' => ['durum' => StatementList::STATUS_DEBTOR], 'active' => $statusFilter === StatementList::STATUS_DEBTOR],
        ['label' => 'Hareket Yok', 'params' => ['durum' => StatementList::STATUS_NONE], 'active' => $statusFilter === StatementList::STATUS_NONE],
        ['label' => 'Kapalı Hesaplar', 'params' => ['durum' => StatementList::STATUS_CLOSED], 'active' => $statusFilter === StatementList::STATUS_CLOSED],
        ['label' => 'Son 30 Gün', 'params' => ['son_hareket' => 30], 'active' => $recency === 30],
        ['label' => 'Son 90 Gün', 'params' => ['son_hareket' => 90], 'active' => $recency === 90],
    ];

    // Sıralama: ad (SQL) veya bakiye/son hareket (servis). Hareketi olanlar her durumda üstte.
    $sortOptions = [
        'name:asc' => ['label' => 'Firma adına göre (A → Z)', 'params' => ['sort' => 'name', 'direction' => 'asc', 'balance_sort' => null]],
        'name:desc' => ['label' => 'Firma adına göre (Z → A)', 'params' => ['sort' => 'name', 'direction' => 'desc', 'balance_sort' => null]],
        'recent' => ['label' => 'Son harekete göre (Yeni → Eski)', 'params' => ['balance_sort' => 'recent']],
        'highest' => ['label' => 'Bakiyeye göre (Yüksek → Düşük)', 'params' => ['balance_sort' => 'highest'], 'needsCurrency' => true],
        'lowest' => ['label' => 'Bakiyeye göre (Düşük → Yüksek)', 'params' => ['balance_sort' => 'lowest'], 'needsCurrency' => true],
    ];
    $currentSortKey = $balanceSort ?? ('name:'.$filter->currentDirection());
    $currentSortLabel = $sortOptions[$currentSortKey]['label'] ?? $sortOptions['name:asc']['label'];

    $moreFilterCount = (int) request()->filled('account_type') + (int) request()->filled('is_active')
        + (int) $hasBalanceOnly + (int) request()->filled('per_page');
    $fieldClass = 'block min-h-11 w-full rounded-lg border border-field bg-card px-3 text-tx focus:border-acc-tx';
    $lastDate = static fn (?string $date): string => $date ? \Illuminate\Support\Carbon::parse($date)->format('d.m.Y') : '—';
@endphp

@section('content')
    <x-ui.page-header title="Cari Ekstre" icon="ledger" subtitle="Ekstresini görmek istediğiniz firmayı seçin.">
        <x-slot:actions>
            <x-ui.today />

            <x-ui.button variant="secondary"
                         :href="route('statements.print-index', request()->except(['page', 'per_page']))"
                         target="_blank" rel="noopener"><x-ui.icon name="printer" /> Yazdır</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- ===================================================== ÖZET KARTLARI --}}
    {{-- Arama/para birimi/hesap türü kapsamını özetler; durum hızlı filtreleri kartları değiştirmez. --}}
    <section class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4" data-statement-stats aria-label="Cari özet">
        <x-ui.kpi-card variant="plain" tone="purple" icon="building" label="Toplam firma"
                       :value="number_format($stats['total'], 0, ',', '.')" hint="kayıt bulundu" />

        <x-ui.kpi-card variant="plain" tone="amber" icon="wallet" label="Borçlu firmalar"
                       :value="number_format($stats['debtors'], 0, ',', '.')"
                       :hint="$debtorHint" />

        <x-ui.kpi-card variant="plain" tone="green" icon="check-circle" label="Kapalı hesaplar"
                       :value="number_format($stats['closed'], 0, ',', '.')"
                       :hint="$closedHint" />

        <x-ui.kpi-card variant="plain" tone="blue" icon="clock" label="Son hareket tarihi"
                       :value="$lastDate($stats['latest'])" hint="en son hareket" />
    </section>

    {{-- ========================================================= FİLTRELER --}}
    <section class="mt-4 rounded-xl border border-line bg-card p-4" data-statement-filters aria-label="Filtreler">
        <form method="GET" action="{{ route('statements.index') }}">
            @foreach (['sort', 'direction', 'balance_sort'] as $keep)
                @if (request()->filled($keep))
                    <input type="hidden" name="{{ $keep }}" value="{{ request($keep) }}">
                @endif
            @endforeach

            <div class="flex flex-wrap items-end gap-3">
                <div class="min-w-0 flex-[2_1_14rem]">
                    <label for="q" class="sr-only">Firma ara</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-mut"><x-ui.icon name="search" /></span>
                        <input id="q" name="q" type="search" value="{{ request('q') }}"
                               placeholder="Firma adı ile ara…" title="Ünvan, vergi no, telefon, e-posta, yetkili veya şehir"
                               class="{{ $fieldClass }} pl-9">
                    </div>
                </div>

                <div class="min-w-0 flex-[1_1_10rem] sm:w-44 sm:flex-none">
                    <x-ui.select label="Bakiye durumu" name="durum">
                        <option value="">Tümü</option>
                        @foreach (StatementList::STATUSES as $value => $label)
                            <option value="{{ $value }}" @selected($statusFilter === $value)>{{ $label }}</option>
                        @endforeach
                    </x-ui.select>
                </div>

                <div class="min-w-0 flex-[1_1_10rem] sm:w-44 sm:flex-none">
                    <x-ui.select label="Son hareket" name="son_hareket">
                        <option value="">Tümü</option>
                        @foreach (StatementList::RECENCY as $days => $label)
                            <option value="{{ $days }}" @selected($recency === $days)>{{ $label }}</option>
                        @endforeach
                    </x-ui.select>
                </div>

                <div class="min-w-0 flex-[1_1_8rem] sm:w-36 sm:flex-none">
                    <x-ui.select label="Para birimi" name="currency">
                        <option value="">Tümü</option>
                        {{-- Raporlama ekranı: her zaman Currency::cases() — geçmiş bakiye asla gizlenmez (§6). --}}
                        @foreach (Currency::cases() as $currency)
                            <option value="{{ $currency->value }}" @selected($balanceCurrency === $currency)>{{ $currency->value }}</option>
                        @endforeach
                    </x-ui.select>
                </div>

                <x-ui.button type="submit" class="w-full sm:w-auto"><x-ui.icon name="filter" /> Filtrele</x-ui.button>
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-2">
                <span class="text-sm font-medium text-tx">Hızlı filtreler:</span>

                @foreach ($quickFilters as $chip)
                    <a href="{{ request()->fullUrlWithQuery($chip['params'] + ['page' => null]) }}"
                       @if ($chip['active']) aria-current="true" @endif
                       @class([
                           'inline-flex min-h-11 items-center rounded-full border px-3 text-sm lg:min-h-8',
                           'border-acc bg-acc font-semibold text-on-acc' => $chip['active'],
                           'border-line bg-well text-tx hover:border-field' => ! $chip['active'],
                       ])>{{ $chip['label'] }}</a>
                @endforeach

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
                            <x-ui.select label="Hesap türü" name="account_type">
                                <option value="">Tümü</option>
                                <option value="company" @selected(request('account_type') === 'company')>Firma</option>
                                <option value="individual" @selected(request('account_type') === 'individual')>Bireysel</option>
                            </x-ui.select>

                            <x-ui.select label="Hesap durumu" name="is_active">
                                <option value="">Tümü</option>
                                <option value="1" @selected(request('is_active') === '1')>Aktif</option>
                                <option value="0" @selected(request('is_active') === '0')>Pasif</option>
                            </x-ui.select>

                            <x-ui.select label="Sayfa başına" name="per_page">
                                @foreach ($perPageOptions as $option)
                                    <option value="{{ $option }}" @selected((int) request('per_page', 25) === $option)>{{ $option }}</option>
                                @endforeach
                            </x-ui.select>

                            <x-ui.checkbox name="has_balance" value="1" :checked="$hasBalanceOnly"
                                           label="Yalnızca bakiyesi olan firmalar" />

                            <x-ui.button type="submit" variant="secondary">Uygula</x-ui.button>
                        </div>
                    </div>
                </details>

                @if ($filter->hasActiveFilters())
                    <a href="{{ route('statements.index') }}"
                       class="inline-flex min-h-11 items-center gap-1.5 text-sm text-acc-tx hover:text-tx lg:min-h-8">
                        <x-ui.icon name="refresh" /> Filtreleri temizle
                    </a>
                @endif
            </div>
        </form>
    </section>

    {{-- ========================================================== LİSTE --}}
    <section class="mt-4 rounded-xl border border-line bg-card" aria-labelledby="firma-listesi">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-4 py-2">
            <h2 id="firma-listesi" class="text-base font-semibold text-tx">
                Firma Listesi <span class="text-sm font-normal tabular-nums text-mut">({{ number_format($companies->total(), 0, ',', '.') }})</span>
            </h2>

            <details class="relative" data-popover>
                <summary class="flex min-h-11 cursor-pointer list-none items-center gap-2 rounded-lg border border-field px-3 text-sm text-tx hover:bg-well">
                    <x-ui.icon name="sort" /> <span>{{ $currentSortLabel }}</span> <x-ui.icon name="chevron-down" />
                </summary>
                <ul class="absolute right-0 z-20 mt-2 w-72 rounded-xl border border-line bg-card p-1" aria-label="Sıralama seçenekleri">
                    @foreach ($sortOptions as $key => $option)
                        <li>
                            @if (($option['needsCurrency'] ?? false) && $balanceCurrency === null)
                                <span class="flex min-h-11 flex-col justify-center rounded-lg px-3 text-sm text-mut">
                                    {{ $option['label'] }}
                                    <span class="text-xs">Önce bir para birimi seçin.</span>
                                </span>
                            @else
                                <a href="{{ request()->fullUrlWithQuery($option['params'] + ['page' => null]) }}"
                                   @if ($key === $currentSortKey) aria-current="true" @endif
                                   @class(['flex min-h-11 items-center rounded-lg px-3 text-sm text-tx hover:bg-well', 'bg-acc-bg font-semibold' => $key === $currentSortKey])>
                                    {{ $option['label'] }}
                                </a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </details>
        </div>

        {{-- Masaüstü tablo: satırın tamamı ekstreye gider (statements.js). --}}
        <div class="hidden lg:block">
            <table class="min-w-full text-sm">
                <thead class="border-b border-line">
                    <tr>
                        <x-ui.sort-header :filter="$filter" column="name">Firma</x-ui.sort-header>
                        <x-ui.th>Bakiye</x-ui.th>
                        <x-ui.th>Son hareket</x-ui.th>
                        <x-ui.th>Durum</x-ui.th>
                        <x-ui.th align="right">İşlem</x-ui.th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($companies as $company)
                        @php $companyBalances = $balances[$company->id] ?? []; @endphp
                        <tr class="statement-row border-b border-line last:border-0 hover:bg-well"
                            data-statement-row-url="{{ route('statements.show', $company) }}"
                            tabindex="0"
                            role="link"
                            aria-label="{{ $company->name }} cari ekstresini aç">
                            <td class="px-4 py-2.5 font-medium text-tx">{{ $company->name }}</td>
                            <td class="px-4 py-2.5">
                                @include('companies::statements._balances', ['companyBalances' => $companyBalances, 'companyFx' => $fxTotals[$company->id] ?? []])
                            </td>
                            <td class="px-4 py-2.5 whitespace-nowrap tabular-nums text-tx">{{ $lastDate($lastDates[$company->id] ?? null) }}</td>
                            <td class="px-4 py-2.5">@include('companies::statements._status', ['status' => $statuses[$company->id] ?? StatementList::STATUS_NONE])</td>
                            <td class="px-4 py-1 text-right">@include('companies::statements._row-menu', ['company' => $company])</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-sm text-mut">
                                @if ($filter->hasActiveFilters())
                                    Bu filtrelerle eşleşen firma bulunamadı.
                                    <a href="{{ route('statements.index') }}"
                                       class="ml-1 text-acc-tx underline underline-offset-2 hover:text-tx">Filtreleri temizle</a>
                                @else
                                    Firma bulunamadı.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Mobil: kartın tamamı ekstreye gider. --}}
        <div class="lg:hidden">
            @forelse ($companies as $company)
                @php $companyBalances = $balances[$company->id] ?? []; @endphp
                <a href="{{ route('statements.show', $company) }}"
                   @class(['block px-4 py-3 hover:bg-well', 'border-t border-line' => ! $loop->first])>
                    <span class="flex items-start justify-between gap-3">
                        <span class="min-w-0 truncate font-medium text-tx">{{ $company->name }}</span>
                        @include('companies::statements._status', ['status' => $statuses[$company->id] ?? StatementList::STATUS_NONE])
                    </span>
                    <span class="mt-1 block">
                        @include('companies::statements._balances', ['companyBalances' => $companyBalances, 'companyFx' => $fxTotals[$company->id] ?? []])
                    </span>
                    <span class="mt-1 block text-xs text-mut">
                        Son hareket: <span class="tabular-nums">{{ $lastDate($lastDates[$company->id] ?? null) }}</span>
                    </span>
                </a>
            @empty
                <x-ui.empty class="m-4" message="Firma bulunamadı." :filtered="$filter->hasActiveFilters()"
                            :clear-href="route('statements.index')" />
            @endforelse
        </div>

        @if ($companies->hasPages())
            <div class="border-t border-line px-4 py-3">{{ $companies->links() }}</div>
        @endif
    </section>
@endsection
