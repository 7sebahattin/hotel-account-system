@extends('layouts.app')
@section('title', 'Cari Grafik')

@section('content')
    <x-ui.page-header title="Cari Grafik"
                      subtitle="Borcu bulunan carilerin para birimi bazında görsel özeti.">
        <x-slot:actions>
            <x-ui.button variant="secondary" :href="route('statements.index')">Cari Ekstre</x-ui.button>
            <x-ui.button variant="secondary"
                         :href="route('statements.graphics-print', request()->only(['q', 'currency']))"
                         target="_blank" rel="noopener">Yazdır</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.filter-panel :count="$activeFilterCount"
                       :clear-href="route('statements.graphics')"
                       submit-label="Uygula">
        <div class="sm:col-span-2">
            <x-ui.input label="Cari ara" name="q" type="search" :value="request('q')"
                        hint="Ünvan, vergi no, telefon, e-posta, yetkili veya şehir." />
        </div>

        <x-ui.select label="Para birimi" name="currency">
            <option value="">Tümü</option>
            @foreach (\App\Modules\Core\Enums\Currency::cases() as $currency)
                <option value="{{ $currency->value }}" @selected($selectedCurrency === $currency)>
                    {{ $currency->value }}
                </option>
            @endforeach
        </x-ui.select>
    </x-ui.filter-panel>

    @if ($uniqueDebtorCount === 0)
        <x-ui.empty class="mt-4"
                    message="Bu filtrelerle borçlu cari bulunamadı."
                    :filtered="$activeFilterCount > 0"
                    :clear-href="route('statements.graphics')">
            Bu filtrelerle borçlu cari bulunamadı.
        </x-ui.empty>
    @else
        <section class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Borç özeti">
            <x-ui.card class="sm:col-span-2 xl:col-span-1">
                <p class="text-xs font-medium uppercase tracking-wide text-mut">Borçlu Cari</p>
                <p class="mt-2 text-3xl font-semibold tabular-nums text-tx"
                   data-unique-debtor-count="{{ $uniqueDebtorCount }}">{{ $uniqueDebtorCount }}</p>
                <p class="mt-1 text-xs text-mut">En az bir para biriminde pozitif bakiyesi olan tekil cari.</p>
            </x-ui.card>

            @foreach ($currencyGroups as $group)
                <x-ui.card data-currency-summary="{{ $group['currency']->value }}">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-mut">{{ $group['currency']->value }}</p>
                            <p class="mt-1 text-sm text-mut">{{ $group['companyCount'] }} borçlu cari</p>
                        </div>
                        <span class="rounded-lg bg-acc-bg px-2 py-1 text-xs font-semibold text-acc-tx">
                            {{ $group['currency']->label() }}
                        </span>
                    </div>
                    <p class="mt-3 whitespace-nowrap text-lg font-semibold tabular-nums text-debit">
                        {{ $group['total']->format($group['currency']) }}
                    </p>
                    <p class="mt-1 text-xs text-mut">Toplam borç · yalnızca {{ $group['currency']->value }}</p>
                </x-ui.card>
            @endforeach
        </section>

        <section class="mt-6" aria-labelledby="debtor-chart-title">
            <div class="mb-3">
                <h2 id="debtor-chart-title" class="text-base font-semibold text-tx">Borçlu Cariler</h2>
                <p class="mt-1 text-sm text-mut">
                    Çubuklar her para biriminin kendi en yüksek borcuna göre ölçeklenir; farklı dövizler birbiriyle kıyaslanmaz.
                </p>
            </div>

            <div class="space-y-4">
                @foreach ($currencyGroups as $group)
                    <x-ui.card data-currency-group="{{ $group['currency']->value }}">
                        <div class="flex flex-wrap items-end justify-between gap-2 border-b border-line pb-3">
                            <div>
                                <h3 class="font-semibold text-tx">{{ $group['currency']->value }} · {{ $group['currency']->label() }}</h3>
                                <p class="mt-0.5 text-xs text-mut">{{ $group['companyCount'] }} borçlu cari</p>
                            </div>
                            <p class="text-sm font-semibold tabular-nums text-debit">
                                {{ $group['total']->format($group['currency']) }}
                            </p>
                        </div>

                        <div class="mt-4 space-y-2">
                            @foreach ($group['rows'] as $row)
                                <a href="{{ route('statements.show', $row['company']) }}"
                                   class="group grid min-h-11 items-center gap-2 rounded-lg px-2 py-2 hover:bg-well md:grid-cols-[minmax(10rem,18rem)_minmax(0,1fr)_auto]"
                                   data-debtor-bar="{{ $group['currency']->value }}:{{ $row['company']->id }}"
                                   aria-label="{{ $row['company']->name }} cari ekstresini aç">
                                    <span class="min-w-0 truncate text-sm font-medium text-tx group-hover:text-acc-tx">
                                        {{ $row['company']->name }}
                                    </span>
                                    <span class="h-3 overflow-hidden rounded-full bg-well" aria-hidden="true">
                                        <span class="block h-full rounded-full bg-debit"
                                              style="width: {{ $row['barPercent'] }}%"></span>
                                    </span>
                                    <span class="whitespace-nowrap text-right text-sm font-semibold tabular-nums text-debit">
                                        {{ $row['balance']->format($group['currency']) }}
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    </x-ui.card>
                @endforeach
            </div>
        </section>

        <section class="mt-6 grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(18rem,28rem)]" aria-labelledby="activity-title">
            <x-ui.card>
                <h2 id="activity-title" class="text-base font-semibold text-tx">Son Hareket Dağılımı</h2>
                <p class="mt-1 text-sm text-mut">
                    Son cari hareketinden bu yana geçen süreyi gösterir; vade bilgisi değildir.
                </p>

                <div class="mt-4 space-y-3">
                    @foreach ($activityBuckets as $bucket)
                        <div data-activity-bucket="{{ $loop->index }}" data-count="{{ $bucket['count'] }}">
                            <div class="mb-1 flex items-center justify-between gap-3 text-sm">
                                <span class="text-tx">{{ $bucket['label'] }}</span>
                                <span class="whitespace-nowrap tabular-nums text-mut">
                                    {{ $bucket['count'] }} cari · %{{ $bucket['percent'] }}
                                </span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-well" aria-hidden="true">
                                <div class="h-full rounded-full bg-acc"
                                     style="width: {{ $bucket['percent'] }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-ui.card>

            <x-ui.card>
                <h2 class="text-base font-semibold text-tx">Rapor Kapsamı</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-mut">Tekil borçlu cari</dt>
                        <dd class="font-semibold tabular-nums text-tx">{{ $uniqueDebtorCount }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-mut">Borç satırı</dt>
                        <dd class="font-semibold tabular-nums text-tx">{{ count($detailRows) }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-mut">Aktif filtre</dt>
                        <dd class="max-w-48 text-right text-tx">{{ $filterSummary }}</dd>
                    </div>
                </dl>
                <p class="mt-4 border-t border-line pt-3 text-xs text-mut">
                    Parasal toplamlar yalnızca kendi para birimi içinde hesaplanır.
                </p>
            </x-ui.card>
        </section>

        <section class="mt-6" aria-labelledby="detail-title">
            <div class="mb-3">
                <h2 id="detail-title" class="text-base font-semibold text-tx">Borçlu Cari Detayı</h2>
                <p class="mt-1 text-sm text-mut">Her cari + pozitif para birimi bakiyesi ayrı satırdır.</p>
            </div>

            <x-ui.table>
                <thead class="border-b border-line">
                    <tr>
                        <x-ui.th>Cari</x-ui.th>
                        <x-ui.th>Para Birimi</x-ui.th>
                        <x-ui.th class="text-right">Borç Bakiyesi</x-ui.th>
                        <x-ui.th>Son Hareket</x-ui.th>
                        <x-ui.th>Son Hareketten Bu Yana</x-ui.th>
                        <x-ui.th>Ekstre</x-ui.th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($detailRows as $row)
                        <tr class="statement-row border-b border-line last:border-0 hover:bg-well"
                            data-statement-row-url="{{ route('statements.show', $row['company']) }}"
                            tabindex="0"
                            role="link"
                            aria-label="{{ $row['company']->name }} cari ekstresini aç">
                            <td class="px-4 py-3 font-medium text-tx">{{ $row['company']->name }}</td>
                            <td class="px-4 py-3 text-tx">{{ $row['currency']->value }}</td>
                            <td class="px-4 py-3 text-right font-semibold tabular-nums text-debit">
                                {{ $row['balance']->format($row['currency']) }}
                            </td>
                            <td class="px-4 py-3 tabular-nums text-mut">
                                {{ $row['lastDate'] ? \Illuminate\Support\Carbon::parse($row['lastDate'])->format('d.m.Y') : '—' }}
                            </td>
                            <td class="px-4 py-3 tabular-nums text-mut">
                                {{ $row['daysSince'] === null ? '—' : $row['daysSince'].' gün' }}
                            </td>
                            <td class="px-4 py-3 text-acc-tx">Aç</td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>

            <div class="mt-4 space-y-3 lg:hidden">
                @foreach ($detailRows as $row)
                    <a href="{{ route('statements.show', $row['company']) }}" class="block">
                        <x-ui.card>
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate font-medium text-tx">{{ $row['company']->name }}</p>
                                    <p class="mt-1 text-xs text-mut">
                                        Son hareket:
                                        <span class="tabular-nums">
                                            {{ $row['lastDate'] ? \Illuminate\Support\Carbon::parse($row['lastDate'])->format('d.m.Y') : '—' }}
                                        </span>
                                    </p>
                                </div>
                                <span class="rounded-lg bg-well px-2 py-1 text-xs font-semibold text-tx">
                                    {{ $row['currency']->value }}
                                </span>
                            </div>
                            <p class="mt-3 font-semibold tabular-nums text-debit">
                                {{ $row['balance']->format($row['currency']) }}
                            </p>
                            <p class="mt-1 text-xs text-mut">
                                {{ $row['daysSince'] === null ? 'Hareket tarihi yok' : $row['daysSince'].' gün önce' }}
                            </p>
                        </x-ui.card>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
@endsection
