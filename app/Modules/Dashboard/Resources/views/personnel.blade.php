@extends('layouts.app')
@section('title', 'Pano — '.config('app.name'))

@section('content')
    {{--
        Personel panosu. Burada BİLEREK hiçbir tutar yoktur (CLAUDE.md §7.4):
        bakiye, toplam alacak ve finansal kartlar personele gösterilmez ve
        controller tarafından hiç sorgulanmaz.
    --}}
    <x-ui.page-header
        title="Pano"
        :subtitle="'Hoş geldiniz, '.auth()->user()->name" />

    <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
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
            <x-ui.button :href="route('stays.create')">Konaklama ekle</x-ui.button>
        @endcan

        @can('payments.create')
            <x-ui.button variant="secondary" :href="route('payments.create')">Tahsilat ekle</x-ui.button>
        @endcan
    </div>

    @unless ($summary->hasRecords())
        <x-ui.card class="mt-4 p-10 text-center">
            <h2 class="text-base font-semibold text-tx">Henüz kayıt girmediniz</h2>

            <p class="mx-auto mt-2 max-w-md text-sm text-mut">
                Girdiğiniz konaklama ve tahsilatlar burada listelenir.
            </p>
        </x-ui.card>
    @else
        <div class="mt-4 grid items-start gap-4 lg:grid-cols-2">
            {{-- Satırın tamamı dokunulabilir — yalnızca misafir adı/ref değil. --}}
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
    @endunless
@endsection
