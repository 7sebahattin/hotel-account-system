@extends('layouts.app')
@section('title', 'Yeni teklif')
@section('content')
    <div class="border-b border-line pb-6">
        <a href="{{ route('offers.index') }}" class="text-sm font-medium text-acc-tx hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-acc-tx">← Teklifler</a>
        <p class="mt-5 text-xs font-semibold uppercase tracking-[0.18em] text-acc-tx">Konaklama · Fiyat belgesi</p>
        <h1 class="mt-2 text-3xl font-semibold tracking-tight text-tx sm:text-4xl">Yeni teklif</h1>
        <p class="mt-2 max-w-2xl text-sm text-mut">Müşteriye sunulacak fiyatları, koşulları ve belge bilgilerini hazırlayın.</p>
    </div>
    @include('offers::_form', ['offer' => null])
@endsection
