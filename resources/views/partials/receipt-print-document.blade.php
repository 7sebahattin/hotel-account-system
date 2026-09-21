<style>
    :root {
        --receipt-gold: #d49a00;
        --receipt-gold-soft: #fff9e8;
        --receipt-ink: #111827;
        --receipt-muted: #5f6773;
        --receipt-line: #d7dadd;
        --receipt-panel: #fbfbfa;
        --receipt-danger: #a61b1b;
    }

    .receipt-toolbar {
        display: flex;
        gap: 8px;
        margin: 0 auto 12px;
        max-width: 188mm;
    }

    .receipt-toolbar a,
    .receipt-toolbar button {
        min-height: 42px;
        border: 1px solid #7b828a;
        border-radius: 5px;
        padding: 7px 12px;
        background: #fff;
        color: var(--receipt-ink);
        font: inherit;
        text-decoration: none;
        cursor: pointer;
    }

    .receipt-document {
        max-width: 188mm;
        margin: 0 auto;
        border: 1px solid #d9dcdf;
        border-radius: 3mm;
        padding: 7mm 8mm 6mm;
        background: #fff;
        color: var(--receipt-ink);
        box-shadow: 0 2mm 8mm rgb(17 24 39 / 7%);
    }

    .receipt-cancelled {
        margin: -1mm 0 5mm;
        border: 1.5px solid var(--receipt-danger);
        border-radius: 1.5mm;
        padding: 2.5mm 3mm;
        color: var(--receipt-danger);
        text-align: center;
        font-size: 11pt;
        font-weight: 800;
        letter-spacing: .12em;
    }

    .receipt-head {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 53mm;
        gap: 7mm;
        align-items: start;
        border-bottom: 1.2mm solid var(--receipt-gold);
        padding-bottom: 4.5mm;
    }

    .receipt-brand-name {
        margin: 0;
        font-size: 22pt;
        line-height: 1;
        font-weight: 850;
        letter-spacing: .025em;
        color: #0b1524;
    }

    .receipt-legal-name {
        margin: 1.8mm 0 0;
        font-size: 7.8pt;
        line-height: 1.25;
        font-weight: 700;
    }

    .receipt-company-lines {
        margin-top: 2.2mm;
        color: #2f3741;
        font-size: 7.6pt;
        line-height: 1.45;
    }

    .receipt-company-lines p {
        margin: .45mm 0;
    }

    .receipt-company-lines strong {
        color: #111827;
    }

    .receipt-title-block {
        text-align: right;
    }

    .receipt-title {
        margin: 0;
        color: var(--receipt-gold);
        font-size: 18.5pt;
        line-height: 1.12;
        font-weight: 850;
        letter-spacing: .055em;
    }

    .receipt-title-meta {
        display: grid;
        gap: 2mm;
        margin-top: 4mm;
    }

    .receipt-title-meta-row {
        display: grid;
        grid-template-columns: 19mm minmax(0, 1fr);
        align-items: center;
        gap: 2mm;
        font-size: 8pt;
    }

    .receipt-title-meta-row .label {
        text-align: right;
        font-weight: 700;
    }

    .receipt-title-meta-row .value {
        min-height: 8mm;
        border: 1px solid #cdd1d5;
        background: #fff;
        padding: 1.8mm 2mm;
        text-align: center;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .receipt-main {
        padding-top: 6mm;
    }

    .receipt-line-field {
        display: grid;
        grid-template-columns: 35mm minmax(0, 1fr);
        gap: 3mm;
        align-items: end;
        margin: 4.5mm 0;
    }

    .receipt-line-field .label {
        font-size: 8.5pt;
        font-weight: 750;
    }

    .receipt-line-field .value {
        min-height: 7mm;
        border-bottom: 1px dashed #9fa5ab;
        padding: 1mm 1.5mm 1.3mm;
        font-size: 10pt;
    }

    .receipt-line-field .value.muted {
        color: var(--receipt-muted);
        font-size: 8.5pt;
    }

    .receipt-payment-panel {
        display: grid;
        grid-template-columns: minmax(0, 1.05fr) minmax(0, .95fr);
        gap: 7mm;
        margin: 6mm 0;
        border: 1px solid var(--receipt-line);
        background: var(--receipt-panel);
        padding: 5mm;
    }

    .receipt-amount-area {
        display: grid;
        grid-template-columns: 22mm minmax(0, 1fr);
        gap: 3mm;
        align-items: center;
    }

    .receipt-amount-area .label {
        font-size: 10pt;
        font-weight: 800;
    }

    .receipt-amount-box {
        display: flex;
        min-height: 13mm;
        align-items: center;
        justify-content: flex-end;
        border: 1.4px solid var(--receipt-gold);
        border-radius: 1.5mm;
        background: #fff;
        padding: 2.5mm 4mm;
        font-size: 15pt;
        font-weight: 800;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .receipt-methods-title {
        margin: 0 0 2mm;
        font-size: 8.5pt;
        font-weight: 800;
    }

    .receipt-methods {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1.6mm 4mm;
        font-size: 8.5pt;
    }

    .receipt-method {
        display: flex;
        align-items: center;
        gap: 1.8mm;
        min-width: 0;
    }

    .receipt-method-box {
        display: inline-flex;
        width: 4mm;
        height: 4mm;
        flex: 0 0 4mm;
        align-items: center;
        justify-content: center;
        border: 1px solid #4b5563;
        background: #fff;
        font-size: 8pt;
        font-weight: 900;
        line-height: 1;
    }

    .receipt-method[data-checked="true"] .receipt-method-box {
        border-color: var(--receipt-gold);
        background: var(--receipt-gold);
        color: #fff;
    }

    .receipt-description {
        margin-top: 4.5mm;
    }

    .receipt-description .label {
        margin-bottom: 1.5mm;
        font-size: 8.5pt;
        font-weight: 800;
    }

    .receipt-description-box {
        min-height: 26mm;
        border-bottom: 1px dashed #9fa5ab;
        padding: 1.5mm 1mm 2.5mm;
        font-size: 9pt;
        line-height: 1.5;
    }

    .receipt-description-text {
        min-height: 8mm;
        white-space: pre-line;
    }

    .receipt-secondary-notes {
        display: grid;
        gap: .7mm;
        margin-top: 2.5mm;
        color: var(--receipt-muted);
        font-size: 7.7pt;
    }

    .receipt-signatures {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10mm;
        margin: 10mm 4mm 0;
    }

    .receipt-signature {
        display: flex;
        min-height: 36mm;
        flex-direction: column;
        border: 1px solid #d5d9dc;
        border-radius: 2mm;
        background: #fff;
        padding: 3mm 4mm;
        text-align: center;
    }

    .receipt-signature-title {
        border-bottom: 1px solid #e3e5e7;
        padding-bottom: 2mm;
        font-size: 8.5pt;
        font-weight: 800;
    }

    .receipt-signature-space {
        flex: 1;
        display: flex;
        align-items: flex-end;
        justify-content: center;
        padding: 3mm 2mm 1.5mm;
        font-size: 8pt;
        color: #39424c;
    }

    .receipt-signature-caption {
        color: var(--receipt-muted);
        font-size: 7.4pt;
    }

    .receipt-disclaimer {
        margin: 5mm 0 0;
        color: var(--receipt-muted);
        text-align: center;
        font-size: 7pt;
        line-height: 1.35;
    }

    .receipt-foot {
        display: flex;
        justify-content: space-between;
        gap: 6mm;
        margin-top: 4mm;
        border-top: 1px solid #d8dcdf;
        padding-top: 2.5mm;
        color: var(--receipt-muted);
        font-size: 7pt;
    }

    @media screen and (max-width: 760px) {
        .receipt-document {
            border-radius: 8px;
            padding: 18px;
        }

        .receipt-head,
        .receipt-payment-panel,
        .receipt-signatures {
            grid-template-columns: 1fr;
        }

        .receipt-title-block {
            text-align: left;
        }

        .receipt-title-meta-row .label {
            text-align: left;
        }

        .receipt-line-field {
            grid-template-columns: 1fr;
            gap: 4px;
        }

        .receipt-signatures {
            margin-right: 0;
            margin-left: 0;
        }
    }

    @media print {
        @page {
            size: A4 portrait;
            margin: 8mm;
        }

        body {
            padding: 0 !important;
        }

        .receipt-toolbar {
            display: none !important;
        }

        .receipt-document {
            max-width: none;
            margin: 0;
            border-color: #cfd3d6;
            box-shadow: none;
            break-inside: avoid;
            page-break-inside: avoid;
        }
    }
</style>

@php
    $receiptMethods = [
        'cash' => 'Nakit',
        'credit_card' => 'K. Kartı',
        'bank_transfer' => 'Havale/EFT',
        'cheque' => 'Çek/Senet',
        'other' => 'Diğer',
    ];
@endphp

<div class="receipt-toolbar">
    <button type="button" onclick="window.print()">Yazdır</button>
    <a href="{{ $receiptBackUrl }}">{{ $receiptBackLabel }}</a>
</div>

<article class="receipt-document" data-receipt-template="v4">
    @if ($receiptCancelled)
        <div class="receipt-cancelled">İPTAL EDİLDİ</div>
    @endif

    <header class="receipt-head">
        <section>
            <h1 class="receipt-brand-name">{{ config('hotel.company.receipt_brand_name') }}</h1>
            <p class="receipt-legal-name">{{ config('hotel.company.legal_name') }}</p>

            <div class="receipt-company-lines">
                @if (config('hotel.company.address'))
                    <p><strong>Adres:</strong> {{ config('hotel.company.address') }}</p>
                @endif
                <p>
                    @if (config('hotel.company.phone')) <strong>Tel:</strong> {{ config('hotel.company.phone') }} @endif
                    @if (config('hotel.company.email')) &nbsp;|&nbsp; <strong>E-posta:</strong> {{ config('hotel.company.email') }} @endif
                </p>
                <p>
                    @if (config('hotel.company.tax_office')) <strong>VD / No:</strong> {{ config('hotel.company.tax_office') }} @endif
                    @if (config('hotel.company.tax_number')) - {{ config('hotel.company.tax_number') }} @endif
                </p>
                <p>
                    @if (config('hotel.company.trade_registry_no')) <strong>Tic. Sicil No:</strong> {{ config('hotel.company.trade_registry_no') }} @endif
                    @if (config('hotel.company.mersis_no')) &nbsp;|&nbsp; <strong>Mersis No:</strong> {{ config('hotel.company.mersis_no') }} @endif
                </p>
            </div>
        </section>

        <section class="receipt-title-block">
            <h2 class="receipt-title">TAHSİLAT<br>MAKBUZU</h2>
            <div class="receipt-title-meta">
                <div class="receipt-title-meta-row">
                    <span class="label">Makbuz No:</span>
                    <span class="value">{{ $receiptNo }}</span>
                </div>
                <div class="receipt-title-meta-row">
                    <span class="label">Tarih:</span>
                    <span class="value">{{ $receiptDate }}</span>
                </div>
            </div>
        </section>
    </header>

    <main class="receipt-main">
        <div class="receipt-line-field">
            <div class="label">Sayın (Müşteri/Cari)</div>
            <div class="value">{{ $receiptCustomerName }}</div>
        </div>

        <div class="receipt-line-field">
            <div class="label">Yalnız (Yazı ile)</div>
            <div class="value {{ $receiptAmountInWords ? '' : 'muted' }}">
                {{ $receiptAmountInWords ?: '— · '.$receiptCurrencyCode.' için yazıyla tutar üretilmiyor.' }}
            </div>
        </div>

        <section class="receipt-payment-panel">
            <div class="receipt-amount-area">
                <div class="label">TUTAR:</div>
                <div class="receipt-amount-box">{{ $receiptAmount }}</div>
            </div>

            <div>
                <p class="receipt-methods-title">Ödeme Şekli:</p>
                <div class="receipt-methods">
                    @foreach ($receiptMethods as $methodValue => $methodLabel)
                        <div class="receipt-method"
                             data-receipt-method="{{ $methodValue }}"
                             data-checked="{{ $receiptPaymentMethod === $methodValue ? 'true' : 'false' }}">
                            <span class="receipt-method-box" aria-hidden="true">
                                @if ($receiptPaymentMethod === $methodValue) ✓ @endif
                            </span>
                            <span>{{ $methodLabel }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="receipt-description">
            <div class="label">Açıklama</div>
            <div class="receipt-description-box">
                <div class="receipt-description-text">{{ $receiptDescription ?: '—' }}</div>

                @if ($receiptSecondaryNotes !== [])
                    <div class="receipt-secondary-notes">
                        @foreach ($receiptSecondaryNotes as $note)
                            <div>{{ $note }}</div>
                        @endforeach
                    </div>
                @endif

                @if ($receiptCancelledDetails)
                    <div class="receipt-secondary-notes">
                        <div style="color: var(--receipt-danger); font-weight: 700;">{{ $receiptCancelledDetails }}</div>
                    </div>
                @endif
            </div>
        </section>

        <section class="receipt-signatures">
            <div class="receipt-signature">
                <div class="receipt-signature-title">Teslim Eden (Ödeyen)</div>
                <div class="receipt-signature-space">{{ $receiptPayerName ?: ' ' }}</div>
                <div class="receipt-signature-caption">Adı Soyadı / İmza</div>
            </div>

            <div class="receipt-signature">
                <div class="receipt-signature-title">Teslim Alan (Firma Yetkilisi)</div>
                <div class="receipt-signature-space"><strong>{{ $receiptCreatorName ?: '—' }}</strong></div>
                <div class="receipt-signature-caption">Adı Soyadı / Kaşe ve İmza</div>
            </div>
        </section>

        <p class="receipt-disclaimer">{{ $receiptDisclaimer }}</p>

        <footer class="receipt-foot">
            <span>Makbuz No: {{ $receiptNo }}</span>
            <span>{{ $receiptPrintedAt }} tarihinde yazdırıldı</span>
        </footer>
    </main>
</article>
