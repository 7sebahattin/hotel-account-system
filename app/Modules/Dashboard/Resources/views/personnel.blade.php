@extends('layouts.app')
@section('title', 'Pano — '.config('app.name'))

@section('content')
    <x-ui.page-header title="Pano" :subtitle="'Hoş geldiniz, '.auth()->user()->name" />
    <section class="mt-6" aria-label="Kayıtlarım">
        <h2 class="text-base font-semibold text-tx">Bugün girdiğim kayıtlar</h2>
        <div class="mt-3 grid grid-cols-2 gap-4 sm:grid-cols-4">
            <x-ui.card>
                <x-ui.stat label="Bugün konaklama" :value="$summary->staysToday" hint="Girdiğiniz kayıt" />
            </x-ui.card>

            <x-ui.card>
                <x-ui.stat label="Bu hafta konaklama" :value="$summary->staysThisWeek" hint="Girdiğiniz kayıt" />
            </x-ui.card>

            <x-ui.card>
                <x-ui.stat label="Bugün tahsilat" :value="$summary->paymentsToday" hint="Girdiğiniz kayıt" />
            </x-ui.card>

            <x-ui.card>
                <x-ui.stat label="Bu hafta tahsilat" :value="$summary->paymentsThisWeek" hint="Girdiğiniz kayıt" />
            </x-ui.card>
        </div>

        <div class="mt-4 flex flex-wrap gap-2">
            @can('stays.create')
                <x-ui.button variant="secondary" :href="route('stays.create')">Konaklama ekle</x-ui.button>
            @endcan

            @can('payments.create')
                <x-ui.button variant="secondary" :href="route('payments.create')">Cari Tahsilat</x-ui.button>
            @endcan
        </div>

        @if ($summary->hasRecords())
            <div class="mt-4 grid items-start gap-4 lg:grid-cols-2">
                <x-ui.panel title="Son girdiğiniz konaklamalar" :href="route('stays.index')">
                    @forelse ($summary->recentStays as $stay)
                        <a href="{{ route('stays.show', $stay) }}"
                           @class(['block px-4 py-3 hover:bg-well', 'border-t border-line' => ! $loop->first])>
                            <p class="truncate text-sm text-tx">{{ $stay->guest_name }}</p>

                            <p class="mt-1 text-xs tabular-nums text-mut">
                                {{ $stay->check_in_date->format('d.m.Y') }} – {{ $stay->check_out_date->format('d.m.Y') }}
                            </p>
                        </a>
                    @empty
                        <x-ui.empty class="m-4 border-0 bg-transparent py-8">Konaklama kaydınız yok.</x-ui.empty>
                    @endforelse
                </x-ui.panel>

                <x-ui.panel title="Son girdiğiniz tahsilatlar" :href="route('payments.index')">
                    @forelse ($summary->recentPayments as $payment)
                        <a href="{{ route('payments.show', $payment) }}"
                           @class(['block px-4 py-3 hover:bg-well', 'border-t border-line' => ! $loop->first])>
                            <p class="truncate text-sm text-tx">{{ $payment->reference_no ?: 'Referans yok' }}</p>

                            <p class="mt-1 flex flex-wrap items-center gap-2 text-xs tabular-nums text-mut">
                                {{ $payment->payment_date->format('d.m.Y') }}

                                @if ($payment->isCancelled())
                                    <x-ui.badge tone="danger">İptal</x-ui.badge>
                                @endif
                            </p>
                        </a>
                    @empty
                        <x-ui.empty class="m-4 border-0 bg-transparent py-8">Tahsilat kaydınız yok.</x-ui.empty>
                    @endforelse
                </x-ui.panel>
            </div>
        @endif
    </section>
@endsection
