@extends('layouts.app')
@section('content')<x-ui.page-header title="Günlük puantaj raporu" :subtitle="$date" icon="calendar"><x-slot:actions><x-ui.button variant="secondary" href="{{ route('personnel.reports.daily.print',request()->query()) }}">Yazdır</x-ui.button></x-slot:actions></x-ui.page-header>@include('personnel::reports._daily-table')@endsection
