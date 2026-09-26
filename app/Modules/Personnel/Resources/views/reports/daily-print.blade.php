@extends('layouts.print')
@section('title','Günlük puantaj')
@section('content')
<div class="toolbar"><button onclick="window.print()">Yazdır</button><a href="{{ route('personnel.attendance.index',request()->query()) }}">Geri</a></div>
<header class="head"><h1>Lemon · Günlük puantaj</h1><p>{{ $date }} · Süreler dakika cinsindedir.</p></header>
<div class="scroll-x"><table><thead><tr><th>Personel</th><th>Giriş / çıkış</th><th>Durum</th><th class="num">Net</th><th class="num">Geç</th><th class="num">Erken</th><th class="num">Fazla</th></tr></thead><tbody>
@forelse($rows as $row)
<tr><td>{{ $row['full_name'] }}<br>{{ $row['employee_code'] }}</td><td>{{ $row['first_in_label'] ?? '—' }}<br>{{ $row['last_out_label'] ?? '—' }}</td><td>{{ $row['status_label'] }}{{ $row['has_correction'] ? ' *' : '' }}</td><td class="num">{{ $row['net_minutes'] }}</td><td class="num">{{ $row['late_minutes'] }}</td><td class="num">{{ $row['early_minutes'] }}</td><td class="num">{{ $row['overtime_minutes'] }}</td></tr>
@empty<tr><td colspan="7">Kayıt yok.</td></tr>@endforelse
</tbody></table></div>
<footer class="foot"><span>* Manuel düzeltme. Ham kayıtlar korunur.</span><span>{{ now('Europe/Istanbul')->format('d.m.Y H:i') }}</span></footer>
@endsection
