<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cari Ekstre Bakiye Listesi</title>
    <style>
        :root {
            color-scheme: light;
            print-color-adjust: exact;
            -webkit-print-color-adjust: exact;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: #fff;
            color: #18212b;
            font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
            font-size: 9pt;
            line-height: 1.35;
        }

        .toolbar {
            display: flex;
            gap: 8px;
            max-width: 900px;
            margin: 12px auto;
        }

        .toolbar a,
        .toolbar button {
            min-height: 38px;
            border: 1px solid #67717c;
            border-radius: 4px;
            padding: 7px 12px;
            background: #fff;
            color: #18212b;
            cursor: pointer;
            font: inherit;
            text-decoration: none;
        }

        .report {
            max-width: 900px;
            margin: 0 auto;
        }

        .report-head {
            border-bottom: 2px solid #354150;
            padding-bottom: 10px;
        }

        .brand {
            font-size: 10pt;
            font-weight: 750;
            letter-spacing: .12em;
        }

        .product {
            color: #4e5965;
            font-size: 8.5pt;
        }

        h1 {
            margin: 9px 0 10px;
            font-size: 16pt;
            letter-spacing: .025em;
        }

        .meta {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px 18px;
            margin: 0;
            font-size: 8.5pt;
        }

        .meta dt { color: #59636e; }

        .meta dd {
            margin: 1px 0 0;
            font-weight: 650;
        }

        .filters {
            margin: 10px 0 12px;
            border: 1px solid #aeb5bc;
            background: #f5f6f7;
            padding: 7px 9px;
            font-size: 8.5pt;
        }

        .filters strong { margin-right: 5px; }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 9pt;
        }

        col.company-column { width: 34%; }
        col.balance-column { width: 46%; }
        col.last-column { width: 20%; }

        thead { display: table-header-group; }

        th,
        td {
            border: 1px solid #aeb5bc;
            padding: 6px 8px;
            vertical-align: top;
        }

        th {
            background: #e4e8ec;
            text-align: left;
            font-weight: 750;
        }

        tbody tr {
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .company {
            font-weight: 650;
        }

        .balance-list {
            display: flex;
            flex-wrap: wrap;
            gap: 4px 14px;
        }

        .balance {
            min-width: 0;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }

        .balance-main { font-weight: 650; }

        .balance-status {
            margin-left: 3px;
            color: #59636e;
            font-size: 8pt;
        }

        .balance-fx {
            display: block;
            margin-top: 1px;
            color: #59636e;
            font-size: 8pt;
            font-weight: 400;
        }

        .balance-note {
            display: block;
            color: #59636e;
            font-size: 7.5pt;
        }

        .muted { color: #59636e; }

        .date {
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }

        .empty {
            padding: 18px;
            text-align: center;
            color: #59636e;
        }

        .foot {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            border-top: 1px solid #87919c;
            margin-top: 10px;
            padding-top: 5px;
            color: #59636e;
            font-size: 7.5pt;
        }

        @page {
            size: A4 portrait;
            margin: 9mm;
        }

        @media screen and (max-width: 760px) {
            body { padding: 10px; }
            .meta { grid-template-columns: 1fr; }
            .report { overflow-x: auto; }
            table { min-width: 680px; }
        }

        @media print {
            body { padding: 0; }
            .toolbar { display: none; }
            .report { max-width: none; }
        }
    </style>
</head>
<body>
    @php
        $try = \App\Modules\Core\Enums\Currency::TRY;
        $search = trim((string) request('q', ''));
        $effectiveSort = match ($balanceSort) {
            'highest' => 'En yüksek bakiye',
            'lowest' => 'En düşük bakiye',
            default => 'Firma adı',
        };
    @endphp

    <nav class="toolbar" aria-label="Rapor işlemleri">
        <a href="{{ route('statements.index', request()->except(['page', 'per_page'])) }}">← Cari Ekstreye Dön</a>
        <button type="button" onclick="window.print()">Yazdır</button>
    </nav>

    <main class="report">
        <header class="report-head">
            <div class="brand">LEMON</div>
            <div class="product">Cari Takip</div>
            <h1>CARİ EKSTRE · BAKİYE LİSTESİ</h1>

            <dl class="meta">
                <div>
                    <dt>Firma sayısı</dt>
                    <dd>{{ $companies->count() }}</dd>
                </div>
                <div>
                    <dt>Rapor tarihi</dt>
                    <dd>{{ $generatedAt->format('d.m.Y H:i') }}</dd>
                </div>
                <div>
                    <dt>Sıralama</dt>
                    <dd>{{ $effectiveSort }}</dd>
                </div>
            </dl>
        </header>

        <div class="filters">
            <strong>Filtreler:</strong>
            @if ($search !== '')
                Firma arama: “{{ $search }}” ·
            @endif
            Para birimi: {{ $balanceCurrency?->value ?? 'Tümü' }} ·
            Bakiyesi olanlar: {{ $hasBalanceOnly ? 'Evet' : 'Tümü' }}
        </div>

        <table>
            <caption style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);">
                Cari ekstre firma bakiye listesi
            </caption>
            <colgroup>
                <col class="company-column">
                <col class="balance-column">
                <col class="last-column">
            </colgroup>
            <thead>
                <tr>
                    <th scope="col">Firma</th>
                    <th scope="col">Bakiye</th>
                    <th scope="col">Son hareket</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($companies as $company)
                    @php
                        $companyBalances = $balances[$company->id] ?? [];
                        $companyFx = $fxTotals[$company->id] ?? [];
                    @endphp
                    <tr>
                        <td class="company">{{ $company->name }}</td>
                        <td>
                            <div class="balance-list">
                                @forelse ($companyBalances as $code => $balance)
                                    @php
                                        $currency = \App\Modules\Core\Enums\Currency::from($code);
                                        $balanceFx = $companyFx[$code] ?? null;
                                    @endphp
                                    <div class="balance">
                                        <span class="balance-main">{{ $balance->format($currency) }}</span>
                                        <span class="balance-status">
                                            @if ($balance->isPositive()) borçlu
                                            @elseif ($balance->isNegative()) alacaklı
                                            @else kapalı
                                            @endif
                                        </span>
                                        @if ($currency !== $try && $balanceFx)
                                            <span class="balance-fx">≈ {{ $balanceFx['net']->format($try) }}</span>
                                            @if (! $balanceFx['complete'])
                                                <span class="balance-note">TL karşılığı eksik veri</span>
                                            @endif
                                        @endif
                                    </div>
                                @empty
                                    <span class="muted">Hareket yok</span>
                                @endforelse
                            </div>
                        </td>
                        <td class="date muted">
                            {{ isset($lastDates[$company->id])
                                ? \Illuminate\Support\Carbon::parse($lastDates[$company->id])->format('d.m.Y')
                                : '—' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="empty">Bu filtrelerle eşleşen firma bulunamadı.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <footer class="foot">
            <span>Lemon Cari Takip · Cari Ekstre Bakiye Listesi</span>
            <span>Oluşturulma: {{ $generatedAt->format('d.m.Y H:i') }}</span>
        </footer>
    </main>
@include('partials.pdf-download')
</body>
</html>
