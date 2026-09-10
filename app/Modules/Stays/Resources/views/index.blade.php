@extends('layouts.app')
@section('title', 'Konaklamalar')

@section('content')
    <x-ui.page-header title="Konaklamalar"
                      :subtitle="number_format($stays->total(), 0, ',', '.').' konaklama kaydı'">
        <x-slot:actions>
            @can('create', \App\Modules\Stays\Models\Stay::class)
                <x-ui.button :href="route('stays.create')">Yeni konaklama</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <form method="GET" class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="sm:col-span-2">
            <x-ui.input label="Ara" name="q" type="search" :value="request('q')"
                        placeholder="Misafir adı, oda, firma" />
        </div>

        <x-ui.select label="Firma" name="company_id">
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

        <div class="flex flex-wrap items-end gap-3 sm:col-span-2 lg:col-span-4">
            <x-ui.button type="submit">
                Filtrele
                @if ($filter->activeFilterCount() > 0)
                    ({{ $filter->activeFilterCount() }})
                @endif
            </x-ui.button>
            <x-ui.button variant="ghost" :href="route('stays.index')">Temizle</x-ui.button>

            <label for="per_page" class="ml-auto flex items-center gap-2 text-sm text-mut">
                Sayfa başına
                <select id="per_page" name="per_page" onchange="this.form.submit()"
                        class="min-h-11 rounded-lg border border-field bg-well px-2 text-tx focus:border-acc">
                    @foreach ($perPageOptions as $option)
                        <option value="{{ $option }}" @selected((int) request('per_page', 25) === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </form>

    <x-ui.table class="mt-4">
        <thead class="border-b border-line">
            <tr>
                <x-ui.sort-header :filter="$filter" column="check_in_date">Giriş</x-ui.sort-header>
                <x-ui.sort-header :filter="$filter" column="check_out_date">Çıkış</x-ui.sort-header>
                <x-ui.sort-header :filter="$filter" column="nights" align="right">Gece</x-ui.sort-header>
                <x-ui.sort-header :filter="$filter" column="guest_name">Misafir</x-ui.sort-header>
                <x-ui.th>Oda</x-ui.th>
                <x-ui.th>Firma</x-ui.th>
                <x-ui.sort-header :filter="$filter" column="amount" align="right">Tutar</x-ui.sort-header>
                <x-ui.th>Durum</x-ui.th>
                <x-ui.th>Oluşturan</x-ui.th>
            </tr>
        </thead>
        <tbody>
            @forelse ($stays as $stay)
                <tr class="border-b border-line last:border-0 hover:bg-well">
                    <td class="px-4 py-3 whitespace-nowrap">
                        <a href="{{ route('stays.show', $stay) }}" class="tabular-nums text-tx hover:text-acc">
                            {{ $stay->check_in_date->format('d.m.Y') }}
                        </a>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap tabular-nums text-mut">{{ $stay->check_out_date->format('d.m.Y') }}</td>
                    <td class="px-4 py-3 text-right tabular-nums text-mut">{{ $stay->nights }}</td>
                    <td class="px-4 py-3 text-tx">{{ $stay->guest_name }}</td>
                    <td class="px-4 py-3 tabular-nums text-mut">{{ $stay->roomLabel() ?? '—' }}</td>
                    <td class="px-4 py-3 text-mut">{{ $stay->company?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-right whitespace-nowrap tabular-nums text-tx">
                        {{ $stay->amountMoney()->format($stay->currency) }}
                        <x-ui.fx-note :rate="$stay->exchange_rate_to_try" :equivalent="$stay->tryEquivalentMoney()" class="text-right" />
                    </td>
                    <td class="px-4 py-3">@include('stays::_status', ['stay' => $stay])</td>
                    <td class="px-4 py-3 text-mut">{{ $stay->creator?->name ?? '—' }}</td>
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
