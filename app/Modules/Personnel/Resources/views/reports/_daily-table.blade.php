<div class="mt-5 grid gap-3 lg:grid-cols-2">
@forelse($rows as $row)
    <x-ui.card :title="$row['full_name']">
        <p class="text-xs text-mut">{{ $row['employee_code'] }} · {{ $row['shift_label'] }}</p>
        <p class="mt-2 text-sm">{{ $row['first_in_label'] ?? '—' }} → {{ $row['last_out_label'] ?? '—' }}</p>
        <p class="mt-1 text-sm font-semibold">{{ $row['status_label'] }}{{ $row['has_correction'] ? ' · Düzeltilmiş' : '' }}</p>
        <dl class="mt-3 grid grid-cols-2 gap-1 text-sm">
            @foreach(['worked_minutes'=>'Brüt','break_minutes'=>'Mola','net_minutes'=>'Net','late_minutes'=>'Geç kalma','early_minutes'=>'Erken çıkış','overtime_minutes'=>'Fazla mesai','missing_minutes'=>'Eksik çalışma'] as $key=>$label)
                <dt class="text-mut">{{ $label }}</dt><dd class="text-right">{{ $row[$key] ?? 0 }} dk</dd>
            @endforeach
        </dl>
        @if(data_get($row,'post_correction_scan'))<p class="mt-2 text-sm text-danger">Düzeltme sonrası okutma var; yeniden kontrol gerekli.</p>@endif
        <x-ui.button class="mt-3" variant="secondary" href="{{ route('personnel.employees.show',['employee'=>$row['employee_id'],'month'=>substr($date,0,7)]) }}">Ayrıntı / düzeltme</x-ui.button>
    </x-ui.card>
@empty
    <p class="text-sm text-mut">Bu filtrede kayıt yok.</p>
@endforelse
</div>
<div class="mt-4">{{ $employees->links() }}</div>
