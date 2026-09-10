@extends('layouts.print')
@section('title', $payment->receipt_no.' — Tahsilat Makbuzu')

@section('content')
    <style>
        {{--
            Makbuza özgü stiller — layouts.print'in genel rapor tablosu
            sınıflarının (`.summary`, `.summaries`) üstüne, YALNIZCA bu
            ekranda kullanılan birkaç ek kural. Paylaşılan layout'a
            eklenmedi: diğer yazdırma ekranlarının (ekstre, devir raporu)
            ihtiyacı yoktur (CLAUDE.md §11 gereksiz genelleştirme yasağı).
        --}}
        .receipt-title { text-align: center; margin: 18px 0; }
        .receipt-title h1 { font-size: 15pt; letter-spacing: 0.06em; }
        .receipt-title p { margin: 2px 0 0; font-size: 10pt; color: #444; }

        .meta-row { display: flex; justify-content: space-between; font-size: 10pt; margin-bottom: 12px; }

        .details { width: 100%; border-collapse: collapse; margin: 14px 0; }
        .details th, .details td { border: 1px solid #999; padding: 7px 9px; font-size: 10pt; text-align: left; vertical-align: top; }
        .details th { width: 38%; background: #f2f2f2; font-weight: 600; }
        .details .amount { font-size: 13pt; font-weight: 700; font-variant-numeric: tabular-nums; }

        .cancelled-banner {
            border: 2px solid #900; color: #900; font-weight: 700; text-align: center;
            padding: 6px; margin-bottom: 12px; letter-spacing: 0.08em;
        }

        .signatures { display: flex; justify-content: space-between; gap: 24px; margin-top: 40px; }
        .signature { flex: 1; text-align: center; font-size: 10pt; }
        .signature .line { border-top: 1px solid #111; margin-top: 36px; padding-top: 4px; }
    </style>

    <div class="toolbar">
        <button type="button" onclick="window.print()">Yazdır</button>
        <a href="{{ route('payments.show', $payment) }}">Tahsilata dön</a>
    </div>

    @if ($payment->isCancelled())
        <div class="cancelled-banner">İPTAL EDİLDİ</div>
    @endif

    <div class="head">
        <h1>{{ config('hotel.company.legal_name') }}</h1>
        <p>
            @if (config('hotel.company.address')) {{ config('hotel.company.address') }} · @endif
            @if (config('hotel.company.phone')) {{ config('hotel.company.phone') }} @endif
            @if (config('hotel.company.email')) · {{ config('hotel.company.email') }} @endif
        </p>
        <p>
            @if (config('hotel.company.tax_office')) Vergi Dairesi: {{ config('hotel.company.tax_office') }} @endif
            @if (config('hotel.company.tax_number')) · Vergi No: {{ config('hotel.company.tax_number') }} @endif
            @if (config('hotel.company.trade_registry_no')) · Ticaret Sicil No: {{ config('hotel.company.trade_registry_no') }} @endif
            @if (config('hotel.company.mersis_no')) · MERSİS: {{ config('hotel.company.mersis_no') }} @endif
        </p>
    </div>

    <div class="receipt-title">
        <h1>TAHSİLAT MAKBUZU</h1>
        <p>Bu belge bir fatura/e-Arşiv fatura DEĞİLDİR — operasyonel tahsilat kaydıdır.</p>
    </div>

    <div class="meta-row">
        <span><strong>Makbuz No:</strong> {{ $payment->receipt_no }}</span>
        <span><strong>Tarih:</strong> {{ $payment->payment_date->format('d.m.Y') }} {{ $payment->created_at->format('H:i') }}</span>
    </div>

    <table class="details">
        <tr>
            <th>Sayın / Firma</th>
            <td>{{ $payment->company?->name ?? 'Kaynak firma bulunamadı' }}</td>
        </tr>
        <tr>
            <th>Tutar</th>
            <td class="amount">
                {{ $payment->amountMoney()->format($payment->currency) }}
                @if ($payment->hasExchangeRate())
                    <br><span style="font-size: 9pt; font-weight: 400; color: #444;">
                        ≈ {{ $payment->tryEquivalentMoney()->format(\App\Modules\Core\Enums\Currency::TRY) }}
                        · Kur: {{ rtrim(rtrim(number_format((float) $payment->exchange_rate_to_try, 4, ',', '.'), '0'), ',') }}
                    </span>
                @endif
            </td>
        </tr>
        @if ($payment->amountInWords())
            <tr>
                <th>Yalnız</th>
                <td>{{ $payment->amountInWords() }}</td>
            </tr>
        @else
            <tr>
                <th>Yalnız</th>
                <td>— (yalnızca TRY için yazıyla tutar üretilir; para birimi {{ $payment->currency->value }})</td>
            </tr>
        @endif
        <tr>
            <th>Ödeme Şekli</th>
            <td>{{ $payment->payment_method?->label() ?? 'Belirtilmedi' }}</td>
        </tr>
        @if ($payment->reference_no)
            <tr>
                <th>Referans No</th>
                <td>{{ $payment->reference_no }}</td>
            </tr>
        @endif
        @if ($payment->description)
            <tr>
                <th>Açıklama</th>
                <td>{{ $payment->description }}</td>
            </tr>
        @endif
        @if ($payment->isCancelled())
            <tr>
                <th>Durum</th>
                <td>İptal edildi — {{ $payment->cancelled_at?->format('d.m.Y H:i') }} · {{ $payment->cancellation_reason }}</td>
            </tr>
        @endif
    </table>

    <div class="signatures">
        <div class="signature">
            <p>Teslim Eden</p>
            <p class="line">&nbsp;</p>
        </div>
        <div class="signature">
            <p>Teslim Alan</p>
            <p class="line">{{ $payment->creator?->name ?? '—' }}</p>
        </div>
    </div>

    <div class="foot">
        <span>Makbuz No: {{ $payment->receipt_no }}</span>
        <span>{{ now()->format('d.m.Y H:i') }} tarihinde yazdırıldı</span>
    </div>
@endsection
