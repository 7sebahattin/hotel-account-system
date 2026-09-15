@extends('layouts.app')
@section('title', 'Günlük Hesabı Düzenle')
@section('content')
    <x-ui.page-header title="Günlük Hesabı Düzenle" :subtitle="$day->account_date->format('d.m.Y')" />
    @if ($day->isDeleted())
        <x-ui.alert tone="info" class="mt-4">Bu gün silinmiş olarak saklanıyor. Kaydettiğinizde yeniden aylık hesaba dahil edilir.</x-ui.alert>
    @endif
    <form method="POST" action="{{ route('monthly-account.update', $day) }}" class="monthly-account monthly-account-form mt-3" data-monthly-account-form>
        @include('monthly-account::_form')
    </form>
@endsection
