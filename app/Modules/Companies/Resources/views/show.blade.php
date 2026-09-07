@extends('layouts.app')
@section('title', $company->name)

@section('content')
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold">{{ $company->name }}</h1>
            <div class="mt-1">@include('companies::_status', ['company' => $company])</div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            @can('update', $company)
                <a href="{{ route('companies.edit', $company) }}"
                   class="inline-flex min-h-11 items-center rounded-md bg-slate-900 px-4 text-sm font-medium text-white hover:bg-slate-800">
                    Düzenle
                </a>
            @endcan

            @can('delete', $company)
                <form method="POST" action="{{ route('companies.destroy', $company) }}"
                      onsubmit="return confirm('Bu firma silinsin mi? Kayıt saklanır, geri alınabilir.')">
                    @csrf @method('DELETE')
                    <button type="submit" class="min-h-11 px-2 text-sm font-medium text-red-700 hover:underline">Sil</button>
                </form>
            @endcan

            @can('restore', $company)
                <form method="POST" action="{{ route('companies.restore', $company) }}">
                    @csrf @method('PATCH')
                    <button type="submit" class="min-h-11 px-2 text-sm font-medium text-emerald-700 hover:underline">Geri al</button>
                </form>
            @endcan
        </div>
    </div>

    <div class="mt-6 grid gap-4 lg:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-6 lg:col-span-2">
            <h2 class="text-sm font-semibold text-slate-700">Bilgiler</h2>

            <dl class="mt-4 grid gap-x-6 gap-y-4 sm:grid-cols-2">
                @foreach ([
                    'Vergi dairesi' => $company->tax_office,
                    'Vergi numarası' => $company->tax_number,
                    'Telefon' => $company->phone,
                    'E-posta' => $company->email,
                    'Yetkili kişi' => $company->contact_person,
                    'Şehir' => $company->city,
                ] as $label => $value)
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">{{ $label }}</dt>
                        <dd class="mt-0.5 text-sm">{{ $value ?: '—' }}</dd>
                    </div>
                @endforeach

                <div class="sm:col-span-2">
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Adres</dt>
                    <dd class="mt-0.5 whitespace-pre-line text-sm">{{ $company->address ?: '—' }}</dd>
                </div>

                <div class="sm:col-span-2">
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Notlar</dt>
                    <dd class="mt-0.5 whitespace-pre-line text-sm">{{ $company->notes ?: '—' }}</dd>
                </div>
            </dl>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-6">
            <h2 class="text-sm font-semibold text-slate-700">Kayıt bilgisi</h2>

            <dl class="mt-4 space-y-3 text-sm">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Oluşturan</dt>
                    <dd class="mt-0.5">{{ $company->creator?->name ?? '—' }}
                        <span class="text-slate-500">· {{ $company->created_at->format('d.m.Y H:i') }}</span>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Son güncelleyen</dt>
                    <dd class="mt-0.5">{{ $company->updater?->name ?? '—' }}
                        <span class="text-slate-500">· {{ $company->updated_at->format('d.m.Y H:i') }}</span>
                    </dd>
                </div>
            </dl>

            <p class="mt-6 rounded-md bg-slate-50 px-3 py-2 text-xs text-slate-500">
                Konaklama, tahsilat ve cari ekstre sekmeleri ilgili modüller geldiğinde eklenecektir.
            </p>
        </div>
    </div>
@endsection
