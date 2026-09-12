@extends('layouts.app')
@section('title', $company->name.' — cari ekstre')

@section('content')
    <x-ui.page-header :title="$company->name" subtitle="Cari ekstre">
        <x-slot:meta>
            @if ($criteria->dateFrom || $criteria->dateTo)
                <p class="mt-1 text-sm tabular-nums text-mut">
                    {{ $criteria->dateFrom?->format('d.m.Y') ?? 'Başlangıç' }}
                    – {{ $criteria->dateTo?->format('d.m.Y') ?? 'Bugün' }}
                </p>
            @endif
        </x-slot:meta>

        <x-slot:actions>
            <x-ui.button variant="secondary"
                         :href="route('statements.print', [$company, ...$criteria->toQuery()])">
                Yazdır
            </x-ui.button>
            <x-ui.button variant="ghost" :href="route('companies.show', $company)">Cari hesap kartı</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($statement->hasSummaries())
        <div class="mt-6">@include('companies::statements._summary', ['fx' => $fx])</div>
    @endif

    {{-- Grid kapalı: bu ekranın gövdesi iki ANLAMLI gruba ayrılır (bakiyenin
         kapsamı / yalnızca görünümü daraltanlar), tek bir düz grid değildir. --}}
    <x-ui.filter-panel :count="$activeFilterCount"
                       :clear-href="route('statements.show', $company)"
                       :grid="false">
        <p class="text-xs font-semibold uppercase tracking-wide text-mut">Bakiyenin kapsamı</p>
        <p class="mt-0.5 text-xs text-mut">Bu üç alan hangi hareketlerin hesaba katılacağını belirler.</p>

        <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <x-ui.input label="Başlangıç tarihi" name="date_from" type="date" :value="request('date_from')"
                        hint="Öncesi devreden bakiye olur" />
            <x-ui.input label="Bitiş tarihi" name="date_to" type="date" :value="request('date_to')" />

            <x-ui.select label="Para birimi" name="currency"
                         hint="Para birimleri ayrı hesaplanır, toplanmaz">
                <option value="">Tüm para birimleri</option>
                @foreach ($currencies as $currency)
                    <option value="{{ $currency->value }}" @selected(request('currency') === $currency->value)>
                        {{ $currency->label() }} ({{ $currency->value }})
                    </option>
                @endforeach
            </x-ui.select>
        </div>

        <p class="mt-5 text-xs font-semibold uppercase tracking-wide text-mut">Yalnızca görünümü daraltır</p>
        <p class="mt-0.5 text-xs text-mut">Bakiye kolonu bunlardan etkilenmez.</p>

        <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <x-ui.select label="İşlem türü" name="type">
                <option value="">Tümü</option>
                @foreach ($types as $type)
                    <option value="{{ $type->value }}" @selected(request('type') === $type->value)>
                        {{ $type->label() }}
                    </option>
                @endforeach
            </x-ui.select>

            <x-ui.input label="Açıklamada ara" name="q" type="search" :value="request('q')" />

            <x-ui.select label="Sıralama" name="direction">
                <option value="asc" @selected(request('direction') !== 'desc')>Eskiden yeniye</option>
                <option value="desc" @selected(request('direction') === 'desc')>Yeniden eskiye</option>
            </x-ui.select>
        </div>
    </x-ui.filter-panel>

    @if ($criteria->hasDisplayFilters())
        <x-ui.alert tone="info" class="mt-4">
            İşlem türü ve açıklama filtreleri yalnızca <strong>görünen satırları</strong> daraltır.
            Bakiye kolonu, defterin tamamı üzerinden yürüyen gerçek bakiyeyi göstermeye devam eder.
        </x-ui.alert>
    @endif

    @foreach ($statement->carryForwards() as $summary)
        @php $carryFx = $fx['carryForward'][$summary->currency->value] ?? null; @endphp
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-field bg-well px-4 py-3">
            <span class="text-sm text-tx">
                Devreden bakiye
                <span class="font-medium">({{ $summary->currency->value }})</span>
                · {{ $criteria->dateFrom?->format('d.m.Y') }} öncesi
            </span>
            <span class="text-right">
                <span class="block font-medium tabular-nums text-tx">{{ $summary->carryForward->format($summary->currency) }}</span>
                @if ($carryFx)
                    <x-ui.fx-note :equivalent="$carryFx['value']" class="text-right" />
                    @if (! $carryFx['complete'])
                        <span class="block text-xs text-mut">TL karşılığı eksik veri nedeniyle tam değil</span>
                    @endif
                @endif
            </span>
        </div>
    @endforeach

    <x-ui.table class="mt-4">
        <thead class="border-b border-line">
            <tr>
                <x-ui.th>Tarih</x-ui.th>
                <x-ui.th>İşlem</x-ui.th>
                <x-ui.th>Açıklama</x-ui.th>
                <x-ui.th align="right">Borç</x-ui.th>
                <x-ui.th align="right">Alacak</x-ui.th>
                <x-ui.th>Para birimi</x-ui.th>
                <x-ui.th align="right" class="bg-well">Bakiye</x-ui.th>
                <x-ui.th>Kaynak</x-ui.th>
            </tr>
        </thead>
        <tbody>
            @forelse ($statement->rows as $row)
                @php $rowFx = $fx['byEntry'][$row->entry->id] ?? null; @endphp
                <tr class="border-b border-line last:border-0 hover:bg-well">
                    <td class="px-4 py-3 whitespace-nowrap tabular-nums text-tx">
                        {{ $row->entry->entry_date->format('d.m.Y') }}
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        {{-- Her işlem türü rozetle gösterilir; ters kayıt ayrıca
                             kırmızı tonla ayrışır ama anlam metinde durur. --}}
                        <x-ui.badge :tone="match (true) {
                            $row->isReversal() => 'danger',
                            $row->entry->isDebit() => 'debit',
                            default => 'credit',
                        }">{{ $row->type()->label() }}</x-ui.badge>
                    </td>
                    <td class="px-4 py-3 text-mut">{{ $row->entry->description }}</td>
                    <td class="px-4 py-3 text-right whitespace-nowrap tabular-nums">
                        @if ($row->entry->debitMoney()->isZero())
                            <span class="text-mut" aria-hidden="true">—</span>
                        @else
                            <span class="font-medium text-debit">{{ $row->entry->debitMoney()->format($row->currency()) }}</span>
                            @if ($rowFx && $row->entry->isDebit())
                                <x-ui.fx-note :equivalent="$rowFx['equivalent']" class="text-right" />
                            @endif
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right whitespace-nowrap tabular-nums">
                        @if ($row->entry->creditMoney()->isZero())
                            <span class="text-mut" aria-hidden="true">—</span>
                        @else
                            <span class="font-medium text-credit">{{ $row->entry->creditMoney()->format($row->currency()) }}</span>
                            @if ($rowFx && ! $row->entry->isDebit())
                                <x-ui.fx-note :equivalent="$rowFx['equivalent']" class="text-right" />
                            @endif
                        @endif
                    </td>
                    <td class="px-4 py-3 text-mut">{{ $row->currency()->value }}</td>
                    <td class="px-4 py-3 text-right whitespace-nowrap bg-well font-semibold tabular-nums text-tx">
                        {{ $row->runningBalance->format($row->currency()) }}
                        @if ($rowFx)
                            <x-ui.fx-note :equivalent="$rowFx['runningTl']" class="text-right" />
                            @if (! $rowFx['complete'])
                                <span class="block text-xs text-mut">eksik veri</span>
                            @endif
                        @endif
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-xs">
                        @include('companies::statements._source-link')
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="px-4 py-10 text-center text-mut">
                    Bu aralıkta cari hareket bulunmuyor.
                </td></tr>
            @endforelse
        </tbody>
    </x-ui.table>

    {{-- Mobil: yatay kaydırma yok; her tutarın yanında para birimi açıkça yazar. --}}
    <div class="mt-4 space-y-3 lg:hidden">
        @forelse ($statement->rows as $row)
            @php $rowFx = $fx['byEntry'][$row->entry->id] ?? null; @endphp
            <x-ui.card>
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="tabular-nums text-sm text-tx">{{ $row->entry->entry_date->format('d.m.Y') }}</p>
                        <p class="mt-0.5 text-sm">
                            @if ($row->isReversal())
                                <x-ui.badge tone="danger">{{ $row->type()->label() }}</x-ui.badge>
                            @else
                                <span class="text-tx">{{ $row->type()->label() }}</span>
                            @endif
                        </p>
                    </div>
                    <span class="shrink-0 text-right">
                        <span class="block text-xs text-mut">{{ $row->entry->isDebit() ? 'Borç' : 'Alacak' }}</span>
                        <span @class([
                            'block font-medium tabular-nums',
                            'text-debit' => $row->entry->isDebit(),
                            'text-credit' => ! $row->entry->isDebit(),
                        ])>{{ $row->entry->amount()->format($row->currency()) }}</span>
                        @if ($rowFx)
                            <x-ui.fx-note :equivalent="$rowFx['equivalent']" class="text-right" />
                        @endif
                    </span>
                </div>

                <p class="mt-2 text-sm text-mut">{{ $row->entry->description }}</p>

                <div class="mt-3 flex items-end justify-between gap-3 border-t border-line pt-3">
                    <span class="text-xs">@include('companies::statements._source-link')</span>
                    <span class="text-right text-sm text-mut">
                        Bakiye
                        <span class="block font-semibold tabular-nums text-tx">{{ $row->runningBalance->format($row->currency()) }}</span>
                        @if ($rowFx)
                            <x-ui.fx-note :equivalent="$rowFx['runningTl']" class="text-right" />
                            @if (! $rowFx['complete'])
                                <span class="block text-xs text-mut">eksik veri</span>
                            @endif
                        @endif
                    </span>
                </div>
            </x-ui.card>
        @empty
            <x-ui.empty>Bu aralıkta cari hareket bulunmuyor.</x-ui.empty>
        @endforelse
    </div>
@endsection
