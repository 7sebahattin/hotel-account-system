@extends('layouts.app')
@section('title', 'Vardiya detayı')

@section('content')
    @php $session = $summary->session; @endphp

    <x-ui.page-header
        :title="$session->openedBy?->name.' vardiyası'"
        :subtitle="$session->opened_at->format('d.m.Y H:i').' – '.($session->closed_at?->format('d.m.Y H:i') ?? 'devam ediyor')">
        <x-slot:actions>
            <x-ui.button variant="secondary" :href="route('cashbook.sessions.print', $session)">
                Devir raporu
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mt-3 flex flex-wrap items-center gap-2">
        <x-ui.badge :tone="$session->isOpen() ? 'success' : 'neutral'">
            {{ $session->status->label() }}
        </x-ui.badge>

        <span class="text-xs text-mut tabular-nums">{{ $summary->movementCount }} hareket</span>

        @if ($summary->hasCountDifference())
            <x-ui.badge tone="danger">Sayım farkı var</x-ui.badge>
        @endif
    </div>

    @if ($summary->incomingTransfer)
        <p class="mt-3 text-sm text-mut">
            Kasa <strong class="font-medium text-tx">{{ $summary->incomingTransfer->fromUser?->name }}</strong>
            tarafından devredildi
            ({{ $summary->incomingTransfer->responded_at?->format('d.m.Y H:i') }}).
        </p>
    @endif

    @include('cashbook::_expected', [
        'summary' => $summary,
        'title' => $session->isOpen() ? 'Kasada beklenen' : 'Vardiya toplamları',
    ])

    @if ($summary->counts->isNotEmpty())
        <x-ui.panel title="Kapanış sayımı" class="mt-4">
            @include('cashbook::_counts', ['counts' => $summary->counts])
        </x-ui.panel>
    @endif

    @if ($summary->outgoingTransfer?->isAccepted())
        <p class="mt-4 text-sm text-mut">
            Kasa <strong class="font-medium text-tx">{{ $summary->outgoingTransfer->toUser?->name }}</strong>
            kişisine devredildi
            ({{ $summary->outgoingTransfer->responded_at?->format('d.m.Y H:i') }}).
        </p>
    @endif

    <x-ui.panel title="Hareketler" class="mt-4">
        @forelse ($movements as $movement)
            @include('cashbook::movements._row', ['movement' => $movement, 'canReverse' => false])
        @empty
            <x-ui.empty class="m-4 border-0 bg-transparent py-8">
                Bu vardiyada hareket yok.
            </x-ui.empty>
        @endforelse
    </x-ui.panel>
@endsection
