@extends('layouts.app')
@section('title', $offer->offer_no)
@section('content')
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div><a href="{{ route('offers.index') }}" class="text-sm text-tx-muted hover:underline">← Teklifler</a><p class="mt-4 text-xs font-bold uppercase tracking-widest text-acc">{{ $offer->offer_no }}</p><h1 class="mt-1 text-3xl font-bold tracking-tight">{{ $offer->title }}</h1><p class="mt-2 text-tx-muted">{{ $offer->customer_name_snapshot }}</p></div>
        <div class="flex flex-wrap gap-2"><a href="{{ route('offers.print', $offer) }}" target="_blank" rel="noopener" class="rounded-xl bg-acc px-5 py-3 font-semibold text-on-acc">Yazdır / PDF</a>@can('update', $offer)<a href="{{ route('offers.edit', $offer) }}" class="rounded-xl border border-line px-5 py-3 font-semibold">Düzenle</a>@endcan</div>
    </div>
    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach (['Müşteri' => $offer->customer_name_snapshot, 'Teklif tarihi' => $offer->offer_date->format('d.m.Y'), 'Geçerlilik' => $offer->valid_until?->format('d.m.Y') ?? 'Belirtilmedi', 'Oluşturan' => $offer->creator?->name ?? '—'] as $label => $content)
            <div class="rounded-xl border border-line bg-surface p-4"><p class="text-xs uppercase tracking-wide text-tx-muted">{{ $label }}</p><p class="mt-2 font-semibold">{{ $content }}</p></div>
        @endforeach
    </div>
    <div class="mt-5 flex items-center gap-3">@include('offers::_status', ['status' => $offer->status])<span class="text-sm text-tx-muted">{{ $offer->currency }} · {{ $offer->document_year }}</span></div>
    <section class="mt-6 rounded-2xl border border-line bg-surface p-5 shadow-sm sm:p-7">
        <p class="text-xs font-bold uppercase tracking-widest text-acc">{{ $offer->hotel_name_snapshot }}</p><h2 class="mt-2 text-2xl font-bold">{{ $offer->document_heading }}</h2>
        @if ($offer->intro_text)<p class="mt-3 whitespace-pre-line text-tx-muted">{{ $offer->intro_text }}</p>@endif
        <div class="mt-6">@include('offers::_matrix')</div>
    </section>
    <div class="mt-5 grid gap-5 lg:grid-cols-2">
        @foreach (['Genel açıklamalar' => $offer->general_notes, 'İptal ve fiyat koşulları' => $offer->cancellation_terms, 'Kapanış notu' => $offer->footer_notes] as $heading => $content)
            @if ($content)<section class="rounded-2xl border border-line bg-surface p-5"><h2 class="font-semibold">{{ $heading }}</h2><p class="mt-3 whitespace-pre-line text-sm leading-6 text-tx-muted">{{ $content }}</p></section>@endif
        @endforeach
        @if ($offer->legal_name_snapshot || $offer->bank_account_holder || $offer->address_snapshot)
            <section class="rounded-2xl border border-line bg-surface p-5"><h2 class="font-semibold">Firma ve ödeme bilgileri</h2><div class="mt-3 space-y-1 text-sm text-tx-muted">
                @foreach ([
                    $offer->legal_name_snapshot, $offer->address_snapshot,
                    trim(($offer->tax_office_snapshot ?? '').' · '.($offer->tax_number_snapshot ?? ''), ' ·'),
                    $offer->phone_snapshot, $offer->email_snapshot,
                    $offer->bank_account_holder ? 'Hesap sahibi: '.$offer->bank_account_holder : null,
                    $offer->bank_iban_try ? 'TL IBAN: '.$offer->bank_iban_try : null,
                    $offer->bank_iban_eur ? 'EUR IBAN: '.$offer->bank_iban_eur : null,
                ] as $line) @if ($line)<p>{{ $line }}</p>@endif @endforeach
            </div></section>
        @endif
    </div>
@endsection
