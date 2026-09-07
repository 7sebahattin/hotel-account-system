@csrf

<x-ui.errors />

@php $s = $stay ?? null; @endphp

<div class="grid gap-4 sm:grid-cols-2">
    <div class="sm:col-span-2">
        <x-ui.select label="Firma" name="company_id" required>
            <option value="">Seçiniz…</option>
            @foreach ($companies as $id => $name)
                <option value="{{ $id }}"
                    @selected((string) old('company_id', $s->company_id ?? ($selectedCompanyId ?? '')) === (string) $id)>
                    {{ $name }}
                </option>
            @endforeach
        </x-ui.select>
    </div>

    <x-ui.input label="Misafir adı" name="guest_name" required maxlength="150" :value="$s->guest_name ?? null" />
    <x-ui.input label="Oda" name="room_no" maxlength="20" :value="$s->room_no ?? null" />

    <x-ui.input label="Giriş tarihi" name="check_in_date" type="date" required
                :value="$s?->check_in_date?->toDateString()" />

    <x-ui.input label="Çıkış tarihi" name="check_out_date" type="date" required
                :value="$s?->check_out_date?->toDateString()"
                hint="Gece sayısı tarihlerden otomatik hesaplanır." />

    <x-ui.input label="Tutar" name="amount" required inputmode="decimal"
                placeholder="9000,00" :value="$s->amount ?? null" class="tabular-nums" />

    <x-ui.select label="Para birimi" name="currency" required>
        @foreach ($currencies as $currency)
            <option value="{{ $currency->value }}"
                @selected(old('currency', $s?->currency?->value ?? 'TRY') === $currency->value)>
                {{ $currency->value }} — {{ $currency->label() }}
            </option>
        @endforeach
    </x-ui.select>

    <div class="sm:col-span-2">
        <x-ui.textarea label="Notlar" name="notes" :rows="3" maxlength="5000" :value="$s->notes ?? null" />
    </div>
</div>

@isset($stay)
    <x-ui.alert tone="info" class="mt-4">
        Firma, tarih, tutar veya para birimi değişirse mevcut cari hareketi ters kaydedilir ve
        yeni bir borç hareketi oluşturulur. Eski hareket silinmez.
    </x-ui.alert>
@endisset
