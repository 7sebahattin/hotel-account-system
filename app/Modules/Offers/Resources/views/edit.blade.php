@extends('layouts.app')
@section('title', 'Teklifi düzenle')
@section('content')
    <x-ui.page-header title="Teklifi Düzenle" subtitle="{{ $offer->offer_no }} · {{ $offer->customer_name_snapshot }}" />
    @include('offers::_form')
@endsection
