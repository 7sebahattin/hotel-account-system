@extends('layouts.app')
@section('title', $offer->offer_no)
@section('content')
    @php
        $brandName = $offer->hotel_name_snapshot ?: 'LEMON HOTEL';
    @endphp
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('offers.index') }}" class="text-sm font-medium text-acc-tx hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-acc-tx">← Teklifler</a>
        <div class="flex flex-wrap gap-2">
            @can('update', $offer)
                <a href="{{ route('offers.edit', $offer) }}" class="inline-flex min-h-11 items-center rounded-lg border border-field bg-card px-4 text-sm font-semibold text-tx hover:bg-well focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-acc-tx">Düzenle</a>
            @endcan
            <a href="{{ route('offers.print', $offer) }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center gap-2 rounded-lg bg-acc px-4 text-sm font-semibold text-on-acc hover:bg-acc-strong focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-acc-tx"><x-ui.icon name="printer" /> Yazdır / PDF</a>
        </div>
    </div>

    <header class="rounded-xl border border-line bg-card p-5 sm:p-7">
        <div class="flex flex-wrap items-start justify-between gap-5 border-b border-line pb-5">
            <div class="flex min-w-0 items-center gap-3">
                <x-brand-logo variant="on-light" :decorative="true" class="h-14 max-w-36 shrink-0 object-contain" />
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-acc-tx">Konaklama fiyat teklifi</p>
                    <p class="mt-1 truncate text-lg font-semibold text-tx">{{ $brandName }}</p>
                </div>
            </div>
            <div class="text-left sm:text-right">
                <p class="text-xs font-semibold uppercase tracking-wider text-mut">Teklif no</p>
                <p class="mt-1 font-mono text-base font-semibold text-tx">{{ $offer->offer_no }}</p>
            </div>
        </div>
        <div class="mt-6 grid gap-5 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-start">
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-acc-tx">{{ $offer->document_year }} · Teklif</p>
                <h1 class="mt-2 text-2xl font-semibold leading-tight tracking-tight text-tx sm:text-3xl">{{ $offer->document_heading }}</h1>
                <p class="mt-2 text-base text-mut">{{ $offer->title }}</p>
            </div>
            <div class="lg:text-right">@include('offers::_status', ['status' => $offer->status])</div>
        </div>
        <dl class="mt-6 grid gap-4 border-t border-line pt-5 sm:grid-cols-3">
            <div><dt class="text-xs font-semibold uppercase tracking-wide text-mut">Teklif tarihi</dt><dd class="mt-1 text-sm font-semibold text-tx">{{ $offer->offer_date->format('d.m.Y') }}</dd></div>
            <div><dt class="text-xs font-semibold uppercase tracking-wide text-mut">Geçerlilik tarihi</dt><dd class="mt-1 text-sm font-semibold text-tx">{{ $offer->valid_until?->format('d.m.Y') ?? 'Belirtilmedi' }}</dd></div>
            <div><dt class="text-xs font-semibold uppercase tracking-wide text-mut">Oluşturan</dt><dd class="mt-1 text-sm font-semibold text-tx">{{ $offer->creator?->name ?? '—' }}</dd></div>
        </dl>
    </header>

    <section class="mt-5 rounded-xl border border-line bg-card p-5 sm:p-6" aria-labelledby="offer-customer-heading">
        <h2 id="offer-customer-heading" class="text-xs font-semibold uppercase tracking-[0.16em] text-acc-tx">Teklif sunulan</h2>
        <p class="mt-2 break-words text-xl font-semibold text-tx">{{ $offer->customer_name_snapshot }}</p>
        @if ($offer->intro_text)<p class="mt-4 max-w-3xl whitespace-pre-line text-sm leading-6 text-mut">{{ $offer->intro_text }}</p>@endif
    </section>

    <section class="mt-5 rounded-xl border border-acc/40 bg-card p-5 sm:p-6" aria-labelledby="offer-prices-heading">
        <div class="mb-5 flex flex-wrap items-end justify-between gap-2">
            <div><p class="text-xs font-semibold uppercase tracking-[0.16em] text-acc-tx">Konaklama</p><h2 id="offer-prices-heading" class="mt-1 text-xl font-semibold text-tx">Fiyat tablosu</h2></div>
            <p class="text-sm font-semibold text-mut">Para birimi: {{ $offer->currency }}</p>
        </div>
        @include('offers::_matrix')
    </section>

    @if ($offer->general_notes || $offer->cancellation_terms)
        <div class="mt-5 grid gap-5 lg:grid-cols-2">
            @if ($offer->general_notes)
                <section class="rounded-xl border border-line bg-card p-5 sm:p-6">
                    <h2 class="text-lg font-semibold text-tx">Genel açıklamalar</h2>
                    <p class="mt-3 whitespace-pre-line text-sm leading-6 text-mut">{{ $offer->general_notes }}</p>
                </section>
            @endif
            @if ($offer->cancellation_terms)
                <section class="rounded-xl border border-line bg-card p-5 sm:p-6">
                    <h2 class="text-lg font-semibold text-tx">İptal ve fiyat koşulları</h2>
                    <p class="mt-3 whitespace-pre-line text-sm leading-6 text-mut">{{ $offer->cancellation_terms }}</p>
                </section>
            @endif
        </div>
    @endif

    @if ($offer->legal_name_snapshot || $offer->address_snapshot || $offer->bank_account_holder || $offer->bank_iban_try || $offer->bank_iban_eur)
        <section class="mt-5 rounded-xl border border-line bg-card p-5 sm:p-6" aria-labelledby="offer-company-heading">
            <h2 id="offer-company-heading" class="text-lg font-semibold text-tx">Firma ve ödeme bilgileri</h2>
            <div class="mt-4 grid gap-6 border-t border-line pt-4 md:grid-cols-2">
                <div class="space-y-2 text-sm leading-6 text-mut">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-acc-tx">Firma</h3>
                    @foreach ([$offer->legal_name_snapshot, $offer->address_snapshot, trim(($offer->tax_office_snapshot ?? '').' · '.($offer->tax_number_snapshot ?? ''), ' ·'), $offer->phone_snapshot, $offer->email_snapshot] as $line)
                        @if ($line)<p class="break-words">{{ $line }}</p>@endif
                    @endforeach
                </div>
                <div class="space-y-2 text-sm leading-6 text-mut">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-acc-tx">Banka</h3>
                    @if ($offer->bank_account_holder)<p><span class="font-semibold text-tx">Hesap sahibi:</span> {{ $offer->bank_account_holder }}</p>@endif
                    @if ($offer->bank_iban_try)<p class="break-all"><span class="font-semibold text-tx">TL IBAN:</span> {{ $offer->bank_iban_try }}</p>@endif
                    @if ($offer->bank_iban_eur)<p class="break-all"><span class="font-semibold text-tx">EUR IBAN:</span> {{ $offer->bank_iban_eur }}</p>@endif
                </div>
            </div>
        </section>
    @endif
    @if ($offer->footer_notes)
        <section class="mt-5 rounded-xl border-l-4 border-acc bg-acc-bg px-5 py-5 sm:px-6" aria-label="Kapanış notu">
            <p class="whitespace-pre-line text-sm leading-6 text-tx">{{ $offer->footer_notes }}</p>
        </section>
    @endif
@endsection
