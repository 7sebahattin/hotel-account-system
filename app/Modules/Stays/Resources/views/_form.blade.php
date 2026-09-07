@csrf

@if ($errors->any())
    <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        <ul class="list-inside list-disc space-y-1">
            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif

@php $s = $stay ?? null; @endphp

<div class="grid gap-4 sm:grid-cols-2">
    <div class="sm:col-span-2">
        <label for="company_id" class="block text-sm font-medium text-slate-700">
            Firma <span class="text-red-600">*</span>
        </label>
        <select id="company_id" name="company_id" required
                class="mt-1 block min-h-11 w-full rounded-md border border-slate-300 px-3 text-sm">
            <option value="">Seçiniz…</option>
            @foreach ($companies as $id => $name)
                <option value="{{ $id }}"
                    @selected((string) old('company_id', $s->company_id ?? ($selectedCompanyId ?? '')) === (string) $id)>
                    {{ $name }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <label for="guest_name" class="block text-sm font-medium text-slate-700">
            Misafir adı <span class="text-red-600">*</span>
        </label>
        <input id="guest_name" name="guest_name" type="text" required maxlength="150"
               value="{{ old('guest_name', $s->guest_name ?? '') }}"
               class="mt-1 block min-h-11 w-full rounded-md border border-slate-300 px-3 text-sm">
    </div>

    <div>
        <label for="room_no" class="block text-sm font-medium text-slate-700">Oda</label>
        <input id="room_no" name="room_no" type="text" maxlength="20"
               value="{{ old('room_no', $s->room_no ?? '') }}"
               class="mt-1 block min-h-11 w-full rounded-md border border-slate-300 px-3 text-sm">
    </div>

    <div>
        <label for="check_in_date" class="block text-sm font-medium text-slate-700">
            Giriş tarihi <span class="text-red-600">*</span>
        </label>
        <input id="check_in_date" name="check_in_date" type="date" required
               value="{{ old('check_in_date', $s?->check_in_date?->toDateString() ?? '') }}"
               class="mt-1 block min-h-11 w-full rounded-md border border-slate-300 px-3 text-sm">
    </div>

    <div>
        <label for="check_out_date" class="block text-sm font-medium text-slate-700">
            Çıkış tarihi <span class="text-red-600">*</span>
        </label>
        <input id="check_out_date" name="check_out_date" type="date" required
               value="{{ old('check_out_date', $s?->check_out_date?->toDateString() ?? '') }}"
               class="mt-1 block min-h-11 w-full rounded-md border border-slate-300 px-3 text-sm">
        <p class="mt-1 text-xs text-slate-500">Gece sayısı tarihlerden otomatik hesaplanır.</p>
    </div>

    <div>
        <label for="amount" class="block text-sm font-medium text-slate-700">
            Tutar <span class="text-red-600">*</span>
        </label>
        <input id="amount" name="amount" type="text" inputmode="decimal" required
               value="{{ old('amount', $s->amount ?? '') }}" placeholder="9000,00"
               class="mt-1 block min-h-11 w-full rounded-md border border-slate-300 px-3 text-sm tabular-nums">
    </div>

    <div>
        <label for="currency" class="block text-sm font-medium text-slate-700">
            Para birimi <span class="text-red-600">*</span>
        </label>
        <select id="currency" name="currency" required
                class="mt-1 block min-h-11 w-full rounded-md border border-slate-300 px-3 text-sm">
            @foreach ($currencies as $currency)
                <option value="{{ $currency->value }}"
                    @selected(old('currency', $s?->currency?->value ?? 'TRY') === $currency->value)>
                    {{ $currency->value }} — {{ $currency->label() }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="sm:col-span-2">
        <label for="notes" class="block text-sm font-medium text-slate-700">Notlar</label>
        <textarea id="notes" name="notes" rows="3" maxlength="5000"
                  class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm">{{ old('notes', $s->notes ?? '') }}</textarea>
    </div>
</div>

@isset($stay)
    <p class="mt-4 rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800">
        Firma, tarih, tutar veya para birimi değişirse mevcut cari hareketi ters kaydedilir ve
        yeni bir borç hareketi oluşturulur. Eski hareket silinmez.
    </p>
@endisset
