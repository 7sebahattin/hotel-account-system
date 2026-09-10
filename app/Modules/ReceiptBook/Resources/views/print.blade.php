@extends('layouts.print')
@section('title', $entry->receipt_no.' — Tahsilat Makbuzu Defteri')

@section('content')
    <style>
        {{-- Bu makbuza özgü stiller — referans görsele YAKIN iki sütunlu başlık. --}}
        .receipt-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px;
                         border-bottom: 1.5px solid #111; padding-bottom: 10px; margin-bottom: 14px; }
        .receipt-head .company { max-width: 60%; }
        .receipt-head .company h1 { font-size: 13pt; }
        .receipt-head .company p { margin: 3px 0 0; color: #444; font-size: 9.5pt; }
        .receipt-head .title { text-align: right; }
        .receipt-head .title h1 { font-size: 18pt; letter-spacing: 0.04em; }
        .receipt-head .title p { margin: 4px 0 0; font-size: 9.5pt; color: #444; }

        .meta-row { display: flex; justify-content: space-between; font-size: 10pt; margin-bottom: 12px; }

        .field { margin: 10px 0; }
        .field .label { font-size: 8.5pt; text-transform: uppercase; letter-spacing: 0.04em; color: #666; }
        .field .value { margin-top: 2px; font-size: 11pt; border-bottom: 1px solid #999; padding-bottom: 4px; min-height: 16pt; }

        .amount-box { border: 1.5px solid #111; padding: 10px 12px; margin: 14px 0; }
        .amount-box .label { font-size: 8.5pt; text-transform: uppercase; letter-spacing: 0.04em; color: #666; }
        .amount-box .value { margin-top: 2px; font-size: 15pt; font-weight: 700; font-variant-numeric: tabular-nums; }
        .amount-box .fx { margin-top: 2px; font-size: 9pt; color: #444; font-weight: 400; }

        .methods { display: flex; gap: 18px; margin: 14px 0; font-size: 10.5pt; }
        .methods .box { display: inline-flex; align-items: center; gap: 6px; }
        .methods .indicator { display: inline-block; width: 12px; height: 12px; border: 1.3px solid #111; }
        .methods .indicator.checked { background: #111; }

        .signatures { display: flex; justify-content: space-between; gap: 24px; margin-top: 44px; }
        .signature { flex: 1; text-align: center; font-size: 10pt; }
        .signature .line { border-top: 1px solid #111; margin-top: 36px; padding-top: 4px; }

        .not-invoice { margin-top: 10px; font-size: 8.5pt; color: #777; text-align: center; }
    </style>

    <div class="toolbar">
        <button type="button" onclick="window.print()">Yazdır</button>
        <a href="{{ route('receipt-book.show', $entry) }}">Kayda dön</a>
    </div>

    <div class="receipt-head">
        <div class="company">
            <h1>{{ config('hotel.company.legal_name') }}</h1>
            <p>
                @if (config('hotel.company.address')) {{ config('hotel.company.address') }} @endif
            </p>
            <p>
                @if (config('hotel.company.phone')) {{ config('hotel.company.phone') }} @endif
                @if (config('hotel.company.email')) · {{ config('hotel.company.email') }} @endif
            </p>
            <p>
                @if (config('hotel.company.tax_office')) Vergi Dairesi: {{ config('hotel.company.tax_office') }} @endif
                @if (config('hotel.company.tax_number')) · Vergi No: {{ config('hotel.company.tax_number') }} @endif
            </p>
        </div>
        <div class="title">
            <h1>TAHSİLAT MAKBUZU</h1>
            <p>Makbuz No: {{ $entry->receipt_no }}</p>
            <p>Bu belge fatura/e-Arşiv fatura DEĞİLDİR — dokümantasyon amaçlı bir kayıttır.</p>
        </div>
    </div>

    <div class="meta-row">
        <span><strong>Tarih:</strong> {{ $entry->receipt_date->format('d.m.Y') }}</span>
    </div>

    <div class="field">
        <div class="label">Sayın (Müşteri / Cari)</div>
        <div class="value">{{ $entry->customer_name }}</div>
    </div>

    <div class="amount-box">
        <div class="label">Tutar</div>
        <div class="value">{{ $entry->amountMoney()->format($entry->currency) }}</div>
        @if ($entry->hasExchangeRate())
            <div class="fx">
                ≈ {{ $entry->tryEquivalentMoney()->format(\App\Modules\Core\Enums\Currency::TRY) }}
                · Kur: {{ rtrim(rtrim(number_format((float) $entry->exchange_rate_to_try, 4, ',', '.'), '0'), ',') }}
            </div>
        @endif
    </div>

    <div class="field">
        <div class="label">Yalnız (Yazı ile)</div>
        <div class="value">
            {{ $entry->amountInWords() ?? '— (yalnızca TRY için yazıyla tutar üretilir; para birimi '.$entry->currency->value.')' }}
        </div>
    </div>

    <div class="methods">
        @foreach (\App\Modules\ReceiptBook\Enums\ReceiptPaymentMethod::cases() as $method)
            <span class="box">
                <span class="indicator @if ($entry->payment_method === $method) checked @endif"></span>
                {{ $method->label() }}
            </span>
        @endforeach
    </div>

    <div class="field">
        <div class="label">Açıklama</div>
        <div class="value">{{ $entry->description ?: '—' }}</div>
    </div>

    <div class="signatures">
        <div class="signature">
            <p>Teslim Eden</p>
            <p class="line">&nbsp;</p>
        </div>
        <div class="signature">
            <p>Teslim Alan</p>
            <p class="line">{{ $entry->creator?->name ?? '—' }}</p>
        </div>
    </div>

    <p class="not-invoice">
        Bu makbuz Tahsilat Makbuzu Defteri'nden üretilmiştir; muhasebe/cari hareket kaydı DEĞİLDİR.
    </p>

    <div class="foot">
        <span>Makbuz No: {{ $entry->receipt_no }}</span>
        <span>{{ now()->format('d.m.Y H:i') }} tarihinde yazdırıldı</span>
    </div>
@endsection
