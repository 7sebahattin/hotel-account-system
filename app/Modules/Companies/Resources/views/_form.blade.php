@csrf

<x-ui.errors />

@php $c = $company ?? null; @endphp

<div class="grid gap-4 sm:grid-cols-2">
    @if ($c === null)
        <x-ui.select label="Hesap türü" name="account_type" required>
            <option value="company" @selected(old('account_type', 'company') === 'company')>Firma</option>
            <option value="individual" @selected(old('account_type') === 'individual')>Bireysel</option>
        </x-ui.select>
    @else
        <p class="text-sm text-mut">Hesap türü: {{ $c->account_type->label() }}</p>
    @endif
    <div class="sm:col-span-2">
        <x-ui.input label="Firma ünvanı / Ad soyad" name="name" required maxlength="200" :value="$c->name ?? null" />
    </div>

    <x-ui.input label="Vergi dairesi" name="tax_office" maxlength="100" :value="$c->tax_office ?? null" />
    <x-ui.input label="Vergi numarası" name="tax_number" maxlength="20" inputmode="numeric" :value="$c->tax_number ?? null" />
    <x-ui.input label="Telefon" name="phone" type="tel" maxlength="30" inputmode="tel" :value="$c->phone ?? null" />
    <x-ui.input label="E-posta" name="email" type="email" maxlength="150" inputmode="email" :value="$c->email ?? null" />
    <x-ui.input label="Yetkili kişi" name="contact_person" maxlength="150" :value="$c->contact_person ?? null" />
    <x-ui.input label="Şehir" name="city" maxlength="100" :value="$c->city ?? null" />

    <div class="sm:col-span-2">
        <x-ui.textarea label="Adres" name="address" :rows="2" maxlength="2000" :value="$c->address ?? null" />
    </div>

    <div class="sm:col-span-2">
        <x-ui.textarea label="Notlar" name="notes" :rows="3" maxlength="5000" :value="$c->notes ?? null" />
    </div>

    <div class="sm:col-span-2">
        <x-ui.checkbox label="Hesap aktif" name="is_active" :checked="old('is_active', $c->is_active ?? true)" />
    </div>
</div>
