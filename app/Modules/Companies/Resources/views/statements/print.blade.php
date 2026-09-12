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
                @php $closingFx = $fx['closing'][$summary->currency->value] ?? null; @endphp
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
                            <dd class="num">
                                {{ $summary->closing->format($summary->currency) }}
                                @if ($closingFx && $closingFx['value'] !== null)
                                    <span class="fx">
                                        ≈ {{ $closingFx['value']->format(\App\Modules\Core\Enums\Currency::TRY) }}
                                    </span>
                                @elseif ($closingFx)
                                    <span class="fx incomplete">TL karşılığı eksik veri nedeniyle tam değil</span>
                                @endif
                            </dd>
                        </div>
                    </dl>
                    <p class="meaning">{{ $summary->balanceLabel($company->account_type->value === 'individual' ? 'Kişi' : 'Firma') }}</p>
                </div>
            @endforeach
        </div>
    @endif

    @foreach ($statement->carryForwards() as $summary)
        @php $carryFx = $fx['carryForward'][$summary->currency->value] ?? null; @endphp
        <div class="carry">
            <span>Devreden bakiye ({{ $summary->currency->value }}) · {{ $criteria->dateFrom?->format('d.m.Y') }} öncesi</span>
            <span class="num">
                {{ $summary->carryForward->format($summary->currency) }}
                @if ($carryFx)
                    <span class="fx @unless($carryFx['complete']) incomplete @endunless">
                        ≈ {{ $carryFx['value']->format(\App\Modules\Core\Enums\Currency::TRY) }}
                        @unless($carryFx['complete']) (eksik) @endunless
                    </span>
                @endif
            </span>
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
                @php $rowFx = $fx['byEntry'][$row->entry->id] ?? null; @endphp
                <tr>
                    <td class="num">{{ $row->entry->entry_date->format('d.m.Y') }}</td>
                    <td @class(['rev' => $row->isReversal()])>{{ $row->type()->label() }}</td>
                    <td>{{ $row->entry->description }}</td>
                    <td class="num">
                        @unless ($row->entry->debitMoney()->isZero())
                            {{ $row->entry->debitMoney()->format($row->currency()) }}
                            @if ($rowFx && $row->entry->isDebit() && $rowFx['equivalent'])
                                <span class="fx">≈ {{ $rowFx['equivalent']->format(\App\Modules\Core\Enums\Currency::TRY) }}</span>
                            @endif
                        @endunless
                    </td>
                    <td class="num">
                        @unless ($row->entry->creditMoney()->isZero())
                            {{ $row->entry->creditMoney()->format($row->currency()) }}
                            @if ($rowFx && ! $row->entry->isDebit() && $rowFx['equivalent'])
                                <span class="fx">≈ {{ $rowFx['equivalent']->format(\App\Modules\Core\Enums\Currency::TRY) }}</span>
                            @endif
                        @endunless
                    </td>
                    <td>{{ $row->currency()->value }}</td>
                    <td class="num">
                        {{ $row->runningBalance->format($row->currency()) }}
                        @if ($rowFx && $rowFx['runningTl'])
                            <span class="fx">≈ {{ $rowFx['runningTl']->format(\App\Modules\Core\Enums\Currency::TRY) }}</span>
                        @elseif ($rowFx && ! $rowFx['complete'])
                            <span class="fx incomplete">eksik</span>
                        @endif
                    </td>
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
