@extends('layouts.app')
@section('content')
<x-ui.page-header title="Aylık puantaj raporu" :subtitle="$month" icon="calendar">
    <x-slot:actions><x-ui.button variant="secondary" href="{{ route('personnel.reports.monthly.print',request()->query()) }}">Yazdır</x-ui.button></x-slot:actions>
</x-ui.page-header>
<x-ui.card class="mt-5">
    <form class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.input type="month" name="month" label="Ay" :value="$month" />
        <x-ui.input type="number" name="employee_id" label="Personel kayıt no" :value="data_get($filters,'employee_id')" />
        <x-ui.input name="department" label="Departman" :value="data_get($filters,'department')" />
        <x-ui.select name="active" label="Durum"><option value="all">Tümü</option><option value="1" @selected(data_get($filters,'active')==='1')>Aktif</option><option value="0" @selected(data_get($filters,'active')==='0')>Pasif</option></x-ui.select>
        <x-ui.button variant="secondary">Filtrele</x-ui.button>
    </form>
</x-ui.card>
<div class="mt-5 grid gap-4 lg:grid-cols-2">
    @forelse($employees as $employee)
        <x-ui.card :title="$employee->full_name">
            <p class="text-xs text-mut">{{ $employee->employee_code }} · {{ $employee->department }}</p>
            <dl class="mt-3 grid grid-cols-2 gap-2 text-sm">
                @foreach(['worked_minutes'=>'Brüt çalışma','break_minutes'=>'Mola','net_minutes'=>'Net çalışma','late_minutes'=>'Geç kalma','early_minutes'=>'Erken çıkma','overtime_minutes'=>'Fazla mesai','missing_minutes'=>'Eksik çalışma'] as $key=>$label)
                    <dt class="text-mut">{{ $label }}</dt><dd class="text-right tabular-nums">{{ data_get($totalsByEmployee,$employee->id.'.'.$key,0) }} dk</dd>
                @endforeach
                <dt class="text-mut">Devamsızlık</dt><dd class="text-right">{{ data_get($totalsByEmployee,$employee->id.'.absent_days',0) }} gün</dd>
            </dl>
            @if(data_get($permissions,'payroll'))
                <div class="mt-3 border-t border-line pt-3 text-sm">
                    @forelse(($earningsByCurrency[$employee->id] ?? []) as $currency=>$money)
                        <p class="flex justify-between gap-2"><span>Hakediş · {{ $currency }}</span><strong>{{ $money['formatted'] }}</strong></p>
                    @empty
                        <p class="text-mut">Kaydedilmiş hakediş yok.</p>
                    @endforelse
                    @foreach(($paymentsByCurrency[$employee->id] ?? []) as $currency=>$money)
                        <p class="flex justify-between gap-2"><span>Net ödeme · {{ $currency }}</span><strong>{{ $money['formatted'] }}</strong></p>
                    @endforeach
                    @foreach(['bonusesByCurrency'=>'Prim', 'deductionsByCurrency'=>'Kesinti'] as $group=>$label)
                        @foreach(data_get(get_defined_vars(),$group.'.'.$employee->id,[]) as $currency=>$money)
                            <p class="flex justify-between gap-2"><span>{{ $label }} · {{ $currency }}</span><strong>{{ $money['formatted'] }}</strong></p>
                        @endforeach
                    @endforeach
                    <x-ui.button class="mt-2" variant="secondary" href="{{ route('personnel.payroll.index',['employee_id'=>$employee->id,'month'=>$month]) }}">Bakiyeler / ödemeler</x-ui.button>
                </div>
            @endif
            <x-ui.button class="mt-3" variant="secondary" href="{{ route('personnel.employees.show',['employee'=>$employee,'month'=>$month]) }}">Günlük ayrıntı</x-ui.button>
        </x-ui.card>
    @empty
        <p class="text-sm text-mut">Kayıt bulunamadı.</p>
    @endforelse
</div>
<div class="mt-4">{{ $employees->links() }}</div>
@endsection
