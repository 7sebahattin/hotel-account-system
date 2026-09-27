@php $editing = isset($employee) && $employee; @endphp
<x-ui.errors />
<div class="grid gap-4 sm:grid-cols-2">
<x-ui.input name="first_name" label="Ad" :value="old('first_name',data_get($employee ?? null,'first_name'))" required/>
<x-ui.input name="last_name" label="Soyad" :value="old('last_name',data_get($employee ?? null,'last_name'))" required/>
<x-ui.input name="employee_code" label="Personel kodu" :value="old('employee_code',data_get($employee ?? null,'employee_code'))" required/>
<x-ui.input name="department" label="Departman" :value="old('department',data_get($employee ?? null,'department'))"/>
<x-ui.input name="position" label="Pozisyon" :value="old('position',data_get($employee ?? null,'position'))"/>
<x-ui.input type="date" name="start_date" label="İşe başlama" :value="$employee?->start_date?->format('Y-m-d') ?? data_get($formDefaults ?? [],'start_date')" required/>
<x-ui.input type="date" name="end_date" label="İşten ayrılma" :value="$employee?->end_date?->format('Y-m-d')"/>
<x-ui.input name="phone" label="Telefon" :value="old('phone',data_get($employee ?? null,'phone'))"/>
<x-ui.input type="email" name="email" label="E-posta" :value="old('email',data_get($employee ?? null,'email'))"/>
<x-ui.textarea name="notes" label="Notlar" :value="$employee?->notes" maxlength="10000" />
</div><div class="mt-5"><x-ui.button>{{ $editing?'Kaydet':'Personeli kaydet' }}</x-ui.button></div>
