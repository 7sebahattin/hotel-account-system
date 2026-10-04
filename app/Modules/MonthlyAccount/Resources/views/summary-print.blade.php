<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $month->translatedFormat('F Y') }} Aylık Hesap Özeti</title>
    <style>
        :root { color-scheme: light; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #f3f5f6; color: #18212b; font: 9pt/1.13 system-ui, -apple-system, "Segoe UI", sans-serif; }
        .toolbar { display: flex; flex-wrap: wrap; gap: 8px; max-width: 210mm; margin: 12px auto; }
        .toolbar a, .toolbar button { min-height: 44px; border: 1px solid #67717c; border-radius: 4px; padding: 7px 12px; background: #fff; color: #18212b; cursor: pointer; font: inherit; text-decoration: none; }
        .report { width: min(210mm, 100%); min-height: 297mm; margin: 0 auto 16px; padding: 11mm 12mm; background: #fff; box-shadow: 0 2px 14px #18212b1a; }
        .report-head { padding-bottom: 2.5mm; border-bottom: 1px solid #b8c0c7; }
        .title-row { display: grid; grid-template-columns: auto 1fr; align-items: baseline; gap: 5mm; }
        .brand { font-size: 9pt; font-weight: 750; letter-spacing: .1em; }
        h1 { margin: 0; font-size: 12pt; letter-spacing: .035em; text-align: right; white-space: nowrap; }
        .subtitle { margin: 1.5mm 0 0; color: #59636e; font-size: 9pt; }
        .totals { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); align-items: start; gap: 6mm; margin-top: 2mm; }
        .summary-column { min-width: 0; }
        .group, .receipt-section { margin-top: 3mm; break-inside: avoid; page-break-inside: avoid; }
        .group-title { margin: 0; padding: 0 0 1mm; border-bottom: 1px solid #b8c0c7; color: #354150; font-size: 8.5pt; font-weight: 750; letter-spacing: .045em; text-transform: uppercase; break-after: avoid; page-break-after: avoid; }
        .amount-row { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 1.5mm; align-items: baseline; padding: .6mm 0; border-bottom: 1px solid #e6e9ec; break-inside: avoid; page-break-inside: avoid; }
        .amount-label { min-width: 0; overflow-wrap: anywhere; }
        .amount-value { text-align: right; font-weight: 650; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .amount-fx { display: block; margin-top: 0; color: #59636e; font-size: 7.5pt; font-weight: 400; line-height: 1.05; }
        .empty { color: #69737d; }
        .receipt-breakdown-line { display: flex; justify-content: space-between; gap: 1.5mm; padding: .6mm 0; break-inside: avoid; page-break-inside: avoid; }
        .receipt-breakdown-value { margin-left: auto; font-weight: 650; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .receipt-breakdown-total { margin-top: 1mm; padding-top: 1mm; border-top: 1px solid #354150; font-weight: 750; }
        @page { size: A4 portrait; margin: 8mm; }
        @media screen and (max-width: 760px) {
            body { padding: 10px; }
            .report { min-height: 0; padding: 8mm; }
            .title-row { grid-template-columns: 1fr; gap: 2mm; }
            h1 { text-align: left; white-space: normal; }
            .totals { grid-template-columns: 1fr; gap: 0; }
        }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .report { width: auto; min-height: 0; margin: 0; padding: 0; box-shadow: none; }
        }
    </style>
</head>
<body>
    @php
        $try = \App\Modules\Core\Enums\Currency::TRY;
        $currencyOrder = collect(\App\Modules\Core\Enums\Currency::cases())
            ->mapWithKeys(fn ($currency, int $index): array => [$currency->value => $index]);
        $currencyTokens = collect(\App\Modules\Core\Enums\Currency::cases())
            ->flatMap(fn ($currency): array => [$currency->value, $currency->symbol()])
            ->map(fn (string $token): string => mb_strtoupper(trim($token), 'UTF-8'))
            ->unique()->values()->all();
        $methodTokens = [
            'credit_card' => ['POS'],
            'link' => ['LİNK ÖDEME', 'LINK ÖDEME'],
            'bank_transfer' => ['HAVALE'],
        ];
        $methodLabels = ['cash' => 'Nakit', 'credit_card' => 'Kredi Kartı', 'link' => 'Link Ödeme', 'bank_transfer' => 'Havale', 'extra' => 'Ekstra'];
        $columnMethods = [
            'left' => ['cash', 'credit_card'],
            'right' => ['link', 'bank_transfer', 'extra'],
        ];
    @endphp
    <nav class="toolbar" aria-label="Rapor işlemleri">
        <a href="{{ route('monthly-account.print', ['owner_id' => $owner->id, 'month' => $month->format('Y-m')]) }}">← Aylık Rapora Dön</a>
        <button type="button" onclick="window.print()">Yazdır</button>
    </nav>
    <main class="report" data-monthly-summary-print>
        <header class="report-head">
            <div class="title-row">
                <div class="brand">LEMON</div>
                <h1>AYLIK HESAP ÖZETİ</h1>
            </div>
            <p class="subtitle">{{ $month->translatedFormat('F Y') }} · {{ $owner->name }}</p>
        </header>

        <section class="totals" aria-label="Aylık sütun toplamları">
            @foreach ($columnMethods as $column => $methods)
            <div class="summary-column" data-summary-column="{{ $column }}">
            @if ($column === 'left')
            <section class="group" aria-labelledby="daily-title">
                <h3 class="group-title" id="daily-title">Günlük Liste</h3>
                <div class="amount-row" data-summary-daily-total>
                    <span class="amount-label">Günlük Liste TL</span>
                    <span class="amount-value">{{ $summary['daily_total']->format($try) }}</span>
                </div>
            </section>
            @endif
            @foreach (\App\Modules\MonthlyAccount\Enums\MonthlyPaymentMethod::cases() as $method)
                @php
                    $methodKey = $method->value;
                @endphp
                @continue(! in_array($methodKey, $methods, true))
                <section class="group" aria-labelledby="method-{{ $methodKey }}">
                    <h3 class="group-title" id="method-{{ $methodKey }}">{{ $methodLabels[$methodKey] }}</h3>
                    @if ($method->requiresBank())
                        @php
                            $banks = collect($summary['banks'][$methodKey] ?? [])
                                ->sortKeysUsing(fn (string $a, string $b): int => mb_strtolower($a, 'UTF-8') <=> mb_strtolower($b, 'UTF-8'));
                        @endphp
                        @if ($banks->isEmpty())
                            <div class="amount-row"><span class="empty">—</span></div>
                        @endif
                        @foreach ($banks as $bank => $amounts)
                            @php
                                $ignoredMethodTokens = collect($methodTokens[$methodKey] ?? [])
                                    ->map(fn (string $token): string => mb_strtoupper(trim($token), 'UTF-8'))->all();
                                $parts = preg_split('/\s*-\s*/u', $bank) ?: [$bank];
                                $providerParts = array_values(array_filter($parts, function (string $part) use ($ignoredMethodTokens, $currencyTokens): bool {
                                    $normalized = mb_strtoupper(trim($part), 'UTF-8');

                                    return $normalized !== ''
                                        && ! in_array($normalized, $ignoredMethodTokens, true)
                                        && ! in_array($normalized, $currencyTokens, true);
                                }));
                                $provider = trim(implode(' - ', $providerParts));
                            @endphp
                            @php
                                $sortedAmounts = collect($amounts)
                                    ->sortKeysUsing(fn (string $a, string $b): int => $currencyOrder->get($a, PHP_INT_MAX) <=> $currencyOrder->get($b, PHP_INT_MAX));
                            @endphp
                            @foreach ($sortedAmounts as $code => $amount)
                                @php
                                    $currency = \App\Modules\Core\Enums\Currency::from($code);
                                @endphp
                                <div class="amount-row" data-summary-method="{{ $methodKey }}" data-summary-bank="{{ $bank }}" data-summary-currency="{{ $code }}">
                                    <span class="amount-label">{{ $provider !== '' ? $provider.' - ' : '' }}{{ $code }}</span>
                                    <span class="amount-value">{{ $amount['amount']->format($currency) }}{{ $code !== 'TRY' ? ' '.$code : '' }}
                                        @if ($code !== 'TRY')
                                            <span class="amount-fx">≈ {{ $amount['try_equivalent']->format($try) }}</span>
                                        @endif
                                    </span>
                                </div>
                            @endforeach
                        @endforeach
                    @else
                        @php
                            $methodAmounts = collect($summary['methods'][$methodKey] ?? [])
                                ->sortKeysUsing(fn (string $a, string $b): int => $currencyOrder->get($a, PHP_INT_MAX) <=> $currencyOrder->get($b, PHP_INT_MAX));
                        @endphp
                        @if ($methodAmounts->isEmpty())
                            <div class="amount-row"><span class="empty">—</span></div>
                        @endif
                        @foreach ($methodAmounts as $code => $amount)
                            @php
                                $currency = \App\Modules\Core\Enums\Currency::from($code);
                            @endphp
                            <div class="amount-row" data-summary-method="{{ $methodKey }}" data-summary-currency="{{ $code }}">
                                <span class="amount-label">{{ $code }}</span>
                                <span class="amount-value">{{ $amount['amount']->format($currency) }}{{ $code !== 'TRY' ? ' '.$code : '' }}
                                    @if ($code !== 'TRY')
                                        <span class="amount-fx">≈ {{ $amount['try_equivalent']->format($try) }}</span>
                                    @endif
                                </span>
                            </div>
                        @endforeach
                    @endif
                </section>
            @endforeach
            @if ($column === 'right')
                <section class="receipt-section" aria-labelledby="receipt-title">
                    <h2 class="group-title" id="receipt-title">ÖDEMELER — TL KARŞILIĞI</h2>
                    <x-monthly-receipt-breakdown :summary-data="$summary" data-summary-receipt-breakdown="month" />
                </section>
            @endif
            </div>
            @endforeach
        </section>
    </main>
</body>
</html>
