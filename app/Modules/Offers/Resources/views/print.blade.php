@extends('layouts.print')
@section('title', $offer->offer_no.' · Teklif')
@section('content')
    @php
        $priceMap = $offer->prices->keyBy(fn ($price) => $price->offer_room_type_id.'-'.$price->offer_period_id);
        $logoPath = (string) config('hotel.brand.logo');
    @endphp
    <style>
        body { background:#eef0ee; padding:18px; color:#182329; font-family:Arial, sans-serif; }
        .offer-toolbar { max-width:210mm; margin:0 auto 14px; display:flex; gap:8px; }
        .offer-toolbar a,.offer-toolbar button { border:1px solid #c7ceca; background:white; color:#182329; border-radius:8px; padding:10px 16px; min-height:44px; font:inherit; text-decoration:none; cursor:pointer; }
        .offer-sheet { max-width:210mm; margin:auto; padding:18mm 17mm; background:white; box-shadow:0 16px 42px #14271c1a; }
        .offer-top { display:flex; align-items:flex-start; justify-content:space-between; gap:20px; border-bottom:3px solid #a97a17; padding-bottom:15px; }
        .offer-brand { display:flex; align-items:center; gap:12px; }
        .offer-brand img { max-width:64px; max-height:58px; object-fit:contain; }
        .offer-brand strong { display:block; font-size:19px; letter-spacing:.04em; }
        .offer-brand small { color:#657276; font-size:10px; letter-spacing:.14em; text-transform:uppercase; }
        .offer-kicker { text-align:right; color:#657276; font-size:10px; letter-spacing:.14em; text-transform:uppercase; }
        .offer-kicker strong { display:block; margin-top:4px; color:#182329; font-size:14px; letter-spacing:0; }
        .offer-title { padding:26px 0 19px; }
        .offer-title p { margin:0 0 7px; color:#a47716; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.16em; }
        .offer-title h1 { font-size:27px; line-height:1.18; font-weight:700; margin:0; color:#182329; }
        .offer-title .intro { margin:12px 0 0; color:#47565a; font-size:11px; white-space:pre-line; }
        .offer-meta { display:grid; grid-template-columns:1.5fr 1fr 1fr; gap:8px; margin:0 0 25px; }
        .offer-meta div { border:1px solid #dce1dd; border-radius:7px; padding:10px 12px; background:#f8faf8; }
        .offer-meta span { display:block; color:#687678; font-size:9px; text-transform:uppercase; letter-spacing:.08em; }
        .offer-meta strong { display:block; margin-top:4px; font-size:11px; overflow-wrap:anywhere; }
        .offer-section-title { color:#182329; margin:0 0 10px; font-size:13px; }
        .offer-table { width:100%; border-collapse:collapse; font-size:10px; }
        .offer-table thead { display:table-header-group; }
        .offer-table th { background:#1e3333; color:white; border:0; padding:11px 10px; white-space:normal; }
        .offer-table th:first-child { text-align:left; }
        .offer-table th:not(:first-child),.offer-table td:not(:first-child) { text-align:right; }
        .offer-table td { border-bottom:1px solid #dce1dd; padding:12px 10px; }
        .offer-table tr:nth-child(even) td { background:#f7f9f7; }
        .offer-table td:first-child { font-weight:700; }
        .offer-table small { display:block; margin-top:3px; color:#d8e4df; font-weight:400; }
        .offer-notes { display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-top:23px; }
        .offer-note { border-top:2px solid #d9b35c; padding-top:9px; break-inside:avoid; }
        .offer-note h2 { font-size:11px; margin:0 0 6px; }
        .offer-note p { color:#4d5b5e; font-size:10px; line-height:1.55; margin:0; white-space:pre-line; }
        .offer-footer { display:grid; grid-template-columns:1fr 1fr; gap:20px; border-top:1px solid #ccd4cf; margin-top:25px; padding-top:15px; color:#566467; font-size:9px; line-height:1.5; }
        .offer-footer strong { color:#243639; }
        .offer-footer p { margin:0 0 4px; overflow-wrap:anywhere; }
        .offer-closing { margin-top:20px; padding:12px 15px; border-left:3px solid #a97a17; background:#f8f5ed; white-space:pre-line; font-size:10px; }
        @media screen and (max-width:650px) { .offer-sheet { padding:22px 15px; } .offer-meta,.offer-notes,.offer-footer { grid-template-columns:1fr; } .offer-table { display:block; overflow-x:auto; } }
        @media print { body { padding:0; background:white; } .offer-toolbar { display:none; } .offer-sheet { max-width:none; padding:0; box-shadow:none; } @page { size:A4; margin:14mm 12mm; } }
    </style>
    <div class="offer-toolbar"><a href="{{ route('offers.show', $offer) }}">← Teklife dön</a><button type="button" onclick="window.print()">Yazdır / PDF kaydet</button></div>
    <article class="offer-sheet">
        <header class="offer-top">
            <div class="offer-brand">
                @if ($logoPath !== '' && is_file(public_path($logoPath)))<img src="{{ asset($logoPath) }}" alt="{{ $offer->hotel_name_snapshot }} logosu">@endif
                <div><strong>{{ $offer->hotel_name_snapshot ?: 'LEMON HOTEL' }}</strong><small>Konaklama fiyat teklifi</small></div>
            </div>
            <div class="offer-kicker">Teklif no<strong>{{ $offer->offer_no }}</strong></div>
        </header>
        <section class="offer-title">
            <p>{{ $offer->document_year }} / Konaklama</p>
            <h1>{{ $offer->document_heading }}</h1>
            @if ($offer->intro_text)<div class="intro">{{ $offer->intro_text }}</div>@endif
        </section>
        <div class="offer-meta">
            <div><span>Sayın</span><strong>{{ $offer->customer_name_snapshot }}</strong></div>
            <div><span>Teklif tarihi</span><strong>{{ $offer->offer_date->format('d.m.Y') }}</strong></div>
            <div><span>Geçerlilik</span><strong>{{ $offer->valid_until?->format('d.m.Y') ?? 'Belirtilmedi' }}</strong></div>
        </div>
        <section>
            <h2 class="offer-section-title">{{ $offer->title }}</h2>
            <table class="offer-table">
                <thead><tr><th>Oda tipi</th>@foreach ($offer->periods as $period)<th>{{ $period->label }}@if ($period->start_date && $period->end_date)<small>{{ $period->start_date->format('d.m.Y') }} – {{ $period->end_date->format('d.m.Y') }}</small>@endif</th>@endforeach</tr></thead>
                <tbody>@foreach ($offer->roomTypes as $room)<tr><td>{{ $room->name }}</td>@foreach ($offer->periods as $period)@php $price = $priceMap->get($room->id.'-'.$period->id); @endphp<td>{{ $price ? \App\Modules\Core\Support\Money::fromDecimal($price->price)->formatNumber().' '.$offer->currency : '—' }}</td>@endforeach</tr>@endforeach</tbody>
            </table>
        </section>
        <div class="offer-notes">
            @if ($offer->general_notes)<section class="offer-note"><h2>Genel açıklamalar</h2><p>{{ $offer->general_notes }}</p></section>@endif
            @if ($offer->cancellation_terms)<section class="offer-note"><h2>İptal ve fiyat koşulları</h2><p>{{ $offer->cancellation_terms }}</p></section>@endif
        </div>
        @if ($offer->footer_notes)<div class="offer-closing">{{ $offer->footer_notes }}</div>@endif
        <footer class="offer-footer">
            <div><strong>{{ $offer->legal_name_snapshot }}</strong>@foreach ([$offer->address_snapshot, trim(($offer->tax_office_snapshot ?? '').' · '.($offer->tax_number_snapshot ?? ''), ' ·'), $offer->phone_snapshot, $offer->email_snapshot] as $line)@if ($line)<p>{{ $line }}</p>@endif @endforeach</div>
            <div>@if ($offer->bank_account_holder)<p><strong>Hesap sahibi:</strong> {{ $offer->bank_account_holder }}</p>@endif
                @if ($offer->bank_iban_try)<p><strong>TL IBAN:</strong> {{ $offer->bank_iban_try }}</p>@endif
                @if ($offer->bank_iban_eur)<p><strong>EUR IBAN:</strong> {{ $offer->bank_iban_eur }}</p>@endif
            </div>
        </footer>
    </article>
@endsection
