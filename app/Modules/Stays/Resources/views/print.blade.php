@extends('layouts.print')
@section('title', 'Konaklamalar')

@section('content')
    <div class="toolbar">
        <button type="button" onclick="window.print()">Yazdır</button>
        <a href="{{ route('stays.index', request()->query()) }}">Listeye dön</a>
    </div>

    <div class="head">
        <h1>Konaklamalar</h1>
        <p>
            {{ number_format($stays->count(), 0, ',', '.') }} kayıt
            @if ($filter->hasActiveFilters()) · Filtrelenmiş sonuçlar @endif
            · Yazdırma tarihi: {{ now()->format('d.m.Y H:i') }}
        </p>
    </div>

    {{--
        Para birimi başına AYRI kutu, kasa devir raporuyla AYNI desen — hiçbir
        yerde para birimleri toplanmaz/çevrilmez (CLAUDE.md §6/L5). İptal
        edilmiş tutarlar normal toplamdan AYRI gösterilir ama gizlenmez.
    --}}
    @if ($totals->isNotEmpty())
        <div class="summaries">
            @foreach ($totals as $code => $t)
                <div class="summary">
                    <h2>{{ $code }} — {{ $t['currency']->label() }}</h2>
                    <dl>
                        <div class="line total">
                            <dt>Toplam</dt>
                            <dd class="num">{{ $t['total']->format($t['currency']) }}</dd>
                        </div>
                        @if ($t['cancelledCount'] > 0)
                            <div class="line">
                                <dt>Aktif</dt>
                                <dd class="num">{{ $t['activeTotal']->format($t['currency']) }}</dd>
                            </div>
                            <div class="line">
                                <dt>İptal ({{ $t['cancelledCount'] }})</dt>
                                <dd class="num">{{ $t['cancelledTotal']->format($t['currency']) }}</dd>
                            </div>
                        @endif
                    </dl>
                    <p class="meaning">{{ number_format($t['count'], 0, ',', '.') }} konaklama</p>
                </div>
            @endforeach
        </div>
    @endif

    <div class="scroll-x">
    <table class="stays-print-table">
        <colgroup>
            <col class="stay-col-date">
            <col class="stay-col-date">
            <col class="stay-col-guest">
            <col class="stay-col-room">
            <col class="stay-col-company">
            <col class="stay-col-amount">
            <col class="stay-col-status">
            <col class="stay-col-transaction">
        </colgroup>
        <thead>
            <tr>
                <th class="stay-date-cell">Giriş</th>
                <th class="stay-date-cell">Çıkış</th>
                <th>Misafir</th>
                <th>Oda</th>
                <th>Cari hesap</th>
                <th class="stay-amount-cell">Tutar</th>
                <th>Durum</th>
                <th class="stay-transaction-time">İşlem zamanı</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($stays as $stay)
                <tr @class(['rev' => $stay->isCancelled()])>
                    <td class="stay-date-cell">{{ $stay->check_in_date->format('d.m.Y') }}</td>
                    <td class="stay-date-cell">{{ $stay->check_out_date->format('d.m.Y') }}</td>
                    <td>{{ $stay->guest_name }}</td>
                    <td>{{ $stay->roomLabel() ?? '—' }}</td>
                    <td>{{ $stay->company?->name ?? '—' }}</td>
                    <td class="stay-amount-cell">
                        {{ $stay->amountMoney()->format($stay->currency) }}
                        @if ($stay->try_equivalent !== null)
                            <span class="fx">≈ {{ $stay->tryEquivalentMoney()->format(\App\Modules\Core\Enums\Currency::TRY) }}</span>
                        @endif
                    </td>
                    <td>{{ $stay->status->label() }}</td>
                    <td class="stay-transaction-time">
                        <span class="stay-transaction-date">{{ $stay->created_at->format('d.m.Y') }}</span>
                        <span class="stay-transaction-clock">{{ $stay->created_at->format('H:i') }}</span>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8">Bu filtreyle eşleşen kayıt bulunamadı.</td></tr>
            @endforelse
        </tbody>
    </table>
    </div>

    <div class="foot">
        <span>{{ config('app.name') }}</span>
        <span>{{ now()->format('d.m.Y H:i') }} tarihinde yazdırıldı</span>
    </div>
@endsection
