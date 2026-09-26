@extends('layouts.app')
@section('content')
<x-ui.page-header title="Personel Takibi" subtitle="Günlük devam ve çalışma özeti" icon="users">
    <x-slot:actions><x-ui.button variant="secondary" href="{{ route('personnel.employees.index') }}">Personeller</x-ui.button><x-ui.button variant="secondary" href="{{ route('personnel.shifts.index') }}">Vardiyalar</x-ui.button><x-ui.button variant="secondary" href="{{ route('personnel.attendance.index') }}">Günlük puantaj</x-ui.button><x-ui.button variant="secondary" href="{{ route('personnel.reports.monthly') }}">Aylık döküm</x-ui.button></x-slot:actions>
</x-ui.page-header>
<div class="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
    @foreach(['active_employees'=>'Aktif personel','present_count'=>'Bugün gelen','currently_inside_count'=>'İçeride','absent_count'=>'Devamsız','late_count'=>'Geç kalan','early_count'=>'Erken çıkan','overtime_minutes'=>'Fazla mesai (dk)','missing_entries_count'=>'Açık oturum'] as $key=>$label)
        <x-ui.kpi-card :label="$label" :value="data_get($summary,$key,0)" />
    @endforeach
</div>
@if(data_get($permissions,'payroll'))
    <x-ui.card class="mt-5" title="Bu ay net personel ödemeleri">
        @forelse(($thisMonthPaymentsByCurrency ?? []) as $money)<p class="font-semibold">{{ $money['formatted'] }}</p>@empty<p class="text-sm text-mut">Kayıtlı ödeme yok.</p>@endforelse
        <x-ui.button class="mt-3" variant="secondary" href="{{ route('personnel.payroll.index') }}">Hakediş ve bakiyeler</x-ui.button>
    </x-ui.card>
@endif
@include('personnel::reports._daily-table')
@endsection
