@extends('layouts.app')
@section('content')
<x-ui.page-header :title="$employee->full_name" :subtitle="$employee->employee_code" icon="users">
    <x-slot:actions>
        <x-ui.button variant="secondary" href="{{ route('personnel.reports.monthly.print',['month'=>$month,'employee_id'=>$employee->id]) }}">Yazdır</x-ui.button>
        @if($permissions['payroll'])<x-ui.button variant="secondary" href="{{ route('personnel.payroll.index',['employee_id'=>$employee->id,'month'=>$month]) }}">Ücret / ödemeler</x-ui.button>@endif
        @if($permissions['manage'])<x-ui.button href="{{ route('personnel.employees.edit',$employee) }}" variant="secondary">Düzenle</x-ui.button>@endif
    </x-slot:actions>
</x-ui.page-header>
<x-ui.card class="mt-5" title="Personel bilgileri">
    <dl class="grid gap-2 text-sm sm:grid-cols-2">
        <div><dt class="text-mut">Durum</dt><dd>{{ $employee->is_active ? 'Aktif' : 'Pasif' }}</dd></div>
        <div><dt class="text-mut">İstihdam</dt><dd>{{ $employee->start_date->format('d.m.Y') }} – {{ $employee->end_date?->format('d.m.Y') ?? 'Devam ediyor' }}</dd></div>
        <div><dt class="text-mut">Departman / görev</dt><dd>{{ $employee->department ?? '—' }} / {{ $employee->position ?? '—' }}</dd></div>
        <div><dt class="text-mut">Telefon</dt><dd>{{ $employee->phone ?? '—' }}</dd></div>
    </dl>
    @if($employee->notes)<p class="mt-3 whitespace-pre-line text-sm">{{ $employee->notes }}</p>@endif
    @if($permissions['manage'])
        <p class="mt-3 text-xs text-mut">Pasife alma kart kullanımını durdurur, istihdam tarihini değiştirmez. İşten ayrılma için Düzenle ekranından çıkış tarihi girin.</p>
        <form method="POST" action="{{ route('personnel.employees.active',$employee) }}" class="mt-4 grid gap-2 sm:grid-cols-[1fr_auto]" data-personnel-confirm="Personel durumu değiştirilsin mi? Geçmiş kayıtlar korunacak.">
            @csrf @method('PATCH')
            <input type="hidden" name="is_active" value="{{ $employee->is_active ? 0 : 1 }}">
            <x-ui.input name="reason" id="active-reason" label="Durum değişikliği notu" maxlength="500" />
            <x-ui.button variant="secondary">{{ $employee->is_active ? 'Pasife al' : 'Etkinleştir' }}</x-ui.button>
        </form>
    @endif
</x-ui.card>
<div class="mt-5 grid gap-5 xl:grid-cols-2">
    <x-ui.card title="Kart ve atama geçmişi">
        @forelse($cards as $assignment)
            <article class="mb-3 rounded-lg border border-line p-3 text-sm">
                <p class="font-semibold">{{ $assignment->card->label ?? 'Personel kartı' }} · {{ $assignment->card->masked_uid }}</p>
                <p class="mt-1 text-mut">{{ $assignment->assigned_at }} · {{ $assignment->released_at ? 'Atama sonlandı: '.$assignment->released_at : 'Etkin atama' }}</p>
                @if($permissions['manage'] && !$assignment->released_at)
                    <form method="POST" action="{{ route('personnel.cards.release',$assignment->card) }}" class="mt-3 grid gap-2" data-personnel-confirm="Kart ataması sonlandırılsın mı?">
                        @csrf
                        <x-ui.input name="reason" id="card-reason-{{ $assignment->id }}" label="Atamayı sonlandırma nedeni" required maxlength="500" />
                        <x-ui.button variant="secondary">Kartı serbest bırak</x-ui.button>
                    </form>
                    <form method="POST" action="{{ route('personnel.cards.active',$assignment->card) }}" class="mt-2" data-personnel-confirm="Kart kullanım durumu değiştirilsin mi?">
                        @csrf @method('PATCH')
                        <input type="hidden" name="is_active" value="{{ $assignment->card->is_active ? 0 : 1 }}">
                        <x-ui.button variant="secondary">{{ $assignment->card->is_active ? 'Kartı devre dışı bırak' : 'Kartı etkinleştir' }}</x-ui.button>
                    </form>
                @endif
            </article>
        @empty
            <p class="text-sm text-mut">Henüz kart atanmamış.</p>
        @endforelse
        <div class="mt-3">{{ $cards->links() }}</div>
        @if($permissions['manage'])
            <form data-personnel-card-form method="POST" action="{{ route('personnel.cards.store',$employee) }}" class="mt-4 grid gap-3">
                @csrf
                <x-ui.input data-personnel-card-reader name="uid" label="Kart UID" required hint="USB okuyucuyu bu alanda okutun; Android kimlik modundaki UID de girilebilir." />
                <x-ui.input name="label" label="Kart etiketi" />
                <x-ui.select name="technology" label="Teknoloji"><option value="">Bilinmiyor / USB HID</option>@foreach(['MifareClassic','NfcA','NdefFormatable','Ndef'] as $tech)<option @selected(old('technology')===$tech)>{{ $tech }}</option>@endforeach</x-ui.select>
                <x-ui.button>Kart ata</x-ui.button>
            </form>
        @endif
    </x-ui.card>
    <x-ui.card title="Vardiya geçmişi">
        @forelse($shiftAssignments as $assignment)
            <p class="mb-2 text-sm">{{ $assignment->effective_from->format('d.m.Y') }} · Sürüm {{ $assignment->version->version }} · {{ $assignment->version->start_time }}–{{ $assignment->version->end_time }}{{ $assignment->version->ends_next_day ? ' (+1 gün)' : '' }}</p>
        @empty
            <p class="text-sm text-mut">Henüz vardiya atanmamış.</p>
        @endforelse
        <div class="mt-3">{{ $shiftAssignments->links() }}</div>
        @if($permissions['manage'])
            <form method="POST" action="{{ route('personnel.shift-assignments.store',$employee) }}" class="mt-4 grid gap-3">
                @csrf
                <x-ui.select name="shift_version_id" label="Vardiya sürümü" required><option value="">Seçin</option>
                    @foreach($shiftVersions as $version)<option value="{{ $version->id }}" @selected(old('shift_version_id')==$version->id)>{{ $version->shift->name }} · v{{ $version->version }} · {{ $version->start_time }}–{{ $version->end_time }}</option>@endforeach
                </x-ui.select>
                <x-ui.input type="date" name="effective_from" id="shift-effective-from" label="Yürürlük tarihi" required hint="Sonraki atamalar en erken yarın geçerlidir." />
                <x-ui.button>Vardiya ata</x-ui.button>
            </form>
        @endif
    </x-ui.card>
