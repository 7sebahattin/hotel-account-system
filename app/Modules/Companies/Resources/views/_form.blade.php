@csrf

@if ($errors->any())
    <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        <ul class="list-inside list-disc space-y-1">
            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif

@php $c = $company ?? null; @endphp

<div class="grid gap-4 sm:grid-cols-2">
    <div class="sm:col-span-2">
        <label for="name" class="block text-sm font-medium text-slate-700">
            Firma ünvanı <span class="text-red-600">*</span>
        </label>
        <input id="name" name="name" type="text" required maxlength="200"
               value="{{ old('name', $c->name ?? '') }}"
               class="mt-1 block min-h-11 w-full rounded-md border border-slate-300 px-3 text-sm">
    </div>

    <div>
        <label for="tax_office" class="block text-sm font-medium text-slate-700">Vergi dairesi</label>
        <input id="tax_office" name="tax_office" type="text" maxlength="100"
               value="{{ old('tax_office', $c->tax_office ?? '') }}"
               class="mt-1 block min-h-11 w-full rounded-md border border-slate-300 px-3 text-sm">
    </div>

    <div>
        <label for="tax_number" class="block text-sm font-medium text-slate-700">Vergi numarası</label>
        <input id="tax_number" name="tax_number" type="text" inputmode="numeric" maxlength="20"
               value="{{ old('tax_number', $c->tax_number ?? '') }}"
               class="mt-1 block min-h-11 w-full rounded-md border border-slate-300 px-3 text-sm">
    </div>

    <div>
        <label for="phone" class="block text-sm font-medium text-slate-700">Telefon</label>
        <input id="phone" name="phone" type="tel" inputmode="tel" maxlength="30"
               value="{{ old('phone', $c->phone ?? '') }}"
               class="mt-1 block min-h-11 w-full rounded-md border border-slate-300 px-3 text-sm">
    </div>

    <div>
        <label for="email" class="block text-sm font-medium text-slate-700">E-posta</label>
        <input id="email" name="email" type="email" inputmode="email" maxlength="150"
               value="{{ old('email', $c->email ?? '') }}"
               class="mt-1 block min-h-11 w-full rounded-md border border-slate-300 px-3 text-sm">
    </div>

    <div>
        <label for="contact_person" class="block text-sm font-medium text-slate-700">Yetkili kişi</label>
        <input id="contact_person" name="contact_person" type="text" maxlength="150"
               value="{{ old('contact_person', $c->contact_person ?? '') }}"
               class="mt-1 block min-h-11 w-full rounded-md border border-slate-300 px-3 text-sm">
    </div>

    <div>
        <label for="city" class="block text-sm font-medium text-slate-700">Şehir</label>
        <input id="city" name="city" type="text" maxlength="100"
               value="{{ old('city', $c->city ?? '') }}"
               class="mt-1 block min-h-11 w-full rounded-md border border-slate-300 px-3 text-sm">
    </div>

    <div class="sm:col-span-2">
        <label for="address" class="block text-sm font-medium text-slate-700">Adres</label>
        <textarea id="address" name="address" rows="2" maxlength="2000"
                  class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm">{{ old('address', $c->address ?? '') }}</textarea>
    </div>

    <div class="sm:col-span-2">
        <label for="notes" class="block text-sm font-medium text-slate-700">Notlar</label>
        <textarea id="notes" name="notes" rows="3" maxlength="5000"
                  class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm">{{ old('notes', $c->notes ?? '') }}</textarea>
    </div>

    <label class="flex items-center gap-2 text-sm text-slate-700 sm:col-span-2">
        <input type="checkbox" name="is_active" value="1"
               @checked(old('is_active', $c->is_active ?? true))
               class="size-4 rounded border-slate-300">
        Firma aktif
    </label>
</div>
