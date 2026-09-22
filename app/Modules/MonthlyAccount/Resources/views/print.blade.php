<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $month->translatedFormat('F Y') }} Aylık Hesap Raporu</title>
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
            max-width: 1100px;
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
            max-width: 1100px;
            margin: 0 auto;
        }

        .report-head {
            border-bottom: 2px solid #354150;
            padding-bottom: 10px;
        }

        .report-title-row {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: center;
            gap: 12px;
            margin-bottom: 10px;
        }

        .brand-lockup {
            justify-self: start;
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
            grid-column: 2;
            margin: 0;
            text-align: center;
            font-size: 17pt;
            letter-spacing: .035em;
            white-space: nowrap;
        }

        .meta {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 8px 14px;
            font-size: 8.5pt;
        }

        .meta dt { color: #59636e; }

        .meta dd {
            margin: 1px 0 0;
            font-weight: 650;
        }

        .summaries {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin: 12px 0 16px;
        }

        .summary {
            min-height: 61px;
            border: 1px solid #87919c;
            border-top: 3px solid #354150;
            padding: 8px 10px;
        }

        .summary-daily {
            background: rgb(248 251 255);
            border-top-color: rgb(34 48 74);
        }

        .summary-receipt {
            background: rgb(246 253 248);
            border-top-color: rgb(21 128 61);
        }

        .summary-difference {
            background: rgb(255 250 243);
            border-top-color: rgb(138 106 47);
        }

        .summary-receipt dd {
            color: rgb(21 128 61);
        }

        .summary-difference dd {
            color: rgb(180 83 9);
        }

        .summary dt {
            color: #4e5965;
            font-size: 8pt;
            font-weight: 700;
            letter-spacing: .04em;
        }

        .summary dd {
            margin: 6px 0 0;
            font-size: 13pt;
            font-weight: 750;
            font-variant-numeric: tabular-nums;
        }

        .print-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: auto;
            font-family: "Aptos Narrow", "Arial Narrow", system-ui, -apple-system, "Segoe UI", sans-serif;
            font-size: 9pt;
        }

        .print-table.print-cols-dense { font-size: 8.5pt; }
        .print-table.print-cols-very-dense { font-size: 8pt; }

        .print-table caption {
            position: absolute;
            width: 1px;
            height: 1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
        }

        .print-table col.day-column { width: 16mm; }
        .print-table col.daily-total-column { width: 23mm; }
        .print-table col.receipt-total-column { width: 36mm; }

        .print-table thead { display: table-header-group; }

        .print-table th,
        .print-table td {
            border: 1px solid #aeb5bc;
            padding: 1.4mm 1mm;
            vertical-align: top;
            overflow: hidden;
            overflow-wrap: normal;
            word-break: normal;
        }

        .print-table.print-cols-dense th,
        .print-table.print-cols-dense td {
            padding: 1.2mm .8mm;
        }

        .print-table.print-cols-very-dense th,
        .print-table.print-cols-very-dense td {
            padding: .9mm .45mm;
        }

        .print-table th {
            background: #e8ebee;
            text-align: center;
            font-weight: 750;
            line-height: 1.18;
            white-space: normal;
        }

        .print-table thead th {
            vertical-align: middle;
        }

        .print-table td {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        .print-table tbody tr {
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .group-head {
            background: #dce1e6;
            font-size: 8pt;
            letter-spacing: .025em;
            text-transform: uppercase;
        }

        .print-table th.group-head-credit-card {
            background: rgb(183 228 199);
            color: #18212b;
        }

        .print-table th.group-head-link {
            background: rgb(247 200 145);
            color: #18212b;
        }

        .payment-head {
            font-weight: 650;
        }

        .provider-name {
            display: block;
            overflow-wrap: normal;
            word-break: normal;
        }

        .currency-line {
            display: block;
            margin-top: .55mm;
            color: #4e5965;
            font-size: 8pt;
            font-weight: 600;
            line-height: 1.15;
        }

        .currency-token {
            display: block;
            white-space: nowrap;
        }

        .column-placeholder { color: #69737d; }

        .day-col { text-align: left; }

        .date-main {
            display: block;
            white-space: nowrap;
        }

        .date-sub {
            display: block;
            margin-top: .35mm;
            color: #59636e;
            font-size: 8pt;
            font-weight: 400;
            white-space: normal;
        }

        .money-cell {
            text-align: right;
            font-variant-numeric: tabular-nums;
            overflow-wrap: normal;
            word-break: normal;
        }

        .money-nowrap,
        .print-amount-primary,
        .print-amount-fx {
            display: block;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
            overflow-wrap: normal;
            word-break: normal;
        }

        .print-amount { margin-bottom: .8mm; }
        .print-amount:last-child { margin-bottom: 0; }
        .print-amount-primary { font-weight: 650; }

        .print-amount-fx {
            margin-top: .2mm;
            color: #59636e;
            font-size: 8pt;
            font-weight: 400;
        }

        .print-empty { color: #69737d; }

        .receipt-total-cell {
            min-width: 34mm;
        }

        .receipt-total-primary {
            font-weight: 750;
        }

        .receipt-breakdown {
            margin-top: .8mm;
            padding-top: .65mm;
            border-top: 1px solid rgb(203 213 225);
            color: rgb(71 85 105);
            font-size: 7.2pt;
            font-weight: 500;
            line-height: 1.25;
            text-align: left;
        }

        .receipt-breakdown-line {
            display: flex;
            justify-content: space-between;
            gap: 1.2mm;
        }

        .receipt-breakdown-label {
            white-space: nowrap;
        }

        .receipt-breakdown-value {
            margin-left: auto;
            white-space: nowrap;
            font-weight: 650;
            font-variant-numeric: tabular-nums;
        }

        .total-row th,
        .total-row td {
            background: #e0e5e9;
            border-top: 2px solid #354150;
            border-bottom: 2px solid #354150;
            font-weight: 750;
        }

        .empty-row {
            padding: 5mm;
            text-align: center;
        }

        .foot {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            border-top: 1px solid #87919c;
            margin-top: 12px;
            padding-top: 5px;
            color: #59636e;
            font-size: 8pt;
        }

        @page {
            size: A4 landscape;
            margin: 6mm;
        }

        @media screen and (max-width: 760px) {
            body { padding: 10px; }
            .meta, .summaries { grid-template-columns: 1fr; }
            .report { overflow-x: auto; }
            .print-table { min-width: 900px; }
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
        $methodLabels = ['cash' => 'Nakit', 'credit_card' => 'Kredi Kartı', 'link' => 'Link Ödeme', 'bank_transfer' => 'Havale', 'extra' => 'Ekstra'];
        $bankMethods = ['credit_card', 'link', 'bank_transfer'];
        $columns = [['method' => 'cash', 'bank' => null, 'label' => $methodLabels['cash']]];
        foreach ($bankMethods as $method) {
            $banks = collect(array_keys($summary['banks'][$method] ?? []))->sortBy(fn (string $bank): string => mb_strtolower($bank, 'UTF-8'))->values();
            if ($banks->isEmpty()) { $columns[] = ['method' => $method, 'bank' => null, 'label' => $methodLabels[$method]]; continue; }
            foreach ($banks as $bank) { $columns[] = ['method' => $method, 'bank' => $bank, 'label' => $methodLabels[$method]]; }
        }
        $columns[] = ['method' => 'extra', 'bank' => null, 'label' => $methodLabels['extra']];
        $currencyCases = collect(\App\Modules\Core\Enums\Currency::cases());
        $currencyOrder = $currencyCases
            ->mapWithKeys(fn ($currency, int $index): array => [$currency->value => $index]);
        $currencyTokens = $currencyCases
            ->flatMap(fn ($currency): array => [$currency->value, $currency->symbol()])
            ->map(fn (string $token): string => mb_strtoupper(trim($token), 'UTF-8'))
            ->unique()
            ->values()
            ->all();
        $methodTokens = [
            'credit_card' => ['POS'],
            'link' => ['LİNK ÖDEME', 'LINK ÖDEME'],
            'bank_transfer' => ['HAVALE'],
        ];
        $receiptBreakdownMethods = [
            'credit_card' => 'Kredi Kartı',
            'link' => 'Link Ödeme',
            'bank_transfer' => 'Havale',
        ];
        $methodTryTotal = static function (array $accountSummary, string $method) {
            $total = \App\Modules\Core\Support\Money::zero();
            foreach (($accountSummary['methods'][$method] ?? []) as $amount) {
                $total = $total->add($amount['try_equivalent']);
            }

            return $total;
        };
        $columns = collect($columns)->map(function (array $column) use ($currencyOrder, $currencyTokens, $methodTokens, $summary): array {
            $amounts = $column['bank']
                ? ($summary['banks'][$column['method']][$column['bank']] ?? [])
                : ($summary['methods'][$column['method']] ?? []);
            $column['currencies'] = collect(array_keys($amounts))
                ->sortBy(fn (string $code): int => $currencyOrder->get($code, PHP_INT_MAX))
                ->values()
                ->all();
            $column['header_label'] = null;

            if ($column['bank']) {
                $ignoredMethodTokens = collect($methodTokens[$column['method']] ?? [])
                    ->map(fn (string $token): string => mb_strtoupper(trim($token), 'UTF-8'))
                    ->all();
                $parts = preg_split('/\s*-\s*/u', $column['bank']) ?: [$column['bank']];
                $providerParts = array_values(array_filter($parts, function (string $part) use ($ignoredMethodTokens, $currencyTokens): bool {
                    $normalized = mb_strtoupper(trim($part), 'UTF-8');

                    return $normalized !== ''
                        && ! in_array($normalized, $ignoredMethodTokens, true)
                        && ! in_array($normalized, $currencyTokens, true);
                }));
                $provider = trim(implode(' - ', $providerParts));
                $currencySuffix = collect($column['currencies'])
                    ->map(function (string $code): string {
                        $currency = \App\Modules\Core\Enums\Currency::from($code);

                        return $currency->symbol() === $code
                            ? $code
                            : $code.' - '.$currency->symbol();
                    })
                    ->implode(' / ');

                $column['header_label'] = trim($provider.($currencySuffix !== '' ? ' - '.$currencySuffix : ''));
            }

            return $column;
        })->all();
        $columnCount = count($columns) + 3;
        $densityClass = match (true) {
            $columnCount <= 10 => 'print-cols-normal',
            $columnCount <= 13 => 'print-cols-dense',
            default => 'print-cols-very-dense',
        };
        $recorded = $days->filter(fn ($day) => ! $day->isDeleted())->count();
        $storedDates = $days->keys()->sort()->values();
        $listedDayNumbers = $storedDates->isEmpty() ? [] : range((int) substr($storedDates->first(), 8, 2), (int) substr($storedDates->last(), 8, 2));
    @endphp
    <nav class="toolbar" aria-label="Rapor işlemleri"><a href="{{ route('monthly-account.index', ['owner_id' => $owner->id, 'month' => $month->format('Y-m')]) }}">← Aylık Hesaba Dön</a><button type="button" onclick="window.print()">Yazdır</button></nav>
    <main class="report">
        <header class="report-head">
            <div class="report-title-row">
                <div class="brand-lockup"><div class="brand">LEMON</div><div class="product">Cari Takip</div></div>
                <h1>AYLIK HESAP RAPORU</h1>
                <div aria-hidden="true"></div>
            </div>
            <dl class="meta"><div><dt>Dönem</dt><dd>{{ $month->translatedFormat('F Y') }}</dd></div><div><dt>Hesap sahibi</dt><dd>{{ $owner->name }}</dd></div><div><dt>Kayıtlı gün</dt><dd>{{ $recorded }}</dd></div><div><dt>Rapor tarihi</dt><dd>{{ $generatedAt->format('d.m.Y H:i') }}</dd></div><div><dt>Hazırlayan</dt><dd>{{ $preparedBy->name }}</dd></div></dl>
        </header>
        <dl class="summaries"><div class="summary summary-daily"><dt>GÜNLÜK LİSTE TOPLAMI</dt><dd>{{ $summary['daily_total']->format($try) }}</dd></div><div class="summary summary-receipt"><dt>ALINAN ÖDEMELER · TL KARŞILIĞI</dt><dd>{{ $summary['receipt_total']->format($try) }}</dd></div><div class="summary summary-difference"><dt>LİSTEYE GÖRE FARK</dt><dd>{{ $summary['difference']->format($try) }}</dd></div></dl>
        <table class="print-table {{ $densityClass }}" data-column-count="{{ $columnCount }}">
            <caption>{{ $month->translatedFormat('F Y') }} aylık hesap ödeme ayrıntıları</caption>
            <colgroup>
                <col class="day-column">
                <col class="daily-total-column">
                @foreach ($columns as $column)
                    <col class="payment-column">
                @endforeach
                <col class="receipt-total-column">
            </colgroup>
            <thead>
                <tr>
                    <th scope="col" rowspan="2" class="day-col">Gün</th>
                    <th scope="col" rowspan="2">Günlük Liste<br>TL</th>
                    @foreach (collect($columns)->groupBy('method') as $method => $methodColumns)
                        <th scope="colgroup" colspan="{{ count($methodColumns) }}" class="group-head @if ($method === 'credit_card') group-head-credit-card @elseif ($method === 'link') group-head-link @endif">{{ $methodLabels[$method] }}</th>
                    @endforeach
                    <th scope="col" rowspan="2">Ödemeler<br>TL Karşılığı</th>
                </tr>
                <tr class="payment-headers">
                    @foreach ($columns as $column)
                        <th scope="col" class="payment-head">
                            @if ($column['bank'])
                                <span class="provider-name">{{ $column['header_label'] }}</span>
                            @elseif ($column['currencies'] !== [])
                                <span class="currency-line">
                                    @foreach ($column['currencies'] as $code)
                                        @php($currency = \App\Modules\Core\Enums\Currency::from($code))
                                        <span class="currency-token">{{ $code }}@if ($currency->symbol() !== $code) · {{ $currency->symbol() }}@endif</span>
                                    @endforeach
                                </span>
                            @else
                                <span class="column-placeholder" aria-hidden="true">—</span>
                            @endif
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($listedDayNumbers as $number)
                    @php($date = $month->setDay($number))
                    @php($day = $days->get($date->toDateString()))
                    @php($daySummary = $day && ! $day->isDeleted() ? $day->currentRevision->summary() : null)
                    <tr>
                        <th scope="row" class="day-col"><span class="date-main">{{ $date->translatedFormat('d F') }}</span><span class="date-sub">{{ $date->translatedFormat('l') }}@if ($day?->isDeleted()) · Silindi @endif</span></th>
                        <td class="money-cell"><span class="money-nowrap">{{ $daySummary ? $daySummary['daily_total']->format($try) : '—' }}</span></td>
                        @foreach ($columns as $column)
                            @php($amounts = $column['bank'] ? ($daySummary['banks'][$column['method']][$column['bank']] ?? []) : ($daySummary['methods'][$column['method']] ?? []))
                            <td class="money-cell">@include('monthly-account::_print_amounts', ['amounts' => $amounts])</td>
                        @endforeach
                        <td class="money-cell receipt-total-cell">
                            <span class="money-nowrap receipt-total-primary">{{ $daySummary ? $daySummary['receipt_total']->format($try) : '—' }}</span>
                            @if ($daySummary)
                                <div class="receipt-breakdown">
                                    @foreach ($receiptBreakdownMethods as $method => $label)
                                        @php($methodTotal = $methodTryTotal($daySummary, $method))
                                        <div class="receipt-breakdown-line"><span class="receipt-breakdown-label">{{ $label }}:</span><span class="receipt-breakdown-value">{{ $methodTotal->format($try) }}</span></div>
                                    @endforeach
                                    @php($extraTotal = $methodTryTotal($daySummary, 'extra'))
                                    @if (! $extraTotal->isZero())
                                        <div class="receipt-breakdown-line"><span class="receipt-breakdown-label">Ekstra:</span><span class="receipt-breakdown-value">{{ $extraTotal->format($try) }}</span></div>
                                    @endif
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ count($columns) + 3 }}" class="empty-row">Bu ay için kayıt yok.</td></tr>
                @endforelse
                <tr class="total-row">
                    <th scope="row" class="day-col">AY TOPLAMI</th>
                    <td class="money-cell"><span class="money-nowrap">{{ $summary['daily_total']->format($try) }}</span></td>
                    @foreach ($columns as $column)
                        @php($amounts = $column['bank'] ? ($summary['banks'][$column['method']][$column['bank']] ?? []) : ($summary['methods'][$column['method']] ?? []))
                        <td class="money-cell">@include('monthly-account::_print_amounts', ['amounts' => $amounts])</td>
                    @endforeach
                    <td class="money-cell receipt-total-cell">
                        <span class="money-nowrap receipt-total-primary">{{ $summary['receipt_total']->format($try) }}</span>
                        <div class="receipt-breakdown">
                            @foreach ($receiptBreakdownMethods as $method => $label)
                                @php($methodTotal = $methodTryTotal($summary, $method))
                                <div class="receipt-breakdown-line"><span class="receipt-breakdown-label">{{ $label }}:</span><span class="receipt-breakdown-value">{{ $methodTotal->format($try) }}</span></div>
                            @endforeach
                            @php($extraTotal = $methodTryTotal($summary, 'extra'))
                            @if (! $extraTotal->isZero())
                                <div class="receipt-breakdown-line"><span class="receipt-breakdown-label">Ekstra:</span><span class="receipt-breakdown-value">{{ $extraTotal->format($try) }}</span></div>
                            @endif
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
        <footer class="foot"><span>Lemon Cari Takip · {{ $month->translatedFormat('F Y') }} Aylık Hesap Raporu</span><span>Oluşturulma: {{ $generatedAt->format('d.m.Y H:i') }}</span></footer>
    </main>
</body>
</html>
