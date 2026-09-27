@extends('layouts.app')
@section('content')
<x-ui.page-header title="Ücret ve ödemeler" :subtitle="$month" icon="cash" />
<x-ui.card class="mt-5">
    <form class="grid gap-3 sm:grid-cols-3">
        <x-ui.input type="month" name="month" label="Ay" :value="$month" />
        <x-ui.input type="number" name="employee_id" label="Personel kayıt no" :value="data_get($filters,'employee_id')" />
        <x-ui.select name="currency" label="Para birimi"><option value="">Tümü</option>
            @foreach(\App\Modules\Core\Enums\Currency::active() as $currency)
                <option value="{{ $currency->value }}" @selected(data_get($filters,'currency')===$currency->value)>{{ $currency->value }}</option>
            @endforeach
        </x-ui.select>
        <x-ui.button variant="secondary">Filtrele</x-ui.button>
    </form>
</x-ui.card>
<x-ui.card class="mt-5" title="Personel seçimi">
    <div class="flex flex-wrap gap-2">
        @foreach($employees as $person)
            <x-ui.button variant="secondary" href="{{ route('personnel.payroll.index',['month'=>$month,'employee_id'=>$person->id]) }}">{{ $person->full_name }}</x-ui.button>
        @endforeach
    </div>
    <div class="mt-3">{{ $employees->links() }}</div>
</x-ui.card>
<div class="mt-5 grid gap-3 lg:grid-cols-2">
    @forelse($rows as $row)
        <x-ui.card :title="$row['full_name'].' · '.$row['currency']">
            <dl class="grid grid-cols-2 gap-2 text-sm">
                @foreach(['earned'=>'Hakediş','bonus'=>'Prim / ek ödeme','deduction'=>'Kesinti','paid'=>'Net ödeme','period_balance'=>'Bu ay kalan','balance'=>'Genel kalan bakiye'] as $key=>$label)
                    <dt class="text-mut">{{ $label }}</dt><dd class="min-w-0 break-words text-right font-semibold tabular-nums">{{ $row[$key]['formatted'] }}</dd>
                @endforeach
            </dl>
        </x-ui.card>
    @empty
        <p class="text-sm text-mut">Seçilen dönemde kayıtlı finansal hareket yok.</p>
    @endforelse
