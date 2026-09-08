@extends('layouts.print')
@section('title', 'Kasa devir raporu')

@section('content')
    @php $session = $summary->session; @endphp

    <div class="toolbar">
        <button type="button" onclick="window.print()">Yazdır</button>
        <a href="{{ route('cashbook.sessions.show', $session) }}">Vardiyaya dön</a>
    </div>

    <div class="head">
        <h1>Kasa Devir Raporu</h1>
        <p>
            {{ $session->openedBy?->name }} ·
            {{ $session->opened_at->format('d.m.Y H:i') }}
            – {{ $session->closed_at?->format('d.m.Y H:i') ?? 'devam ediyor' }}
            · {{ $summary->movementCount }} hareket
        </p>
    </div>

    {{--
        Para birimi başına AYRI kutu. Hiçbir yerde para birimleri toplanmaz ve
        çevrilmez (CLAUDE.md §6/L5); "genel toplam" satırı bilinçli olarak yoktur.
    --}}
    <div class="summaries">
        @foreach ($summary->totals as $code => $totals)
            @php $count = $summary->countFor($totals->currency); @endphp

            <div class="summary">
                <h2>{{ $code }} — {{ $totals->currency->label() }}</h2>

                <dl>
                    @foreach (\App\Modules\CashBook\Enums\CashMovementType::cases() as $type)
                        @php $net = $totals->forType($type); @endphp

                        @unless ($net->isZero())
                            <div class="line">
                                <dt>{{ $type->label() }}</dt>
                                <dd class="num">{{ $net->format($totals->currency) }}</dd>
                            </div>
                        @endunless
                    @endforeach

                    <div class="line total">
                        <dt>Beklenen</dt>
                        <dd class="num">{{ $totals->expected()->format($totals->currency) }}</dd>
                    </div>

                    @if ($count)
                        <div class="line">
                            <dt>Sayılan</dt>
                            <dd class="num">{{ $count->countedMoney()->format($totals->currency) }}</dd>
                        </div>
                        <div class="line total">
                            <dt>Fark</dt>
                            <dd class="num">{{ $count->differenceMoney()->format($totals->currency) }}</dd>
                        </div>
                    @endif
                </dl>

                @if ($count && $count->hasDifference())
                    <p class="meaning">
                        {{ $count->differenceMoney()->isNegative() ? 'Kasada eksik var.' : 'Kasada fazla var.' }}
                    </p>
                @endif
            </div>
        @endforeach
    </div>

    @if ($summary->totals === [])
        <p>Bu vardiyada hiç kasa hareketi yok.</p>
    @endif

    <div class="scroll-x">
    <table>
        <thead>
            <tr>
                <th>Saat</th>
                <th>Tür</th>
                <th>Açıklama</th>
                <th>Teslim alan</th>
                <th class="num">Tutar</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($movements->sortBy([['entry_at', 'asc'], ['id', 'asc']]) as $movement)
                <tr @class(['rev' => $movement->is_reversal])>
                    <td class="num">{{ $movement->entry_at->format('d.m.Y H:i') }}</td>
                    <td>{{ $movement->label() }}</td>
                    <td>{{ $movement->description ?: '—' }}</td>
                    <td>{{ $movement->counterparty ?: '—' }}</td>
                    <td class="num">
                        {{ $movement->isIn() ? '+' : '−' }}{{ $movement->amountMoney()->format($movement->currency) }}
                    </td>
                </tr>
            @empty
                <tr><td colspan="5">Hareket yok.</td></tr>
            @endforelse
        </tbody>
    </table>
    </div>

    <div class="foot">
        <span>
            @if ($summary->outgoingTransfer?->isAccepted())
                {{ $summary->outgoingTransfer->toUser?->name }} kişisine devredildi ·
                {{ $summary->outgoingTransfer->responded_at?->format('d.m.Y H:i') }}
            @elseif ($session->isClosed())
                Vardiya kapatıldı · {{ $session->closed_at?->format('d.m.Y H:i') }}
            @else
                Vardiya devam ediyor
            @endif
        </span>

        <span>{{ now()->format('d.m.Y H:i') }} tarihinde yazdırıldı</span>
    </div>
@endsection
