@extends('layouts.app')
@section('title', 'Makbuz düzenle')

@section('content')
    <x-ui.page-header :title="$entry->receipt_no" subtitle="Makbuzu düzenle — makbuz numarası değişmez" />

    <form method="POST" action="{{ route('receipt-book.update', $entry) }}" class="mt-6 max-w-3xl">
        @method('PUT')

        <x-ui.card class="p-6">
            @include('receipt-book::_form')
        </x-ui.card>
    </form>
@endsection
