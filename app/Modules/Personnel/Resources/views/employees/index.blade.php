@extends('layouts.app')
@section('content')
<x-ui.page-header title="Personel" :count="$employees->total()" icon="users">
    <x-slot:actions>@if($permissions['manage'])<x-ui.button href="{{ route('personnel.employees.create') }}">Personel ekle</x-ui.button>@endif</x-slot:actions>
</x-ui.page-header>
<x-ui.card class="mt-5">
    <form class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.input name="q" label="Ad veya kod ara" :value="data_get($filters,'q')" />
        <x-ui.select name="active" label="Durum"><option value="all">Tümü</option><option value="1" @selected(data_get($filters,'active')==='1')>Aktif</option><option value="0" @selected(data_get($filters,'active')==='0')>Pasif</option></x-ui.select>
        <x-ui.select name="department" label="Departman"><option value="">Tümü</option>@foreach($departments as $department)<option value="{{ $department }}" @selected(data_get($filters,'department')===$department)>{{ $department }}</option>@endforeach</x-ui.select>
        <x-ui.button variant="secondary">Filtrele</x-ui.button>
    </form>
</x-ui.card>
<div class="mt-5 grid gap-3 lg:grid-cols-2">
    @forelse($employees as $employee)
        <a href="{{ route('personnel.employees.show',$employee) }}" class="block rounded-xl border border-line bg-card p-4 transition-colors hover:bg-well focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-acc">
            <div class="flex justify-between gap-2"><strong>{{ $employee->full_name }}</strong><span class="text-xs text-mut">{{ $employee->is_active ? 'Aktif' : 'Pasif' }}</span></div>
            <span class="mt-1 block text-sm text-mut">{{ $employee->employee_code }} · {{ $employee->department ?? '—' }} · {{ $employee->position ?? '—' }}</span>
        </a>
    @empty
        <p class="text-sm text-mut">Personel kaydı yok.</p>
    @endforelse
</div>
<div class="mt-4">{{ $employees->links() }}</div>
@endsection
