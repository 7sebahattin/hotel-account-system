@extends('layouts.app')
@section('title', 'Günlük Hesap Ekle')
@section('content')
    <x-ui.page-header title="Günlük Hesap Ekle" :subtitle="$owner->name.' · Aylık Hesap'" />
    <form method="POST" action="{{ route('monthly-account.store') }}" class="monthly-account monthly-account-form mt-3" data-monthly-account-form>
        @include('monthly-account::_form', ['day' => null])
    </form>
@endsection
