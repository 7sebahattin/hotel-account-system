@extends('layouts.app')
@section('title', 'Pano — '.config('app.name'))

@php
    use App\Modules\Core\Enums\Currency;
    use App\Modules\Core\Support\NavIcons;
    use App\Modules\Dashboard\Support\FinancialSummary;

    /** @var FinancialSummary $summary */
    $selectedCurrency = $summary->selectedCurrency;
    $selected = $summary->selected();

    // Pano görünüm tercihleri query string'dedir (yer imi/geri tuşu doğal çalışır).
    $params = array_filter([
        'currency' => $selectedCurrency?->value,
        'months' => $summary->chartMonths,
        'tab' => $summary->tab,
    ]);
    $url = static fn (array $overrides = [], ?string $fragment = null): string => route(
        'home',
        array_filter(array_merge($params, $overrides), static fn ($value): bool => $value !== null),
    ).($fragment !== null ? '#'.$fragment : '');

    // Cari özet satırı ve son güncellemeler için ton → sınıf eşlemesi (tam
    // sınıf adları: Tailwind yalnızca kaynakta gördüğünü üretir).
    $iconTones = [
        'amber' => 'bg-tint-amber-icon text-tint-amber-tx',
        'green' => 'bg-tint-green-icon text-tint-green-tx',
        'blue' => 'bg-tint-blue-icon text-tint-blue-tx',
        'purple' => 'bg-tint-purple-icon text-tint-purple-tx',
        'rose' => 'bg-tint-rose-icon text-tint-rose-tx',
    ];
@endphp

