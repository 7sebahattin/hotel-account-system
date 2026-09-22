@extends('layouts.print')
@section('title', 'Cari Grafik')

@section('content')
    <style>
        @media print {
            @page { size: A4 landscape; }
        }

        .graphics-kpis { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 14px; }
        .graphics-kpi { border: 1px solid currentColor; padding: 7px 9px; min-width: 42mm; page-break-inside: avoid; }
        .graphics-kpi p { margin: 2px 0 0; }
        .graphics-kpi .value { font-size: 13pt; font-weight: 700; font-variant-numeric: tabular-nums; }
        .graphics-group { margin-top: 12px; page-break-inside: avoid; }
        .graphics-group-head { display: flex; justify-content: space-between; gap: 10px; align-items: baseline; }
        .graphics-bars { margin-top: 6px; }
        .graphics-bar-row { display: grid; grid-template-columns: 48mm minmax(50mm, 1fr) 35mm; gap: 6px; align-items: center; margin-top: 4px; }
        .graphics-bar-label { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .graphics-bar-track { height: 5mm; border: 1px solid currentColor; padding: 1px; }
        .graphics-bar-fill { height: 100%; background: currentColor; opacity: .35; }
        .graphics-bar-value { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .graphics-activity { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 7px; margin-top: 8px; }
        .graphics-activity-item { border: 1px solid currentColor; padding: 6px 7px; page-break-inside: avoid; }
        .graphics-activity-item strong { display: block; font-size: 12pt; font-variant-numeric: tabular-nums; }
        .graphics-table { margin-top: 8px; font-size: 9pt; }
        .graphics-table td, .graphics-table th { padding: 4px 5px; }
        .graphics-note { margin: 3px 0 0; font-size: 9pt; }
        .graphics-empty { border: 1px solid currentColor; padding: 12px; text-align: center; }
    </style>

    <div class="toolbar">
        <button type="button" onclick="window.print()">Yazdır</button>
        <a href="{{ route('statements.graphics', request()->only(['q', 'currency'])) }}">Grafiğe Dön</a>
    </div>

    <header class="head">
        <h1>Cari Grafik</h1>
        <p>LEMON HOTEL · Borcu bulunan carilerin para birimi bazında özeti</p>
        <p>Oluşturulma: {{ $generatedAt->format('d.m.Y H:i') }} · {{ $filterSummary }}</p>
    </header>

    @if ($uniqueDebtorCount === 0)
        <div class="graphics-empty">Bu filtrelerle borçlu cari bulunamadı.</div>
    @else
        <div class="graphics-kpis">
            <div class="graphics-kpi">
                <strong>Borçlu Cari</strong>
                <p class="value">{{ $uniqueDebtorCount }}</p>
                <p>Tekil cari</p>
            </div>

            @foreach ($currencyGroups as $group)
                <div class="graphics-kpi">
                    <strong>{{ $group['currency']->value }} · {{ $group['companyCount'] }} cari</strong>
                    <p class="value">{{ $group['total']->format($group['currency']) }}</p>
                    <p>Yalnızca {{ $group['currency']->value }} borcu</p>
                </div>
            @endforeach
        </div>

        <section class="print-section">
            <h2>Borçlu Cariler</h2>
            <p class="graphics-note">
                Çubuklar her para biriminin kendi en yüksek borcuna göre ölçeklenir; farklı para birimleri birleştirilmez.
            </p>

            @foreach ($currencyGroups as $group)
                <div class="graphics-group">
                    <div class="graphics-group-head">
                        <strong>{{ $group['currency']->value }} · {{ $group['currency']->label() }}</strong>
                        <span>{{ $group['companyCount'] }} cari · {{ $group['total']->format($group['currency']) }}</span>
                    </div>

                    <div class="graphics-bars">
                        @foreach ($group['rows'] as $row)
                            <div class="graphics-bar-row">
                                <div class="graphics-bar-label">{{ $row['company']->name }}</div>
                                <div class="graphics-bar-track">
                                    <div class="graphics-bar-fill" style="width: {{ $row['barPercent'] }}%"></div>
                                </div>
                                <div class="graphics-bar-value">{{ $row['balance']->format($group['currency']) }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </section>

        <section class="print-section">
            <h2>Son Hareket Dağılımı</h2>
            <p class="graphics-note">Son cari hareketinden bu yana geçen süreyi gösterir; vade bilgisi değildir.</p>

            <div class="graphics-activity">
                @foreach ($activityBuckets as $bucket)
                    <div class="graphics-activity-item">
                        <span>{{ $bucket['label'] }}</span>
                        <strong>{{ $bucket['count'] }} cari</strong>
                        <span>%{{ $bucket['percent'] }}</span>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="print-section">
            <h2>Borçlu Cari Detayı</h2>
            <table class="graphics-table">
                <thead>
                    <tr>
                        <th>Cari</th>
                        <th>Para Birimi</th>
                        <th class="num">Borç Bakiyesi</th>
                        <th>Son Hareket</th>
                        <th>Son Hareketten Bu Yana</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($detailRows as $row)
                        <tr>
                            <td>{{ $row['company']->name }}</td>
                            <td>{{ $row['currency']->value }}</td>
                            <td class="num">{{ $row['balance']->format($row['currency']) }}</td>
                            <td>{{ $row['lastDate'] ? \Illuminate\Support\Carbon::parse($row['lastDate'])->format('d.m.Y') : '—' }}</td>
                            <td>{{ $row['daysSince'] === null ? '—' : $row['daysSince'].' gün' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        <footer class="foot">
            <span>LEMON HOTEL · Cari Grafik</span>
            <span>Parasal toplamlar yalnızca kendi para birimi içindedir.</span>
        </footer>
    @endif
@endsection
