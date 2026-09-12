@extends('layouts.app')
@section('title', 'Devir Geçmişi')

@section('content')
    <x-ui.page-header title="Devir Geçmişi" :count="$transfers->total()"
                      :subtitle="$canViewAll ? 'Tüm personel arasındaki kasa devirleri' : 'Gönderdiğiniz/aldığınız devirler'">
        <x-slot:actions>
            <x-ui.button variant="ghost" :href="route('cashbook.index')">Kasa Defteri</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.filter-panel :count="$filter->activeFilterCount()"
                       :clear-href="route('cashbook.transfers.index')"
                       :per-page-options="$perPageOptions">
        <div class="sm:col-span-2">
            <x-ui.input label="Ara" name="q" type="search" :value="request('q')"
                        placeholder="Personel adı veya not" />
        </div>

        <x-ui.select label="Durum" name="status">
            <option value="">Tümü</option>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </x-ui.select>

        <x-ui.select label="Para birimi" name="currency">
            <option value="">Tümü</option>
            @foreach ($currencies as $currency)
                <option value="{{ $currency->value }}" @selected(request('currency') === $currency->value)>{{ $currency->value }}</option>
            @endforeach
        </x-ui.select>

        <x-ui.input label="Tarih (baş.)" name="date_from" type="date" :value="request('date_from')" />
        <x-ui.input label="Tarih (bit.)" name="date_to" type="date" :value="request('date_to')" />

        @if ($canViewAll)
            <x-ui.select label="Teslim eden" name="from_user_id">
                <option value="">Tümü</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}" @selected((string) request('from_user_id') === (string) $user->id)>{{ $user->name }}</option>
                @endforeach
            </x-ui.select>

            <x-ui.select label="Teslim alan" name="to_user_id">
                <option value="">Tümü</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}" @selected((string) request('to_user_id') === (string) $user->id)>{{ $user->name }}</option>
                @endforeach
            </x-ui.select>
        @endif
    </x-ui.filter-panel>

    <x-ui.panel title="Devirler" class="mt-4">
        @forelse ($transfers as $transfer)
            <div @class(['px-4 py-3', 'border-t border-line' => ! $loop->first])>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <span class="min-w-0 truncate text-sm text-tx">
                        {{ $transfer->fromUser?->name ?? '—' }} → {{ $transfer->toUser?->name ?? '—' }}
                    </span>

                    <x-ui.badge :tone="match ($transfer->status->value) {
                        'accepted' => 'credit',
                        'cancelled' => 'danger',
                        default => 'neutral',
                    }">{{ $transfer->status->label() }}</x-ui.badge>
                </div>

                <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-mut">
                    <span class="tabular-nums">{{ $transfer->initiated_at->format('d.m.Y H:i') }}</span>

                    @if ($transfer->amounts->isNotEmpty())
                        <span aria-hidden="true">·</span>
                        <span class="tabular-nums">
                            @foreach ($transfer->amounts as $amount)
                                {{ $amount->amountMoney()->format($amount->currency) }}@if (! $loop->last), @endif
                            @endforeach
                        </span>
                    @endif

                    @if ($transfer->note)
                        <span aria-hidden="true">·</span>
                        <span class="truncate">{{ $transfer->note }}</span>
                    @endif
                </div>
            </div>
        @empty
            <x-ui.empty class="m-4 border-0 bg-transparent py-8" :filtered="$filter->hasActiveFilters()"
                        :clear-href="route('cashbook.transfers.index')" />
        @endforelse
    </x-ui.panel>

    <div class="mt-4">{{ $transfers->links() }}</div>
@endsection
