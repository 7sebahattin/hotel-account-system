@extends('layouts.app')
@section('title', 'Kasa hareketi ekle')

@section('content')
    <x-ui.page-header title="Hareket ekle"
                      :subtitle="$session->openedBy?->name.' vardiyası'" />

    <x-ui.card class="mt-4">
        @include('cashbook::movements._form', ['categories' => $categories, 'currencies' => $currencies, 'selectedCategoryId' => $selectedCategoryId ?? null])
    </x-ui.card>
@endsection
