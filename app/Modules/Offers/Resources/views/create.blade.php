@extends('layouts.app')
@section('title', 'Yeni teklif')
@section('content')
    <x-ui.page-header title="Yeni Teklif" subtitle="Konaklama fiyatlarını düzenli bir belgeye dönüştürün." />
    @include('offers::_form', ['offer' => null])
@endsection