@section('content')
    <x-ui.page-header
        title="Pano"
        :subtitle="$today->translatedFormat('d F Y').' · Finans'">
        @if (count($summary->currencies) > 1)
            <x-slot:actions>
                <x-ui.tabs label="Para birimi" variant="pill" :items="array_map(fn ($totals) => [
                    'href' => $url(['currency' => $totals->currency->value, 'sayfa' => null]),
                    'label' => $totals->currency->value,
                    'active' => $totals->currency === $selectedCurrency,
                ], $summary->currencies)" />
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    @if ($summary->isEmpty() || $selected === null)
        {{-- Boş kurulum: kullanıcı ne yapacağını buradan öğrenir. --}}
        <x-ui.card class="mt-6 p-10 text-center">
            <h2 class="text-base font-semibold text-tx">Sistem henüz boş</h2>

            <p class="mx-auto mt-2 max-w-md text-sm text-mut">
                Cari takibe başlamak için önce bir firma oluşturun. Firma kaydedildikten sonra
                konaklama ve tahsilat girdikçe bakiyeler burada görünür.
            </p>

            <div class="mt-5 flex flex-wrap justify-center gap-2">
                @can('companies.create')
                    <x-ui.button :href="route('companies.create')">Firma ekle</x-ui.button>
                @endcan

                <x-ui.button variant="secondary" :href="route('companies.index')">Firmalar</x-ui.button>
            </div>
        </x-ui.card>
    @else
        {{-- ============================================================ KPI KARTLARI --}}
        <section class="mt-5" aria-labelledby="cari-durum-baslik">
            <h2 id="cari-durum-baslik" class="sr-only">Cari durum · {{ $selectedCurrency->value }}</h2>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                <x-ui.kpi-card label="Açık alacak" icon="wallet" tone="amber"
                               :value="$selected->receivable->format($selectedCurrency)"
                               :hint="$selected->receivable->isZero() ? 'Bekleyen tahsilat yok' : 'Hesaplardan tahsilat bekleniyor'" />

                <x-ui.kpi-card label="Açık borç" icon="arrow-down" tone="green"
                               :value="$selected->payable->format($selectedCurrency)"
                               :hint="$selected->payable->isZero() ? 'Hesaplara borcumuz yok' : 'Hesaplara borcumuz var'" />

                <x-ui.kpi-card label="Net bakiye" icon="calculator" tone="blue"
                               :value="$selected->net->format($selectedCurrency)"
                               :hint="$selected->net->isNegative() ? 'Net borçluyuz' : 'Net alacaklıyız'">
                    @if ($selectedCurrency !== Currency::TRY)
                        @if ($selected->netTl !== null)
                            <x-ui.fx-note :equivalent="$selected->netTl" />
                        @else
                            <p class="mt-0.5 text-xs text-tx">TL karşılığı eksik veri nedeniyle tam değil</p>
                        @endif
                    @endif
                </x-ui.kpi-card>

                <x-ui.kpi-card label="Bu ay konaklama" icon="bed" tone="purple"
                               :value="$selected->staysThisMonth->format($selectedCurrency)"
                               :hint="$selected->staysCountThisMonth.' konaklama · '.$selected->nightsThisMonth.' gece'" />

                <x-ui.kpi-card label="Bu ay tahsilat" icon="card" tone="rose"
                               :value="$selected->paymentsThisMonth->format($selectedCurrency)"
                               :hint="$selected->paymentsCountThisMonth.' tahsilat'" />
            </div>
        </section>

        <div class="mt-4 grid items-start gap-4 xl:grid-cols-[minmax(0,1fr)_20rem]">
            <div class="min-w-0 space-y-4">
                {{-- ======================================================== GRAFİK --}}
                <x-ui.card data-dashboard-chart>
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-base font-semibold text-tx">Son {{ $summary->chartMonths }} Ay Cari Hareketler</h2>
                            <p class="mt-0.5 text-xs text-mut">{{ $selectedCurrency->value }} · tüm hesaplar · konaklama çıkış tarihine, tahsilat ödeme tarihine göre</p>
                        </div>

                        <x-ui.tabs label="Grafik dönemi" variant="pill" :items="array_map(fn (int $months) => [
                            'href' => $url(['months' => $months]),
                            'label' => 'Son '.$months.' ay',
                            'active' => $months === $summary->chartMonths,
                        ], FinancialSummary::CHART_MONTHS)" />
                    </div>

                    <div class="mt-4">
                        @include('dashboard::_monthly-chart', ['chart' => $summary->chart])
                    </div>
                </x-ui.card>

                {{-- ======================================== PARA BİRİMİ BAZINDA DURUM --}}
                <x-ui.card :padded="false" data-currency-overview>
                    <div class="border-b border-line px-4 py-3">
                        <h2 class="text-base font-semibold text-tx">Para Birimi Bazında Durum</h2>
                        <p class="mt-0.5 text-xs text-mut">
                            Para birimleri ayrı hesaplanır ve <strong class="font-medium text-tx">birbirine çevrilmez</strong>.
                            Bir satırı seçmek panoyu o para birimine geçirir.
                        </p>
                    </div>

                    <div class="hidden lg:block">
                        <table class="min-w-full text-sm">
                            <thead class="bg-well">
                                <tr>
                                    <th scope="col" class="px-3 py-2 text-left text-xs font-normal uppercase tracking-wide text-tx">Para birimi</th>
                                    <th scope="col" class="whitespace-nowrap px-3 py-2 text-right text-xs font-normal uppercase tracking-wide text-tx">Açık alacak</th>
                                    <th scope="col" class="whitespace-nowrap px-3 py-2 text-right text-xs font-normal uppercase tracking-wide text-tx">Açık borç</th>
                                    <th scope="col" class="whitespace-nowrap px-3 py-2 text-right text-xs font-normal uppercase tracking-wide text-tx">Net bakiye</th>
                                    <th scope="col" class="whitespace-nowrap px-3 py-2 text-right text-xs font-normal uppercase tracking-wide text-tx">Bu ay konaklama</th>
                                    <th scope="col" class="whitespace-nowrap px-3 py-2 text-right text-xs font-normal uppercase tracking-wide text-tx">Bu ay tahsilat</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($summary->currencies as $totals)
                                    @php $isSelected = $totals->currency === $selectedCurrency; @endphp
                                    <tr @class(['border-t border-line', 'bg-acc-bg' => $isSelected])>
                                        <th scope="row" class="px-3 py-2 text-left font-normal">
                                            <a href="{{ $url(['currency' => $totals->currency->value, 'sayfa' => null]) }}"
                                               @if ($isSelected) aria-current="true" @endif
                                               class="flex min-h-11 flex-col justify-center text-tx hover:text-acc-tx">
                                                <span @class(['font-semibold' => $isSelected, 'font-medium' => ! $isSelected])>{{ $totals->currency->value }}</span>
                                                <span class="whitespace-nowrap text-xs text-tx">{{ $totals->currency->label() }}</span>
                                            </a>
                                        </th>
                                        <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums text-debit">{{ $totals->receivable->format($totals->currency) }}</td>
                                        <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums text-credit">{{ $totals->payable->format($totals->currency) }}</td>
                                        <td class="whitespace-nowrap px-3 py-2 text-right font-semibold tabular-nums text-tx">
                                            {{ $totals->net->format($totals->currency) }}
                                            @if ($totals->currency !== Currency::TRY && $totals->netTl !== null)
                                                <x-ui.fx-note :equivalent="$totals->netTl" class="text-right font-normal" />
                                            @endif
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums text-tx">{{ $totals->staysThisMonth->format($totals->currency) }}</td>
                                        <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums text-tx">{{ $totals->paymentsThisMonth->format($totals->currency) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <ul class="lg:hidden">
                        @foreach ($summary->currencies as $totals)
                            @php $isSelected = $totals->currency === $selectedCurrency; @endphp
                            <li @class(['border-t border-line' => ! $loop->first])>
                                <a href="{{ $url(['currency' => $totals->currency->value, 'sayfa' => null]) }}"
                                   @if ($isSelected) aria-current="true" @endif
                                   @class(['block min-h-11 px-4 py-3 hover:bg-well', 'bg-acc-bg' => $isSelected])>
                                    <span class="flex items-center gap-2 text-sm text-tx">
                                        <span @class(['font-semibold' => $isSelected])>{{ $totals->currency->label() }}</span>
                                        <x-ui.badge tone="accent">{{ $totals->currency->value }}</x-ui.badge>
                                    </span>
                                    <dl class="mt-2 grid grid-cols-2 gap-x-4 gap-y-1 text-xs">
                                        <dt class="text-tx">Açık alacak</dt>
                                        <dd class="text-right tabular-nums text-debit">{{ $totals->receivable->format($totals->currency) }}</dd>
                                        <dt class="text-tx">Açık borç</dt>
                                        <dd class="text-right tabular-nums text-credit">{{ $totals->payable->format($totals->currency) }}</dd>
                                        <dt class="text-tx">Net bakiye</dt>
                                        <dd class="text-right font-semibold tabular-nums text-tx">{{ $totals->net->format($totals->currency) }}</dd>
                                        <dt class="text-tx">Bu ay konaklama</dt>
                                        <dd class="text-right tabular-nums text-tx">{{ $totals->staysThisMonth->format($totals->currency) }}</dd>
                                        <dt class="text-tx">Bu ay tahsilat</dt>
                                        <dd class="text-right tabular-nums text-tx">{{ $totals->paymentsThisMonth->format($totals->currency) }}</dd>
                                    </dl>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>

                {{-- ============================================= SEKMELİ ALT TABLO --}}
                <x-ui.card :padded="false" class="scroll-mt-4" id="hareketler" data-dashboard-tabs>
                    <div class="flex flex-wrap items-end justify-between gap-x-3 px-4 pt-2">
                        <x-ui.tabs label="Pano tablosu" class="-mb-px" :items="collect(FinancialSummary::TABS)->map(fn (string $label, string $tab) => [
                            'href' => $url(['tab' => $tab, 'sayfa' => null], 'hareketler'),
                            'label' => $label,
                            'active' => $tab === $summary->tab,
                        ])->values()->all()" />

                        <a href="{{ route('statements.index') }}"
                           class="mb-1 inline-flex min-h-11 items-center gap-1 rounded-lg px-2 text-sm text-acc-tx underline underline-offset-2 hover:text-tx">
                            Tüm cari ekstreyi gör
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="size-4" aria-hidden="true">
                                <path d="{{ NavIcons::path('chevron-right') }}" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </a>
                    </div>

                    <div class="border-t border-line">
                        @if ($summary->tab === 'hareketler')
                            @include('dashboard::_tab-ledger')
                        @elseif ($summary->tab === 'konaklamalar')
                            @include('dashboard::_tab-stays')
                        @elseif ($summary->tab === 'tahsilatlar')
                            @include('dashboard::_tab-payments')
                        @else
                            @include('dashboard::_tab-balances')
                        @endif
                    </div>
                </x-ui.card>
            </div>

            <aside class="min-w-0 space-y-4" aria-label="Özetler">
                {{-- ===================================================== CARİ ÖZETİ --}}
                <x-ui.panel title="Cari Özeti" data-account-overview>
                    @php
                        $overview = [
                            ['icon' => 'building', 'tone' => 'blue', 'label' => 'Aktif hesap', 'value' => (string) $summary->activeCompanyCount, 'hint' => 'Pasif: '.$summary->passiveCompanyCount],
                            ['icon' => 'bed', 'tone' => 'purple', 'label' => 'Toplam konaklama', 'value' => number_format($summary->totalStayCount, 0, ',', '.'), 'hint' => number_format($summary->totalNightCount, 0, ',', '.').' gece · tüm para birimleri'],
                            ['icon' => 'arrow-down', 'tone' => 'green', 'label' => 'Toplam tahsilat', 'value' => $summary->lifetimePayments->format($selectedCurrency), 'hint' => $summary->lifetimePaymentCount.' tahsilat · '.$selectedCurrency->value],
                            ['icon' => 'calculator', 'tone' => 'amber', 'label' => 'Ortalama gecelik tutar', 'value' => $summary->averageNightly?->format($selectedCurrency) ?? '—', 'hint' => 'Aktif konaklamalar · '.$selectedCurrency->value],
                        ];
                    @endphp

                    <ul>
                        @foreach ($overview as $row)
                            <li @class(['flex min-h-14 items-center gap-3 px-4 py-2.5', 'border-t border-line' => ! $loop->first])>
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-lg {{ $iconTones[$row['tone']] }}" aria-hidden="true">
                                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="size-5">
                                        <path d="{{ NavIcons::path($row['icon']) }}" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm text-tx">{{ $row['label'] }}</span>
                                    <span class="block text-xs text-mut">{{ $row['hint'] }}</span>
                                </span>
                                <span class="shrink-0 whitespace-nowrap text-right text-sm font-semibold tabular-nums text-tx">{{ $row['value'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.panel>

                {{-- ======================================= EN YÜKSEK BAKİYELİ FİRMALAR --}}
                <x-ui.panel title="En yüksek bakiyeli firmalar" :href="route('statements.index')" link-label="Tümünü gör">
                    @php $topRows = $summary->topCompanies[$selectedCurrency->value] ?? []; @endphp

                    @if ($topRows === [])
                        <x-ui.empty class="m-4 border-0 bg-transparent py-8">
                            {{ $selectedCurrency->value }} için bize borçlu hesap yok.
                        </x-ui.empty>
                    @else
                        {{--
                            Satırın TAMAMI (tutar dahil) tek bağlantıdır (CLAUDE.md §11.5).
                            İlk sıra altın zeminle vurgulanır; sıra numarası metin
                            olarak da yazılıdır — renk tek başına anlam taşımaz.
                        --}}
                        <ol class="p-2">
                            @foreach ($topRows as $row)
                                <li>
                                    <a href="{{ route('companies.show', $row->companyId) }}"
                                       @class([
                                           'flex min-h-11 items-center gap-3 rounded-lg px-2 py-1.5 hover:bg-well',
                                           'bg-acc-bg' => $loop->first,
                                       ])>
                                        <span @class([
                                            'flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold tabular-nums',
                                            'bg-acc text-on-acc' => $loop->first,
                                            'bg-well text-tx' => ! $loop->first,
                                        ])>{{ $loop->iteration }}</span>
                                        <span @class(['min-w-0 flex-1 truncate text-sm text-tx', 'font-semibold' => $loop->first])>{{ $row->companyName }}</span>
                                        <span class="shrink-0 text-right">
                                            <span class="block text-sm font-medium tabular-nums text-debit">{{ $row->balance->format($row->currency) }}</span>
                                            @if ($row->balanceTl !== null)
                                                <x-ui.fx-note :equivalent="$row->balanceTl" class="text-right" />
                                            @endif
                                        </span>
                                    </a>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </x-ui.panel>

                {{-- ================================================ SON GÜNCELLEMELER --}}
                @if ($summary->activity !== null)
                    <x-ui.panel title="Son Güncellemeler" data-recent-activity>
                        @forelse ($summary->activity as $item)
                            <div @class(['flex items-start gap-3 px-4 py-3', 'border-t border-line' => ! $loop->first])>
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-full {{ $iconTones[$item->tone] ?? $iconTones['blue'] }}" aria-hidden="true">
                                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="size-5">
                                        <path d="{{ NavIcons::path($item->icon) }}" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </span>
                                <div class="min-w-0">
                                    <p class="text-sm text-tx">{{ $item->title }}</p>
                                    <p class="mt-0.5 text-xs text-mut">
                                        @if ($item->at)
                                            <span class="tabular-nums">{{ $item->at->format('d.m.Y H:i') }}</span>
                                        @endif
                                        @if ($item->userName)
                                            <span aria-hidden="true">·</span> {{ $item->userName }}
                                        @endif
                                    </p>
                                </div>
                            </div>
                        @empty
                            <x-ui.empty class="m-4 border-0 bg-transparent py-8">Henüz güncelleme yok.</x-ui.empty>
                        @endforelse

                        <div class="border-t border-line px-4 py-1">
                            <a href="{{ route('audit.index') }}"
                               class="inline-flex min-h-11 items-center gap-1 text-sm text-acc-tx underline underline-offset-2 hover:text-tx">
                                Tüm hareketleri gör
                                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="size-4" aria-hidden="true">
                                    <path d="{{ NavIcons::path('chevron-right') }}" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </a>
                        </div>
                    </x-ui.panel>
                @endif
            </aside>
        </div>
    @endif
@endsection
