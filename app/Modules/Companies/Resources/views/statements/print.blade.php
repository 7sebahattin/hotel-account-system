@extends('layouts.print')
@section('title', $company->name.' — cari ekstre')

@section('content')
    <div class="toolbar">
        <button type="button" onclick="window.print()">Yazdır</button>
        <a href="{{ route('statements.show', [$company, ...$criteria->toQuery()]) }}">Ekstreye dön</a>
    </div>

    <div class="head">
        <h1>{{ $company->name }}</h1>
        <p>
            Cari Ekstre ·
            @if ($criteria->dateFrom || $criteria->dateTo)
                {{ $criteria->dateFrom?->format('d.m.Y') ?? 'Başlangıç' }}
                – {{ $criteria->dateTo?->format('d.m.Y') ?? 'Bugün' }}
            @else
                Tüm hareketler
            @endif
            @if ($criteria->currency) · {{ $criteria->currency->value }} @endif
        </p>
    </div>

    @if ($statement->hasSummaries())
        <div class="summaries">
            @foreach ($statement->summaries as $summary)
                <div class="summary">
                    <h2>{{ $summary->currency->value }} — {{ $summary->currency->label() }}</h2>
                    <dl>
                        @if ($summary->hasCarryForward())
                            <div class="line">
                                <dt>Devreden</dt>
                                <dd class="num">{{ $summary->carryForward->format($summary->currency) }}</dd>
                            </div>
                        @endif
                        <div class="line">
                            <dt>Toplam borç</dt>
                            <dd class="num">{{ $summary->totalDebit->format($summary->currency) }}</dd>
                        </div>
                        <div class="line">
                            <dt>Toplam alacak</dt>
                            <dd class="num">{{ $summary->totalCredit->format($summary->currency) }}</dd>
                        </div>
                        <div class="line total">
                            <dt>Bakiye</dt>
                            <dd class="num">{{ $summary->closing->format($summary->currency) }}</dd>
                        </div>
                    </dl>
                    <p class="meaning">{{ $summary->balanceLabel() }}</p>
                </div>
            @endforeach
        </div>
    @endif

    @foreach ($statement->carryForwards() as $summary)
        <div class="carry">
            <span>Devreden bakiye ({{ $summary->currency->value }}) · {{ $criteria->dateFrom?->format('d.m.Y') }} öncesi</span>
            <span class="num">{{ $summary->carryForward->format($summary->currency) }}</span>
        </div>
    @endforeach

    <div class="scroll-x">
    <table>
        <thead>
            <tr>
                <th>Tarih</th>
                <th>İşlem</th>
                <th>Açıklama</th>
                <th class="num">Borç</th>
                <th class="num">Alacak</th>
                <th>PB</th>
                <th class="num">Bakiye</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($statement->rows as $row)
                <tr>
                    <td class="num">{{ $row->entry->entry_date->format('d.m.Y') }}</td>
                    <td @class(['rev' => $row->isReversal()])>{{ $row->type()->label() }}</td>
                    <td>{{ $row->entry->description }}</td>
                    <td class="num">
                        {{ $row->entry->debitMoney()->isZero() ? '' : $row->entry->debitMoney()->format($row->currency()) }}
                    </td>
                    <td class="num">
                        {{ $row->entry->creditMoney()->isZero() ? '' : $row->entry->creditMoney()->format($row->currency()) }}
                    </td>
                    <td>{{ $row->currency()->value }}</td>
                    <td class="num">{{ $row->runningBalance->format($row->currency()) }}</td>
                </tr>
            @empty
                <tr><td colspan="7">Bu aralıkta cari hareket bulunmuyor.</td></tr>
            @endforelse
        </tbody>
    </table>
    </div>

    <div class="foot">
        <span>{{ config('app.name') }}</span>
        <span>Yazdırma tarihi: {{ now()->format('d.m.Y H:i') }}</span>
    </div>
@endsection
