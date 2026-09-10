@extends('layouts.app')
@section('title', 'Yeni makbuz')

@section('content')
    <x-ui.page-header title="Yeni Makbuz" subtitle="Tahsilat Makbuzu Defteri — yalnızca yazdırma amaçlıdır" />

    <form method="POST" action="{{ route('receipt-book.store') }}" class="mt-6 max-w-3xl">
        <x-ui.card class="p-6">
            @include('receipt-book::_form')
        </x-ui.card>
    </form>
@endsection