</div>
@if($permissions['payroll'])
    <x-ui.card class="mt-5" title="Ücret geçmişi ve yeni ücret">
        <div class="space-y-2 text-sm">
            @forelse($wageAssignments as $wage)
                <p>{{ $wage->effective_from->format('d.m.Y') }} · {{ ['monthly'=>'Aylık','daily'=>'Günlük','hourly'=>'Saatlik'][$wage->wage_type] }} · {{ \App\Modules\Core\Support\Money::fromDecimal($wage->amount)->format(\App\Modules\Core\Enums\Currency::from($wage->currency)) }} · {{ $wage->standard_daily_minutes }} dk · Fazla mesai {{ $wage->overtime_enabled ? 'etkin' : 'kapalı' }}</p>
            @empty
                <p class="text-mut">Henüz ücret atanmamış.</p>
            @endforelse
            {{ $wageAssignments->links() }}
        </div>
        <form method="POST" action="{{ route('personnel.wage-assignments.store',$employee) }}" class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @csrf
            <x-ui.input type="date" name="effective_from" id="wage-effective-from" label="Yürürlük tarihi" required />
            <x-ui.select name="wage_type" label="Ücret tipi">@foreach(['monthly'=>'Aylık','daily'=>'Günlük','hourly'=>'Saatlik'] as $value=>$label)<option value="{{ $value }}" @selected(old('wage_type')===$value)>{{ $label }}</option>@endforeach</x-ui.select>
            <x-ui.input name="amount" label="Ana ücret" inputmode="decimal" required />
            <x-ui.select name="currency" label="Para birimi">@foreach($currencies as $currency)<option value="{{ $currency->value }}" @selected(old('currency','TRY')===$currency->value)>{{ $currency->value }}</option>@endforeach</x-ui.select>
            <x-ui.input type="number" name="standard_daily_minutes" label="Standart günlük dakika" value="480" min="1" max="1440" required />
            <x-ui.input type="number" name="overtime_multiplier_bp" label="Fazla mesai katsayısı (baz puan)" value="15000" min="0" max="100000" hint="10000 = 1 kat, 15000 = 1,5 kat." required />
            <label class="flex min-h-11 items-center gap-2 text-sm"><input type="hidden" name="overtime_enabled" value="0"><input type="checkbox" name="overtime_enabled" value="1" @checked(old('overtime_enabled',true))> Fazla mesai ücretini hesapla</label>
            <x-ui.button>Yeni ücret ata</x-ui.button>
        </form>
    </x-ui.card>