</div>
@if($selectedEmployee)
    <div class="mt-5 grid gap-5 xl:grid-cols-2">
        <x-ui.card :title="$selectedEmployee->full_name.' · Hakediş önizleme'">
            @if($preview)
                <p class="text-sm text-mut">{{ count($preview['days']) }} hesap günü · formül {{ $preview['formula_version'] }}. Önizleme henüz finansal kayıt değildir.</p>
                @foreach($preview['earnings'] as $currency=>$amount)
                    <p class="mt-2 font-semibold">{{ \App\Modules\Core\Support\Money::fromDecimal($amount)->format(\App\Modules\Core\Enums\Currency::from($currency)) }}</p>
                @endforeach
                @if($preview['issues'])
                    <details class="mt-3 text-sm text-danger"><summary>Hesap kesinleştirilemiyor: {{ count($preview['issues']) }} uyarı</summary><ul class="mt-2 list-disc pl-5">@foreach($preview['issues'] as $issue)<li>{{ $issue }}</li>@endforeach</ul></details>
                @endif
                <form method="POST" action="{{ route('personnel.payroll.calculate',$selectedEmployee) }}" class="mt-4" data-personnel-confirm="Hakediş finansal deftere kaydedilsin mi?">
                    @csrf
                    <input data-personnel-request-id type="hidden" name="request_id">
                    <input type="hidden" name="month" value="{{ $month }}">
                    <input type="hidden" name="input_digest" value="{{ $preview['digest'] }}">
                    <x-ui.button :disabled="count($preview['issues']) > 0">Hakedişi kaydet</x-ui.button>
                </form>
                <details class="mt-4 text-sm"><summary>Günlük hesap ayrıntısı</summary>
                    @foreach($preview['days'] as $day)
                        <div class="mt-2 rounded border border-line p-2">{{ $day['date'] }} · {{ $day['net_minutes'] }} dk · {{ $day['currency'] }}<br>Normal: {{ \App\Modules\Core\Support\Money::fromMinorUnits($day['base_minor'])->toDecimal() }} · Kesinti: {{ \App\Modules\Core\Support\Money::fromMinorUnits($day['deduction_minor'])->toDecimal() }} · Fazla mesai: {{ \App\Modules\Core\Support\Money::fromMinorUnits($day['overtime_minor'])->toDecimal() }}</div>
                    @endforeach
                    <p class="mt-2 text-mut">Aylık ana ücret takvim günü segmenti olarak ayrıca eklenir.</p>
                </details>
            @endif
        </x-ui.card>
        <x-ui.card title="Ödeme / prim / kesinti">
            <form method="POST" action="{{ route('personnel.payroll.entries.store',$selectedEmployee) }}" class="grid gap-3" data-personnel-confirm="Bu finansal hareket kaydedilsin mi?">
                @csrf
                <input data-personnel-request-id type="hidden" name="request_id" value="{{ old('request_id') }}">
                <x-ui.select name="type" label="İşlem türü"><option value="payment" @selected(old('type','payment')==='payment')>Ödeme</option><option value="bonus" @selected(old('type')==='bonus')>Prim / ek ödeme</option><option value="deduction" @selected(old('type')==='deduction')>Kesinti</option></x-ui.select>
                <x-ui.input type="date" name="entry_date" label="İşlem tarihi" :value="today('Europe/Istanbul')->toDateString()" required />
                <x-ui.input name="amount" label="Tutar" inputmode="decimal" required />
                <x-ui.select name="currency" id="entry-currency" label="Para birimi">@foreach(\App\Modules\Core\Enums\Currency::active() as $currency)<option value="{{ $currency->value }}" @selected(old('currency',data_get($filters,'currency','TRY'))===$currency->value)>{{ $currency->value }}</option>@endforeach</x-ui.select>
                <x-ui.select name="payment_method" label="Ödeme yöntemi"><option value="cash">Nakit</option><option value="bank_transfer">Havale / EFT</option><option value="other">Diğer</option></x-ui.select>
                <x-ui.input name="description" label="Açıklama / gerekçe" maxlength="500" required />
                <x-ui.button>Hareketi kaydet</x-ui.button>
            </form>
        </x-ui.card>
    </div>
    <x-ui.card class="mt-5" title="Finansal hareket geçmişi">
        <div class="space-y-3">
            @forelse($entries as $entry)
                <article class="rounded-lg border border-line p-3 text-sm">
                    <div class="flex flex-wrap justify-between gap-2"><strong>{{ $entry->entry_date->format('d.m.Y') }} · {{ ['earning'=>'Hakediş','bonus'=>'Prim','deduction'=>'Kesinti','payment'=>'Ödeme','reversal'=>'Ters kayıt'][$entry->type] }}</strong><strong>{{ \App\Modules\Core\Support\Money::fromDecimal($entry->amount)->format(\App\Modules\Core\Enums\Currency::from($entry->currency)) }}</strong></div>
                    <p class="mt-1 break-words">{{ $entry->description }}</p>
                    <p class="mt-1 text-xs text-mut">Kayıt #{{ $entry->id }} · Kullanıcı #{{ $entry->created_by }} · {{ $entry->created_at }}@if($entry->reverses_entry_id) · Ters çevrilen #{{ $entry->reverses_entry_id }}@endif</p>
                    @if($entry->type !== 'reversal' && !($entry->reversal_exists ?? false))
                        <details class="mt-3"><summary class="text-danger">İptal / ters kayıt</summary>
                            <form method="POST" action="{{ route('personnel.payroll.entries.reverse',$entry) }}" class="mt-2 grid gap-2 sm:grid-cols-[1fr_auto]" data-personnel-confirm="Hareket silinmeden ters kayıt oluşturulacak. Onaylıyor musunuz?">
                                @csrf
                                <input data-personnel-request-id type="hidden" name="request_id">
                                <x-ui.input name="reason" id="reverse-reason-{{ $entry->id }}" label="İptal gerekçesi" required maxlength="500" />
                                <x-ui.button variant="secondary">Ters kayıt oluştur</x-ui.button>
                            </form>
                        </details>
                    @else
                        <p class="mt-2 text-mut">Bu hareket için tekrar iptal işlemi yapılamaz.</p>
                    @endif
                </article>
            @empty
                <p class="text-sm text-mut">Finansal hareket yok.</p>
            @endforelse
        </div>
        <div class="mt-4">{{ $entries->links() }}</div>
    </x-ui.card>
@endif
@endsection
