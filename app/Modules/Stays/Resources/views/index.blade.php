@extends('layouts.app')
@section('title', 'Konaklamalar')

@section('content')
    <x-ui.page-header title="Konaklamalar"
                      :subtitle="number_format($stays->total(), 0, ',', '.').' konaklama kaydı'">
        <x-slot:actions>
            <x-ui.button variant="secondary" :href="route('stays.print', request()->query())">Yazdır</x-ui.button>

            @can('create', \App\Modules\Stays\Models\Stay::class)
                <x-ui.button :href="route('stays.create')">Yeni konaklama</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.filter-panel :count="$filter->activeFilterCount()"
                       :clear-href="route('stays.index')"
                       :per-page-options="$perPageOptions">
        <div class="sm:col-span-2">
            <x-ui.input label="Ara" name="q" type="search" :value="request('q')"
                        placeholder="Misafir adı, oda, firma" />
        </div>

        <x-ui.select label="Cari hesap" name="company_id">
            <option value="">Tümü</option>
            @foreach ($companies as $id => $name)
                <option value="{{ $id }}" @selected((string) request('company_id') === (string) $id)>{{ $name }}</option>
            @endforeach
        </x-ui.select>

        <x-ui.select label="Durum" name="status">
            <option value="">Tümü</option>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </x-ui.select>

        <x-ui.input label="Giriş tarihi (baş.)" name="date_from" type="date" :value="request('date_from')" />
        <x-ui.input label="Giriş tarihi (bit.)" name="date_to" type="date" :value="request('date_to')" />

        <x-ui.select label="Para birimi" name="currency">
            <option value="">Tümü</option>
            @foreach ($currencies as $currency)
                <option value="{{ $currency->value }}" @selected(request('currency') === $currency->value)>{{ $currency->value }}</option>
            @endforeach
        </x-ui.select>
    </x-ui.filter-panel>

    @if ($totals->isNotEmpty())
        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4" data-stay-totals>
            @foreach ($totals as $code => $t)
                <x-ui.card class="p-4">
                    <p class="text-xs uppercase tracking-wide text-mut">{{ $code }} toplamı</p>
                    <p class="mt-1 tabular-nums text-lg text-tx">{{ $t['total']->format($t['currency']) }}</p>
                    <p class="mt-1 text-xs text-mut">
                        {{ number_format($t['count'], 0, ',', '.') }} konaklama · bu filtreyle
                    </p>
                    @if ($t['cancelledCount'] > 0)
                        <p class="mt-1 text-xs text-mut">
                            Aktif: {{ $t['activeTotal']->format($t['currency']) }}
                            · İptal ({{ $t['cancelledCount'] }}): {{ $t['cancelledTotal']->format($t['currency']) }}
                        </p>
                    @endif
                </x-ui.card>
            @endforeach
        </div>
    @endif

    <x-ui.table class="mt-4">
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
                    <td class="px-4 py-3 whitespace-nowrap tabular-nums text-tx">{{ $stay->check_in_date->format('d.m.Y') }}</td>
                    <td class="px-4 py-3 whitespace-nowrap tabular-nums text-mut">{{ $stay->check_out_date->format('d.m.Y') }}</td>
                    <td class="px-4 py-3 text-right tabular-nums text-mut">{{ $stay->nights }}</td>
                    <td class="px-4 py-3 text-tx">{{ $stay->guest_name }}</td>
                    <td class="px-4 py-3 tabular-nums text-mut">{{ $stay->roomLabel() ?? '—' }}</td>
                    <td class="px-4 py-3 text-mut">{{ $stay->company?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-right whitespace-nowrap tabular-nums text-tx">
                        {{ $stay->amountMoney()->format($stay->currency) }}
                        <x-ui.fx-note :rate="$stay->exchange_rate_to_try" :equivalent="$stay->tryEquivalentMoney()" class="text-right" />
                    </td>
                    <td class="px-4 py-3">@include('stays::_status', ['stay' => $stay])
                        @include('stays::_payment-status')
                    </td>
                    <td class="px-4 py-3 text-mut">
                        <span class="block">{{ $stay->creator?->name ?? '—' }}</span>
                        <span class="block whitespace-nowrap tabular-nums text-xs">{{ $stay->created_at->format('d.m.Y · H:i') }}</span>
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
    </x-ui.table>

    {{-- Mobil: yalnızca hızlı okunması gereken alanlar --}}
    <div class="mt-4 space-y-3 lg:hidden">
        @forelse ($stays as $stay)
            <a href="{{ route('stays.show', $stay) }}" class="block">
                <x-ui.card>
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-tx">{{ $stay->guest_name }}</p>
                            <p class="truncate text-sm text-mut">{{ $stay->company?->name ?? '—' }}</p>
                        </div>
                        @include('stays::_status', ['stay' => $stay])
                    </div>

                    <div class="mt-3 flex items-end justify-between gap-3">
                        <p class="text-sm text-mut">
                            <span class="tabular-nums">{{ $stay->check_in_date->format('d.m.Y') }}</span>
                            → <span class="tabular-nums">{{ $stay->check_out_date->format('d.m.Y') }}</span>
                            <br>
                            <span class="tabular-nums">{{ $stay->nights }}</span> gece
                            @if ($stay->roomLabel()) · Oda <span class="tabular-nums">{{ $stay->roomLabel() }}</span> @endif
                        </p>

                        <span class="shrink-0 text-right">
                            <span class="block tabular-nums text-tx">{{ $stay->amountMoney()->format($stay->currency) }}</span>
                            @include('stays::_payment-status')
                            <x-ui.fx-note :rate="$stay->exchange_rate_to_try" :equivalent="$stay->tryEquivalentMoney()" class="text-right" />
                        </span>
                    </div>
                </x-ui.card>
            </a>
        @empty
            <x-ui.empty :filtered="$filter->hasActiveFilters()" :clear-href="route('stays.index')" />
        @endforelse
    </div>

    <div class="mt-4">{{ $stays->links() }}</div>
@endsection
