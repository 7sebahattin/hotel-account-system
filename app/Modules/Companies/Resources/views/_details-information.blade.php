<dl @class([
    'grid gap-x-6 gap-y-4 sm:grid-cols-2',
    'lg:grid-cols-3' => $compact ?? false,
])>
    @foreach ([
        'Vergi dairesi' => $company->tax_office,
        'Vergi numarası' => $company->tax_number,
        'Telefon' => $company->phone,
        'E-posta' => $company->email,
        'Yetkili kişi' => $company->contact_person,
        'Şehir' => $company->city,
    ] as $label => $value)
        <div>
            <dt class="text-xs uppercase tracking-wide text-mut">{{ $label }}</dt>
            <dd class="mt-0.5 text-sm text-tx">{{ $value ?: '—' }}</dd>
        </div>
    @endforeach

    <div @class(['sm:col-span-2', 'lg:col-span-3' => $compact ?? false])>
        <dt class="text-xs uppercase tracking-wide text-mut">Adres</dt>
        <dd class="mt-0.5 text-sm whitespace-pre-line text-tx">{{ $company->address ?: '—' }}</dd>
    </div>

    <div @class(['sm:col-span-2', 'lg:col-span-3' => $compact ?? false])>
        <dt class="text-xs uppercase tracking-wide text-mut">Notlar</dt>
        <dd class="mt-0.5 text-sm whitespace-pre-line text-tx">{{ $company->notes ?: '—' }}</dd>
    </div>
</dl>
