@extends('layouts.app')
@section('title', 'Tahsilatlar')

@section('content')
    <x-ui.page-header title="Tahsilatlar"
                      :subtitle="number_format($payments->total(), 0, ',', '.').' tahsilat kaydı'">
        <x-slot:actions>
            @can('create', \App\Modules\Payments\Models\Payment::class)
                <x-ui.button :href="route('payments.create')">Cari Tahsilat</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <form method="GET" class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="sm:col-span-2">
            <x-ui.input label="Referans no veya açıklama" name="q" type="search" :value="request('q')" />
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

        <x-ui.input label="Tarih (baş.)" name="date_from" type="date" :value="request('date_from')" />
        <x-ui.input label="Tarih (bit.)" name="date_to" type="date" :value="request('date_to')" />

        <x-ui.select label="Ödeme yöntemi" name="payment_method">
            <option value="">Tümü</option>
            @foreach ($methods as $method)
                <option value="{{ $method->value }}" @selected(request('payment_method') === $method->value)>{{ $method->label() }}</option>
            @endforeach
        </x-ui.select>

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
            <x-ui.button variant="ghost" :href="route('payments.index')">Temizle</x-ui.button>

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
                <x-ui.sort-header :filter="$filter" column="payment_date">Tarih</x-ui.sort-header>
                <x-ui.th>Firma</x-ui.th>
                <x-ui.sort-header :filter="$filter" column="amount" align="right">Tutar</x-ui.sort-header>
                <x-ui.th>Yöntem</x-ui.th>
                <x-ui.sort-header :filter="$filter" column="reference_no">Referans</x-ui.sort-header>
                <x-ui.th>Durum</x-ui.th>
                <x-ui.th>Oluşturan</x-ui.th>
            </tr>
        </thead>
        <tbody>
            @forelse ($payments as $payment)
                <tr class="border-b border-line last:border-0 hover:bg-well">
                    <td class="px-4 py-3 whitespace-nowrap">
                        <a href="{{ route('payments.show', $payment) }}" class="tabular-nums text-tx hover:text-acc">
                            {{ $payment->payment_date->format('d.m.Y') }}
                        </a>
                    </td>
                    <td class="px-4 py-3 text-mut">{{ $payment->company?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-right whitespace-nowrap tabular-nums text-tx">
                        {{ $payment->amountMoney()->format($payment->currency) }}
                    </td>
                    <td class="px-4 py-3 text-mut">{{ $payment->payment_method?->label() ?? '—' }}</td>
                    <td class="px-4 py-3 tabular-nums text-mut">{{ $payment->reference_no ?: '—' }}</td>
                    <td class="px-4 py-3">@include('payments::_status', ['payment' => $payment])</td>
                    <td class="px-4 py-3 text-mut">{{ $payment->creator?->name ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-4 py-10 text-center text-sm text-mut">
                        @if ($filter->hasActiveFilters())
                            Bu filtrelerle eşleşen kayıt bulunamadı.
                            <a href="{{ route('payments.index') }}"
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
        @forelse ($payments as $payment)
            <a href="{{ route('payments.show', $payment) }}" class="block">
                <x-ui.card>
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-tx">{{ $payment->company?->name ?? '—' }}</p>
                            <p class="tabular-nums text-sm text-mut">{{ $payment->payment_date->format('d.m.Y') }}</p>
                        </div>
                        @include('payments::_status', ['payment' => $payment])
                    </div>

                    <div class="mt-3 flex items-end justify-between gap-3">
                        <p class="min-w-0 text-sm text-mut">
                            {{ $payment->payment_method?->label() ?? '—' }}
                            @if ($payment->reference_no)
                                <br>Ref: <span class="tabular-nums">{{ $payment->reference_no }}</span>
                            @endif
                        </p>

                        <span class="shrink-0 tabular-nums text-tx">{{ $payment->amountMoney()->format($payment->currency) }}</span>
                    </div>
                </x-ui.card>
            </a>
        @empty
            <x-ui.empty :filtered="$filter->hasActiveFilters()" :clear-href="route('payments.index')" />
        @endforelse
    </div>

    <div class="mt-4">{{ $payments->links() }}</div>
@endsection
