@extends('layouts.app')
@section('content')
<x-ui.page-header title="Günlük puantaj" :subtitle="$date" icon="clock">
    <x-slot:actions><x-ui.button variant="secondary" href="{{ route('personnel.reports.daily.print',request()->query()) }}">Yazdır</x-ui.button><x-ui.button variant="secondary" href="{{ route('personnel.reports.monthly') }}">Aylık döküm</x-ui.button></x-slot:actions>
</x-ui.page-header>
<x-ui.card class="mt-5">
    <form class="grid gap-3 sm:grid-cols-3">
        <x-ui.input type="date" name="date" label="Tarih" :value="$date" />
        <x-ui.input type="number" name="employee_id" label="Personel kayıt no" :value="data_get($filters,'employee_id')" />
        <x-ui.input name="department" label="Departman" :value="data_get($filters,'department')" />
        <x-ui.button variant="secondary">Göster</x-ui.button>
    </form>
</x-ui.card>
@include('personnel::reports._daily-table')
@endsection
