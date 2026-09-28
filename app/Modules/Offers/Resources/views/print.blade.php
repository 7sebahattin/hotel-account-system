@extends('layouts.print')
@section('title', $offer->offer_no.' · Teklif')
@section('content')
    @php
        $priceMap = $offer->prices->keyBy(fn ($price) => $price->offer_room_type_id.'-'.$price->offer_period_id);
        $brandName = $offer->hotel_name_snapshot ?: 'LEMON HOTEL';
        $wideTable = $offer->periods->count() > 6;
        $generalLines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $offer->general_notes) ?: [])));
        $cancellationLines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $offer->cancellation_terms) ?: [])));
    @endphp
    <style>
        :root { color-scheme: light; }
        body { margin: 0; padding: 18px; background: #edf0ef; color: #1f2933; font: 10pt/1.45 Arial, Helvetica, sans-serif; }
        .offer-actions { display: flex; gap: 8px; max-width: 210mm; margin: 0 auto 14px; }
        .offer-actions a, .offer-actions button { display: inline-flex; min-height: 44px; align-items: center; padding: 8px 14px; border: 1px solid #b9c1c0; border-radius: 6px; background: #fff; color: #1f2933; font: inherit; text-decoration: none; cursor: pointer; }
        .offer-actions :is(a, button):focus-visible { outline: 2px solid #8a6a2f; outline-offset: 2px; }
        .offer-sheet { width: 100%; max-width: 210mm; margin: auto; padding: 17mm 16mm; background: #fff; box-shadow: 0 10px 28px #1f29331a; }
        .offer-header { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 12mm; align-items: start; padding-bottom: 9mm; border-bottom: 1.5px solid #a8884c; break-inside: avoid; }
        .offer-brand { display: flex; align-items: center; gap: 10px; min-width: 0; }
        .offer-logo-img { display: block; max-width: 54px; max-height: 52px; width: auto; height: auto; flex: none; object-fit: contain; }
        .offer-brand-name { display: block; color: #1f2933; font-size: 15pt; font-weight: 700; line-height: 1.1; letter-spacing: .025em; overflow-wrap: anywhere; }
        .offer-brand-caption { display: block; margin-top: 4px; color: #54606b; font-size: 7.5pt; font-weight: 700; letter-spacing: .13em; text-transform: uppercase; }
        .offer-number { text-align: right; white-space: nowrap; }
        .offer-number span, .offer-meta dt, .offer-customer-label, .offer-section-kicker { color: #59646e; font-size: 7.5pt; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; }
        .offer-number strong { display: block; margin-top: 5px; color: #1f2933; font-size: 11pt; font-weight: 700; letter-spacing: .015em; }
        .offer-intro { padding: 11mm 0 8mm; break-inside: avoid; }
        .offer-intro .offer-section-kicker { color: #8a6a2f; }
        .offer-intro h1 { max-width: 160mm; margin: 6px 0 0; color: #1f2933; font-size: 22pt; line-height: 1.17; font-weight: 700; letter-spacing: -.02em; overflow-wrap: anywhere; }
        .offer-intro .offer-subtitle { margin: 7px 0 0; color: #43505a; font-size: 11pt; font-weight: 600; }
        .offer-meta { display: grid; grid-template-columns: minmax(0, 1.5fr) 1fr 1fr; gap: 0; margin: 0 0 8mm; border: 1px solid #cbd2d0; border-radius: 5px; break-inside: avoid; }
        .offer-meta div { min-width: 0; padding: 8px 11px; }
        .offer-meta div + div { border-left: 1px solid #dfe3e0; }
        .offer-meta dd { margin: 4px 0 0; color: #1f2933; font-size: 9pt; font-weight: 700; overflow-wrap: anywhere; }
        .offer-intro-text { margin: 0 0 8mm; color: #43505a; font-size: 9pt; line-height: 1.6; white-space: pre-line; overflow-wrap: anywhere; }
        .offer-section-heading { display: flex; justify-content: space-between; gap: 8px; align-items: baseline; margin: 0 0 4mm; break-after: avoid; }
        .offer-section-heading h2 { margin: 0; color: #1f2933; font-size: 11pt; font-weight: 700; }
        .offer-section-heading span { color: #59646e; font-size: 8pt; font-weight: 600; }
        .offer-table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 8.5pt; }
        .offer-table col:first-child { width: 22%; }
        .offer-table thead { display: table-header-group; }
        .offer-table th { padding: 8px 7px; border-top: 1.5px solid #66727a; border-bottom: 1.5px solid #66727a; color: #1f2933; background: #f6f4ef; font-weight: 700; overflow-wrap: anywhere; }
        .offer-table th:first-child { text-align: left; }
        .offer-table th:not(:first-child), .offer-table td:not(:first-child) { text-align: right; }
        .offer-table th small { display: block; margin-top: 3px; color: #4d5963; font-size: 7pt; font-weight: 400; }
        .offer-table tbody tr { break-inside: avoid; page-break-inside: avoid; }
        .offer-table td { padding: 9px 7px; border-bottom: 1px solid #d4dad8; vertical-align: top; overflow-wrap: anywhere; }
        .offer-table td:first-child { font-weight: 700; }
        .offer-price { color: #1f2933; font-weight: 700; font-variant-numeric: tabular-nums; }
        .offer-table.is-wide { font-size: 7.3pt; }
        .offer-table.is-wide th, .offer-table.is-wide td { padding: 7px 4px; }
        .offer-table.is-wide th small { font-size: 6.5pt; }
        .offer-notes { display: grid; grid-template-columns: 1fr 1fr; gap: 9mm; margin-top: 10mm; }
        .offer-note { min-width: 0; border-top: 1.5px solid #a8884c; padding-top: 4mm; }
        .offer-note h2, .offer-company h2, .offer-bank h2 { margin: 0 0 3mm; color: #1f2933; font-size: 9pt; font-weight: 700; }
        .offer-note ul { margin: 0; padding-left: 16px; color: #43505a; font-size: 8.5pt; line-height: 1.55; }
        .offer-note li { padding-left: 2px; margin: 0 0 3px; overflow-wrap: anywhere; }
        .offer-note h2, .offer-company h2, .offer-bank h2 { break-after: avoid; }
        .offer-note li::marker { color: #8a6a2f; }
        .offer-corporate { display: grid; grid-template-columns: 1fr 1fr; gap: 9mm; margin-top: 10mm; padding-top: 5mm; border-top: 1px solid #cbd2d0; }
        .offer-company, .offer-bank { min-width: 0; }
        .offer-corporate p { margin: 0 0 3px; color: #43505a; font-size: 8pt; line-height: 1.5; overflow-wrap: anywhere; }
        .offer-corporate strong { color: #1f2933; }
        .offer-closing { margin-top: 9mm; padding: 4mm 5mm; border-left: 2px solid #a8884c; color: #34424b; font-size: 9pt; line-height: 1.55; white-space: pre-line; overflow-wrap: anywhere; break-inside: avoid; }
        @media screen and (max-width: 650px) {
            body { padding: 10px; }
            .offer-sheet { padding: 20px 14px; }
            .offer-header { gap: 10px; }
            .offer-intro h1 { font-size: 18pt; }
            .offer-meta, .offer-notes, .offer-corporate { grid-template-columns: 1fr; }
            .offer-meta div + div { border-left: 0; border-top: 1px solid #dfe3e0; }
            .offer-table-wrap { max-width: 100%; overflow-x: auto; }
            .offer-table { min-width: 560px; }
        }
        @media print {
            body { padding: 0; background: #fff; }
            .offer-actions { display: none !important; }
            .offer-sheet { max-width: none; padding: 0; box-shadow: none; }
            .offer-table-wrap { overflow: visible; }
            .offer-notes { display: block; }
            .offer-note + .offer-note { margin-top: 6mm; }
            .offer-corporate { display: block; }
            .offer-bank { margin-top: 4mm; }
            .offer-meta, .offer-header, .offer-intro, .offer-section-heading, .offer-closing { page-break-inside: avoid; }
            @page { size: A4 portrait; margin: 14mm 12mm; }
        }
        @if ($wideTable)
            @media print { @page { size: A4 landscape; margin: 12mm; } }
        @endif
    </style>

    <div class="offer-actions">
        <a href="{{ route('offers.show', $offer) }}">← Teklife dön</a>
        <button type="button" onclick="window.print()">Yazdır / PDF kaydet</button>
    </div>
    <article class="offer-sheet">
        <header class="offer-header">
            <div class="offer-brand">
                <x-brand-logo variant="on-light" :decorative="true" class="offer-logo-img" />
                <div><strong class="offer-brand-name">{{ $brandName }}</strong><span class="offer-brand-caption">Konaklama fiyat teklifi</span></div>
            </div>
            <div class="offer-number"><span>Teklif no</span><strong>{{ $offer->offer_no }}</strong></div>
        </header>
        <section class="offer-intro">
            <span class="offer-section-kicker">{{ $offer->document_year }} · Konaklama</span>
            <h1>{{ $offer->document_heading }}</h1>
            <p class="offer-subtitle">{{ $offer->title }}</p>
        </section>
        <dl class="offer-meta">
            <div><dt>Sayın</dt><dd>{{ $offer->customer_name_snapshot }}</dd></div>
            <div><dt>Teklif tarihi</dt><dd>{{ $offer->offer_date->format('d.m.Y') }}</dd></div>
            <div><dt>Geçerlilik</dt><dd>{{ $offer->valid_until?->format('d.m.Y') ?? 'Belirtilmedi' }}</dd></div>
        </dl>
        @if ($offer->intro_text)<p class="offer-intro-text">{{ $offer->intro_text }}</p>@endif

        <section aria-labelledby="offer-print-prices">
            <div class="offer-section-heading"><h2 id="offer-print-prices">Konaklama fiyatları</h2><span>Para birimi: {{ $offer->currency }}</span></div>
            <div class="offer-table-wrap">
                <table class="offer-table {{ $wideTable ? 'is-wide' : '' }}">
                    <colgroup><col>@foreach ($offer->periods as $period)<col>@endforeach</colgroup>
                    <thead><tr>
                        <th scope="col">Oda tipi</th>
                        @foreach ($offer->periods as $period)
                            <th scope="col">{{ $period->label }}@if ($period->start_date && $period->end_date)<small>{{ $period->start_date->format('d.m.Y') }} – {{ $period->end_date->format('d.m.Y') }}</small>@endif</th>
                        @endforeach
                    </tr></thead>
                    <tbody>
                        @foreach ($offer->roomTypes as $room)
                            <tr><td>{{ $room->name }}</td>
                                @foreach ($offer->periods as $period)
                                    @php $price = $priceMap->get($room->id.'-'.$period->id); @endphp
                                    <td><span class="offer-price">{{ $price ? \App\Modules\Core\Support\Money::fromDecimal($price->price)->formatNumber().' '.$offer->currency : '—' }}</span></td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        @if ($generalLines || $cancellationLines)
            <div class="offer-notes">
                @if ($generalLines)
                    <section class="offer-note"><h2>Genel açıklamalar</h2><ul>@foreach ($generalLines as $line)<li>{{ $line }}</li>@endforeach</ul></section>
                @endif
                @if ($cancellationLines)
                    <section class="offer-note"><h2>İptal ve fiyat koşulları</h2><ul>@foreach ($cancellationLines as $line)<li>{{ $line }}</li>@endforeach</ul></section>
                @endif
            </div>
        @endif

        @if ($offer->legal_name_snapshot || $offer->address_snapshot || $offer->bank_account_holder || $offer->bank_iban_try || $offer->bank_iban_eur)
            <section class="offer-corporate" aria-label="Firma ve banka bilgileri">
                <div class="offer-company">
                    <h2>Firma bilgileri</h2>
                    @if ($offer->legal_name_snapshot)<p><strong>{{ $offer->legal_name_snapshot }}</strong></p>@endif
                    @foreach ([$offer->address_snapshot, trim(($offer->tax_office_snapshot ?? '').' · '.($offer->tax_number_snapshot ?? ''), ' ·'), $offer->phone_snapshot, $offer->email_snapshot] as $line)
                        @if ($line)<p>{{ $line }}</p>@endif
                    @endforeach
                </div>
                <div class="offer-bank">
                    <h2>Banka bilgileri</h2>
                    @if ($offer->bank_account_holder)<p><strong>Hesap sahibi:</strong> {{ $offer->bank_account_holder }}</p>@endif
                    @if ($offer->bank_iban_try)<p><strong>TL IBAN:</strong> {{ $offer->bank_iban_try }}</p>@endif
                    @if ($offer->bank_iban_eur)<p><strong>EUR IBAN:</strong> {{ $offer->bank_iban_eur }}</p>@endif
                </div>
            </section>
        @endif
        @if ($offer->footer_notes)<div class="offer-closing">{{ $offer->footer_notes }}</div>@endif
    </article>
@endsection
