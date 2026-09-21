@extends('layouts.print')
@section('title', $payment->receipt_no.' — Tahsilat Makbuzu')

@section('content')
    @php
        $receiptSecondaryNotes = [];

        if ($payment->payer_name) {
            $payerType = $payment->payer_type?->label();
            $receiptSecondaryNotes[] = 'Ödeyen: '.$payment->payer_name.($payerType ? ' · '.$payerType : '');
        }

        if ($payment->reference_no) {
            $receiptSecondaryNotes[] = 'Referans No: '.$payment->reference_no;
        }

        if ($payment->hasExchangeRate()) {
            $rate = rtrim(rtrim(number_format((float) $payment->exchange_rate_to_try, 4, ',', '.'), '0'), ',');
            $receiptSecondaryNotes[] = 'Kur: '.$rate.' · TL karşılığı: '.$payment->tryEquivalentMoney()->format(\App\Modules\Core\Enums\Currency::TRY);
        }

        $receiptCancelledDetails = $payment->isCancelled()
            ? 'İptal: '.($payment->cancelled_at?->format('d.m.Y H:i') ?? '—').' · '.($payment->cancellation_reason ?: 'Neden belirtilmedi')
            : null;
    @endphp

    @include('partials.receipt-print-document', [
        'receiptBackUrl' => route('payments.show', $payment),
        'receiptBackLabel' => 'Tahsilata dön',
        'receiptNo' => $payment->receipt_no,
        'receiptDate' => $payment->payment_date->format('d.m.Y'),
        'receiptCustomerName' => $payment->company?->name ?? 'Kaynak hesap bulunamadı',
        'receiptAmount' => $payment->amountMoney()->format($payment->currency),
        'receiptAmountInWords' => $payment->amountInWords(),
        'receiptCurrencyCode' => $payment->currency->value,
        'receiptPaymentMethod' => $payment->payment_method?->value,
        'receiptDescription' => $payment->description,
        'receiptSecondaryNotes' => $receiptSecondaryNotes,
        'receiptCancelled' => $payment->isCancelled(),
        'receiptCancelledDetails' => $receiptCancelledDetails,
        'receiptPayerName' => null,
        'receiptCreatorName' => $payment->creator?->name,
        'receiptDisclaimer' => 'Bu belge bir fatura/e-Arşiv fatura değildir; operasyonel tahsilat kaydıdır.',
        'receiptPrintedAt' => now()->format('d.m.Y H:i'),
    ])
@endsection