@endif
<x-ui.card class="mt-5" title="Aylık puantaj">
    <form class="mb-4 flex flex-wrap items-end gap-3"><x-ui.input type="month" name="month" label="Ay" :value="$month" /><x-ui.button variant="secondary">Göster</x-ui.button></form>
    <div class="grid gap-3 lg:grid-cols-2">
        @forelse($attendanceRows as $row)
            <article class="rounded-lg border border-line p-3 text-sm">
                <div class="flex justify-between gap-2"><strong>{{ $row['work_date'] }}</strong><span>{{ $row['status_label'] }}</span></div>
                <p class="mt-1 text-mut">{{ $row['shift_label'] }}</p>
                <p class="mt-1">{{ $row['first_in_label'] ?? '—' }} → {{ $row['last_out_label'] ?? '—' }}</p>
                <dl class="mt-2 grid grid-cols-2 gap-1">
                    @foreach(['worked_minutes'=>'Brüt','break_minutes'=>'Mola','net_minutes'=>'Net','late_minutes'=>'Geç','early_minutes'=>'Erken','overtime_minutes'=>'Fazla mesai','missing_minutes'=>'Eksik'] as $key=>$label)
                        <dt class="text-mut">{{ $label }}</dt><dd class="text-right">{{ $row[$key] ?? 0 }} dk</dd>
                    @endforeach
                </dl>
                @if($row['has_correction'])<p class="mt-2 text-xs text-mut">Manuel düzeltme · revizyon {{ $row['revision'] }}</p>@endif
                @if(data_get($row,'post_correction_scan'))<p class="mt-2 text-danger">Düzeltme sonrası okutma var; gün yeniden kontrol edilmeli.</p>@endif
            </article>
        @empty
            <p class="text-sm text-mut">Bu ay istihdam aralığında gün yok.</p>
        @endforelse
    </div>
</x-ui.card>
@if($permissions['attendance'])
    @php
        $correctionMap = collect($attendanceRows)->mapWithKeys(fn($row) => [$row['work_date'] => ['revision'=>$row['revision'],'sessions'=>$row['sessions']]]);
        $oldSessions = old('sessions') ?: [['in_at'=>'','out_at'=>'']];
    @endphp
    <x-ui.card class="mt-5" title="Gerekçeli puantaj düzeltmesi">
        <p class="mb-3 text-sm text-mut">Seçilen günün tüm oturumları aşağıdaki listeyle değiştirilir. Ham okutmalar silinmez.</p>
        <form method="POST" action="{{ route('personnel.attendance-corrections.store',$employee) }}" data-personnel-correction data-correction-map="{{ $correctionMap->toJson() }}" class="grid gap-3" data-personnel-confirm="Günün puantajı yeni revizyonla düzeltilecek. Onaylıyor musunuz?">
            @csrf
            <x-ui.select name="work_date" label="Çalışma günü" required><option value="">Gün seçin</option>@foreach($attendanceRows as $row)<option value="{{ $row['work_date'] }}" @selected(old('work_date')===$row['work_date'])>{{ $row['work_date'] }} · {{ $row['status_label'] }}</option>@endforeach</x-ui.select>
            <input type="hidden" name="expected_revision" value="{{ old('expected_revision',0) }}" data-correction-revision>
            <label class="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" name="clear_day" value="1" data-clear-day @checked(old('clear_day'))> Bu günü boş gün olarak düzelt</label>
            <div data-correction-sessions class="space-y-3">
                @foreach($oldSessions as $index=>$session)
                    <div class="grid min-w-0 gap-2 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]" data-correction-session>
                        <x-ui.input type="datetime-local" name="sessions[{{ $index }}][in_at]" id="correction-in-{{ $index }}" errorKey="sessions.{{ $index }}.in_at" label="Giriş (İstanbul)" :value="$session['in_at']" required />
                        <x-ui.input type="datetime-local" name="sessions[{{ $index }}][out_at]" id="correction-out-{{ $index }}" errorKey="sessions.{{ $index }}.out_at" label="Çıkış (İstanbul)" :value="$session['out_at']" required />
                        <button type="button" data-remove-session class="min-h-11 text-sm text-danger">Oturumu kaldır</button>
                    </div>
                @endforeach
            </div>
            <button type="button" data-add-session class="min-h-11 rounded-lg border border-field text-sm">Oturum ekle</button>
            <x-ui.input name="reason" id="correction-reason" label="Düzeltme gerekçesi" maxlength="500" required />
            <x-ui.button>Düzeltmeyi kaydet</x-ui.button>
        </form>
        <details class="mt-5 text-sm"><summary>Düzeltme geçmişi (son 50 kayıt)</summary>
            @foreach($corrections as $correction)
                <div class="mt-2 rounded border border-line p-3"><strong>{{ $correction->work_date->format('d.m.Y') }} · Rev {{ $correction->revision }}</strong><p>{{ $correction->reason }}</p><p class="text-xs text-mut">Kullanıcı #{{ $correction->created_by }} · {{ $correction->created_at }}</p>
                    @foreach($correction->sessions as $session)<p class="mt-1">{{ \Illuminate\Support\Carbon::parse($session['in_at'])->timezone('Europe/Istanbul')->format('d.m.Y H:i') }} – {{ \Illuminate\Support\Carbon::parse($session['out_at'])->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}</p>@endforeach
                    @if(!$correction->sessions)<p>Boş gün düzeltmesi</p>@endif
                </div>
            @endforeach
        </details>
        <div class="mt-3">{{ $corrections->links() }}</div>
    </x-ui.card>
@endif
@endsection
