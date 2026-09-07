@extends('layouts.app')
@section('title', 'Firmalar')

@php
    $sortLink = function (string $column) use ($filter) {
        $dir = ($filter->currentSort() === $column && $filter->currentDirection() === 'asc') ? 'desc' : 'asc';
        return request()->fullUrlWithQuery(['sort' => $column, 'direction' => $dir, 'page' => null]);
    };
    $sortMark = fn (string $column) => $filter->currentSort() === $column
        ? ($filter->currentDirection() === 'asc' ? '▲' : '▼') : '';
@endphp

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-lg font-semibold">
            {{ $showTrashed ? 'Silinmiş firmalar' : 'Firmalar' }}
            <span class="ml-1 text-sm font-normal text-slate-500">({{ $companies->total() }})</span>
        </h1>

        <div class="flex items-center gap-3">
            @can('viewTrashed', \App\Modules\Companies\Models\Company::class)
                <a href="{{ request()->fullUrlWithQuery(['trashed' => $showTrashed ? null : '1', 'page' => null]) }}"
                   class="inline-flex min-h-11 items-center px-2 text-sm text-slate-600 hover:underline">
                    {{ $showTrashed ? 'Aktif firmalar' : 'Silinmişler' }}
                </a>
            @endcan

            @can('create', \App\Modules\Companies\Models\Company::class)
                <a href="{{ route('companies.create') }}"
                   class="inline-flex min-h-11 items-center rounded-md bg-slate-900 px-4 text-sm font-medium text-white hover:bg-slate-800">
                    Yeni firma
                </a>
            @endcan
        </div>
    </div>

    <form method="GET" class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @if ($showTrashed)<input type="hidden" name="trashed" value="1">@endif

        <div class="sm:col-span-2">
            <label for="q" class="block text-xs font-medium text-slate-600">Ara</label>
            <input id="q" type="search" name="q" value="{{ request('q') }}"
                   placeholder="Ünvan, vergi no, telefon, yetkili, şehir"
                   class="mt-1 block min-h-11 w-full rounded-md border border-slate-300 px-3 text-sm">
        </div>

        <div>
            <label for="city" class="block text-xs font-medium text-slate-600">Şehir</label>
            <select id="city" name="city" class="mt-1 block min-h-11 w-full rounded-md border border-slate-300 px-3 text-sm">
                <option value="">Tümü</option>
                @foreach ($cities as $city)
                    <option value="{{ $city }}" @selected(request('city') === $city)>{{ $city }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="is_active" class="block text-xs font-medium text-slate-600">Durum</label>
            <select id="is_active" name="is_active" class="mt-1 block min-h-11 w-full rounded-md border border-slate-300 px-3 text-sm">
                <option value="">Tümü</option>
                <option value="1" @selected(request('is_active') === '1')>Aktif</option>
                <option value="0" @selected(request('is_active') === '0')>Pasif</option>
            </select>
        </div>

        <div class="flex items-end gap-3 sm:col-span-2 lg:col-span-4">
            <button type="submit" class="min-h-11 rounded-md bg-slate-900 px-4 text-sm font-medium text-white hover:bg-slate-800">Filtrele</button>
            <a href="{{ route('companies.index', $showTrashed ? ['trashed' => 1] : []) }}"
               class="inline-flex min-h-11 items-center px-2 text-sm text-slate-600 hover:underline">Temizle</a>

            <label for="per_page" class="ml-auto flex items-center gap-2 text-sm text-slate-600">
                Sayfa başına
                <select id="per_page" name="per_page" onchange="this.form.submit()"
                        class="min-h-11 rounded-md border border-slate-300 px-2 text-sm">
                    @foreach ($perPageOptions as $option)
                        <option value="{{ $option }}" @selected((int) request('per_page', 25) === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </form>

    {{-- Masaüstü: tablo --}}
    <div class="mt-4 hidden overflow-hidden rounded-xl border border-slate-200 bg-white md:block">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3"><a href="{{ $sortLink('name') }}" class="hover:underline">Ünvan {{ $sortMark('name') }}</a></th>
                    <th class="px-4 py-3"><a href="{{ $sortLink('tax_number') }}" class="hover:underline">Vergi no {{ $sortMark('tax_number') }}</a></th>
                    <th class="px-4 py-3">Telefon</th>
                    <th class="px-4 py-3">Yetkili</th>
                    <th class="px-4 py-3"><a href="{{ $sortLink('city') }}" class="hover:underline">Şehir {{ $sortMark('city') }}</a></th>
                    <th class="px-4 py-3">Durum</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($companies as $company)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-medium">
                            <a href="{{ route('companies.show', $company) }}" class="hover:underline">{{ $company->name }}</a>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $company->tax_number ?: '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $company->phone ?: '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $company->contact_person ?: '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $company->city ?: '—' }}</td>
                        <td class="px-4 py-3">
                            @include('companies::_status', ['company' => $company])
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">Kayıt bulunamadı.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Mobil: kart listesi (yatay kaydırma yok) --}}
    <div class="mt-4 space-y-3 md:hidden">
        @forelse ($companies as $company)
            <a href="{{ route('companies.show', $company) }}"
               class="block rounded-xl border border-slate-200 bg-white p-4">
                <div class="flex items-start justify-between gap-3">
                    <span class="font-medium">{{ $company->name }}</span>
                    @include('companies::_status', ['company' => $company])
                </div>
                <dl class="mt-2 space-y-1 text-sm text-slate-600">
                    @if ($company->city)<div><dt class="inline text-slate-500">Şehir:</dt> <dd class="inline">{{ $company->city }}</dd></div>@endif
                    @if ($company->phone)<div><dt class="inline text-slate-500">Telefon:</dt> <dd class="inline">{{ $company->phone }}</dd></div>@endif
                    @if ($company->contact_person)<div><dt class="inline text-slate-500">Yetkili:</dt> <dd class="inline">{{ $company->contact_person }}</dd></div>@endif
                    @if ($company->tax_number)<div><dt class="inline text-slate-500">Vergi no:</dt> <dd class="inline">{{ $company->tax_number }}</dd></div>@endif
                </dl>
            </a>
        @empty
            <p class="rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">
                Kayıt bulunamadı.
            </p>
        @endforelse
    </div>

    <div class="mt-4">{{ $companies->links() }}</div>
@endsection
