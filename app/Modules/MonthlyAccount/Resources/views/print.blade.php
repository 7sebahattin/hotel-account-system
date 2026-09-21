<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $month->translatedFormat('F Y') }} Aylık Hesap Raporu</title>
    <style>
        :root { color-scheme: light; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #fff; color: #18212b; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; font-size: 9pt; line-height: 1.35; }
        .toolbar { display: flex; gap: 8px; margin: 12px auto; max-width: 1100px; }
        .toolbar a, .toolbar button { min-height: 38px; border: 1px solid #67717c; border-radius: 4px; padding: 7px 12px; background: #fff; color: #18212b; cursor: pointer; font: inherit; text-decoration: none; }
        .report { max-width: 1100px; margin: 0 auto; }
        .report-head { border-bottom: 2px solid #354150; padding-bottom: 10px; }
        .brand { font-size: 10pt; font-weight: 750; letter-spacing: .12em; } .product { color: #4e5965; font-size: 8.5pt; }
        h1 { margin: 9px 0 10px; font-size: 17pt; letter-spacing: .035em; }
        .meta { display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px 14px; font-size: 8.5pt; } .meta dt { color: #59636e; } .meta dd { margin: 1px 0 0; font-weight: 650; }
        .summaries { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin: 12px 0 16px; }
        .summary { min-height: 61px; border: 1px solid #87919c; border-top: 3px solid #354150; padding: 8px 10px; } .summary dt { color: #4e5965; font-size: 8pt; font-weight: 700; letter-spacing: .04em; } .summary dd { margin: 6px 0 0; font-size: 13pt; font-weight: 750; font-variant-numeric: tabular-nums; }
        .section { break-before: page; page-break-before: always; } .section:first-of-type { break-before: auto; page-break-before: auto; }
        .section-title { display: flex; justify-content: space-between; align-items: baseline; border-bottom: 1px solid #77818c; margin: 0 0 7px; padding-bottom: 4px; } .section-title h2 { margin: 0; font-size: 10pt; } .section-title p { margin: 0; color: #59636e; font-size: 8pt; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 8.5pt; } caption { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0, 0, 0, 0); }
        thead { display: table-header-group; } th, td { border: 1px solid #aeb5bc; padding: 5px 4px; vertical-align: top; } th { background: #e8ebee; text-align: center; font-weight: 750; } .group-head { background: #dce1e6; } .day-col { width: 15%; text-align: left; } .daily-col, .receipt-col { width: 13%; } td { text-align: right; font-variant-numeric: tabular-nums; } tbody tr { break-inside: avoid; page-break-inside: avoid; }
        .total-row th, .total-row td { background: #e0e5e9; border-top: 2px solid #354150; font-weight: 750; } .date-sub { display: block; color: #59636e; font-size: 7.5pt; font-weight: 400; } .print-amount { white-space: nowrap; margin-bottom: 2px; } .print-amount:last-child { margin-bottom: 0; } .print-amount-primary { display: block; font-weight: 650; } .print-amount-fx { display: block; color: #59636e; font-size: 7.5pt; } .print-empty { color: #69737d; }
        .foot { display: flex; justify-content: space-between; gap: 12px; border-top: 1px solid #87919c; color: #59636e; font-size: 8pt; margin-top: 12px; padding-top: 5px; }
        @page { size: A4 landscape; margin: 9mm; }
        @media screen and (max-width: 760px) { body { padding: 10px; } .meta, .summaries { grid-template-columns: 1fr; } .report { overflow-x: auto; } table { min-width: 900px; } }
        @media print { body { padding: 0; } .toolbar { display: none; } .report { max-width: none; } }
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
        $sections = array_chunk($columns, 6);
        $recorded = $days->filter(fn ($day) => ! $day->isDeleted())->count();
        $storedDates = $days->keys()->sort()->values();
        $listedDayNumbers = $storedDates->isEmpty() ? [] : range((int) substr($storedDates->last(), 8, 2), (int) substr($storedDates->first(), 8, 2));
    @endphp
    <nav class="toolbar" aria-label="Rapor işlemleri"><a href="{{ route('monthly-account.index', ['owner_id' => $owner->id, 'month' => $month->format('Y-m')]) }}">← Aylık Hesaba Dön</a><button type="button" onclick="window.print()">Yazdır</button></nav>
    <main class="report">
        <header class="report-head"><div class="brand">LEMON</div><div class="product">Cari Takip</div><h1>AYLIK HESAP RAPORU</h1><dl class="meta"><div><dt>Dönem</dt><dd>{{ $month->translatedFormat('F Y') }}</dd></div><div><dt>Hesap sahibi</dt><dd>{{ $owner->name }}</dd></div><div><dt>Kayıtlı gün</dt><dd>{{ $recorded }}</dd></div><div><dt>Rapor tarihi</dt><dd>{{ $generatedAt->format('d.m.Y H:i') }}</dd></div><div><dt>Hazırlayan</dt><dd>{{ $preparedBy->name }}</dd></div></dl></header>
        <dl class="summaries"><div class="summary"><dt>GÜNLÜK LİSTE TOPLAMI</dt><dd>{{ $summary['daily_total']->format($try) }}</dd></div><div class="summary"><dt>ALINAN ÖDEMELER · TL KARŞILIĞI</dt><dd>{{ $summary['receipt_total']->format($try) }}</dd></div><div class="summary"><dt>LİSTEYE GÖRE FARK</dt><dd>{{ $summary['difference']->format($try) }}</dd></div></dl>
        @foreach ($sections as $sectionIndex => $sectionColumns)
            <section class="section"><div class="section-title"><h2>Ödeme ayrıntıları{{ count($sections) > 1 ? ' · Bölüm '.($sectionIndex + 1).' / '.count($sections) : '' }}</h2><p>{{ $month->translatedFormat('F Y') }} · {{ $owner->name }}</p></div><table><caption>{{ $month->translatedFormat('F Y') }} aylık hesap ödeme ayrıntıları</caption><thead><tr><th scope="col" rowspan="2" class="day-col">Gün</th><th scope="col" rowspan="2" class="daily-col">Günlük Liste<br>TL</th>@foreach (collect($sectionColumns)->groupBy('method') as $method => $methodColumns)<th scope="colgroup" colspan="{{ count($methodColumns) }}" class="group-head">{{ $methodLabels[$method] }}</th>@endforeach<th scope="col" rowspan="2" class="receipt-col">Ödemeler<br>TL Karşılığı</th></tr><tr>@foreach ($sectionColumns as $column)<th scope="col">{{ $column['bank'] ?? $column['label'] }}</th>@endforeach</tr></thead><tbody><tr class="total-row"><th scope="row">AY TOPLAMI</th><td>{{ $summary['daily_total']->format($try) }}</td>@foreach ($sectionColumns as $column)@php($amounts = $column['bank'] ? ($summary['banks'][$column['method']][$column['bank']] ?? []) : ($summary['methods'][$column['method']] ?? []))<td>@include('monthly-account::_print_amounts', ['amounts' => $amounts])</td>@endforeach<td>{{ $summary['receipt_total']->format($try) }}</td></tr>@forelse ($listedDayNumbers as $number)@php($date = $month->setDay($number))@php($day = $days->get($date->toDateString()))@php($daySummary = $day && ! $day->isDeleted() ? $day->currentRevision->summary() : null)<tr><th scope="row" class="day-col">{{ $date->translatedFormat('d F') }}<span class="date-sub">{{ $date->translatedFormat('l') }}@if ($day?->isDeleted()) · Silindi @endif</span></th><td>{{ $daySummary ? $daySummary['daily_total']->format($try) : '—' }}</td>@foreach ($sectionColumns as $column)@php($amounts = $column['bank'] ? ($daySummary['banks'][$column['method']][$column['bank']] ?? []) : ($daySummary['methods'][$column['method']] ?? []))<td>@include('monthly-account::_print_amounts', ['amounts' => $amounts])</td>@endforeach<td>{{ $daySummary ? $daySummary['receipt_total']->format($try) : '—' }}</td></tr>@empty<tr><td colspan="{{ count($sectionColumns) + 3 }}" style="text-align:center; padding:18px;">Bu ay için kayıt yok.</td></tr>@endforelse</tbody></table></section>
        @endforeach
        <footer class="foot"><span>Lemon Cari Takip · {{ $month->translatedFormat('F Y') }} Aylık Hesap Raporu</span><span>Oluşturulma: {{ $generatedAt->format('d.m.Y H:i') }}</span></footer>
    </main>
</body>
</html>
