@extends('layouts.print')
@section('title','Aylık personel dökümü')
@section('content')
<div class="toolbar"><button onclick="window.print()">Yazdır</button><a href="{{ route('personnel.reports.monthly',request()->query()) }}">Geri</a></div>
<header class="head"><h1>Lemon · Aylık personel dökümü</h1><p>{{ $month }} · Süreler dakika cinsindedir.</p></header>
<div class="scroll-x"><table><thead><tr><th>Personel</th><th class="num">Net</th><th class="num">Mola</th><th class="num">Geç</th><th class="num">Erken</th><th class="num">Fazla</th><th class="num">Eksik</th></tr></thead><tbody>
@foreach($employees as $employee)
<tr><td>{{ $employee->full_name }}<br>{{ $employee->employee_code }}</td>
@foreach(['net_minutes','break_minutes','late_minutes','early_minutes','overtime_minutes','missing_minutes'] as $key)<td class="num">{{ data_get($totalsByEmployee,$employee->id.'.'.$key,0) }}</td>@endforeach
</tr>
@endforeach
</tbody></table></div>
@if(data_get($permissions,'payroll'))
<section class="print-section"><h2>Kaydedilmiş personel finansı</h2><div class="scroll-x"><table><thead><tr><th>Personel</th><th>Tür</th><th>Para birimi</th><th class="num">Net tutar</th></tr></thead><tbody>
@foreach($employees as $employee)
@foreach(['earningsByCurrency'=>'Hakediş','bonusesByCurrency'=>'Prim','deductionsByCurrency'=>'Kesinti','paymentsByCurrency'=>'Ödeme'] as $group=>$label)
@foreach(data_get(get_defined_vars(),$group.'.'.$employee->id,[]) as $currency=>$money)
<tr><td>{{ $employee->full_name }}</td><td>{{ $label }}</td><td>{{ $currency }}</td><td class="num">{{ $money['formatted'] }}</td></tr>
@endforeach
@endforeach
@endforeach
</tbody></table></div></section>
@endif
@if(!empty($filters['employee_id']))
@foreach($employees as $employee)
<section class="print-section"><h2>{{ $employee->full_name }} · Günlük ayrıntı</h2><div class="scroll-x"><table><thead><tr><th>Tarih</th><th>Giriş / çıkış</th><th>Durum</th><th class="num">Net</th><th class="num">Fazla</th></tr></thead><tbody>
@foreach(($rows[$employee->id] ?? []) as $row)
<tr><td>{{ $row['work_date'] }}</td><td>{{ $row['first_in_label'] ?? '—' }}<br>{{ $row['last_out_label'] ?? '—' }}</td><td>{{ $row['status_label'] }}{{ $row['has_correction'] ? ' *' : '' }}</td><td class="num">{{ $row['net_minutes'] }}</td><td class="num">{{ $row['overtime_minutes'] }}</td></tr>
@endforeach
</tbody></table></div></section>
@endforeach
@endif
<footer class="foot"><span>* Manuel düzeltme. Bu rapor yasal bordro değildir.</span><span>{{ now('Europe/Istanbul')->format('d.m.Y H:i') }}</span></footer>
@endsection
