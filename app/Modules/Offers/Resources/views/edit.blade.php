@extends('layouts.app')
@section('title', 'Teklifi düzenle')
@section('content')
    <div class="border-b border-line pb-6">
        <a href="{{ route('offers.show', $offer) }}" class="text-sm font-medium text-acc-tx hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-acc-tx">← Teklife dön</a>
        <p class="mt-5 text-xs font-semibold uppercase tracking-[0.18em] text-acc-tx">{{ $offer->offer_no }} · {{ $offer->customer_name_snapshot }}</p>
        <h1 class="mt-2 text-3xl font-semibold tracking-tight text-tx sm:text-4xl">Teklifi düzenle</h1>
        <p class="mt-2 text-sm text-mut">Belge bilgilerini ve fiyat matrisini güncelleyin.</p>
    </div>
    @include('offers::_form')
@endsection
