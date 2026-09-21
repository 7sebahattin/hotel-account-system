@extends('layouts.print')
@section('title', $entry->receipt_no.' — Tahsilat Makbuzu Defteri')

@section('content')
    @php
        $receiptSecondaryNotes = [];

        if ($entry->hasExchangeRate()) {
            $rate = rtrim(rtrim(number_format((float) $entry->exchange_rate_to_try, 4, ',', '.'), '0'), ',');
            $receiptSecondaryNotes[] = 'Kur: '.$rate.' · TL karşılığı: '.$entry->tryEquivalentMoney()->format(\App\Modules\Core\Enums\Currency::TRY);
        }
    @endphp

    @include('partials.receipt-print-document', [
        'receiptBackUrl' => route('receipt-book.show', $entry),
        'receiptBackLabel' => 'Kayda dön',
        'receiptNo' => $entry->receipt_no,
        'receiptDate' => $entry->receipt_date->format('d.m.Y'),
        'receiptCustomerName' => $entry->customer_name,
        'receiptAmount' => $entry->amountMoney()->format($entry->currency),
        'receiptAmountInWords' => $entry->amountInWords(),
        'receiptCurrencyCode' => $entry->currency->value,
        'receiptPaymentMethod' => $entry->payment_method?->value,
        'receiptDescription' => $entry->description,
        'receiptSecondaryNotes' => $receiptSecondaryNotes,
        'receiptCancelled' => false,
        'receiptCancelledDetails' => null,
        'receiptPayerName' => null,
        'receiptCreatorName' => $entry->creator?->name,
        'receiptDisclaimer' => 'Tahsilat Makbuzu Defteri kaydıdır; muhasebe/cari hareketi oluşturmaz ve fatura/e-Arşiv fatura değildir.',
        'receiptPrintedAt' => now()->format('d.m.Y H:i'),
    ])
@endsection
