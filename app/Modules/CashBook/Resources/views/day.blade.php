@extends('layouts.app')
@section('title', 'Günlük kasa defteri')

@section('content')
    <x-ui.page-header title="Günlük kasa defteri"
                      :subtitle="$date->translatedFormat('d F Y').' · '.$sessions->count().' vardiya'" />

    <form method="GET" class="mt-4 flex flex-wrap items-end gap-3">
        <x-ui.input label="Tarih" name="date" type="date" :value="$date->toDateString()" />
        <x-ui.button type="submit">Göster</x-ui.button>

        <x-ui.button variant="ghost" :href="route('cashbook.day', ['date' => $date->copy()->subDay()->toDateString()])">
            Önceki gün
        </x-ui.button>
        <x-ui.button variant="ghost" :href="route('cashbook.day', ['date' => $date->copy()->addDay()->toDateString()])">
            Sonraki gün
        </x-ui.button>
    </form>

    @forelse ($sessions as $session)
        @php $summary = $summaries[$session->getKey()]; @endphp

        <x-ui.panel class="mt-4"
                    :title="$session->openedBy?->name.' · '.$session->opened_at->format('H:i').' – '.($session->closed_at?->format('H:i') ?? 'devam ediyor')"
                    :href="route('cashbook.sessions.show', $session)" link-label="Detay">
            <div class="px-4 py-3">
                <div class="flex flex-wrap items-center gap-2">
                    <x-ui.badge :tone="$session->isOpen() ? 'success' : 'neutral'">
                        {{ $session->status->label() }}
                    </x-ui.badge>

                    <span class="text-xs text-mut tabular-nums">{{ $summary->movementCount }} hareket</span>

                    @if ($summary->hasCountDifference())
                        <x-ui.badge tone="danger">Sayım farkı</x-ui.badge>
                    @endif
                </div>

                @if ($summary->totals === [])
                    <p class="mt-3 text-sm text-mut">Bu vardiyada hareket yok.</p>
                @else
                    <dl class="mt-3 space-y-1 text-sm">
                        @foreach ($summary->totals as $code => $totals)
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-mut">{{ $code }} beklenen</dt>
                                <dd class="font-medium tabular-nums text-tx">
                                    {{ $totals->expected()->format($totals->currency) }}
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </div>

            @if ($summary->counts->isNotEmpty())
                <div class="border-t border-line">
                    @include('cashbook::_counts', ['counts' => $summary->counts])
                </div>
            @endif
        </x-ui.panel>
    @empty
        <x-ui.empty class="mt-4">Bu tarihte kasa vardiyası yok.</x-ui.empty>
    @endforelse
@endsection
