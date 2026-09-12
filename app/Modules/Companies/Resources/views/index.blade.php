@extends('layouts.app')
@section('title', 'Firmalar')

@section('content')
    <x-ui.page-header :title="$showTrashed ? 'Silinmiş firmalar' : 'Firmalar'" :count="$companies->total()"
                      :subtitle="$showTrashed ? null : number_format($activeCount, 0, ',', '.').' aktif firma'">
        <x-slot:actions>
            @can('viewTrashed', \App\Modules\Companies\Models\Company::class)
                <x-ui.button variant="ghost"
                             :href="request()->fullUrlWithQuery(['trashed' => $showTrashed ? null : '1', 'page' => null])">
                    {{ $showTrashed ? 'Aktif firmalar' : 'Silinmişler' }}
                </x-ui.button>
            @endcan

            @can('create', \App\Modules\Companies\Models\Company::class)
                <x-ui.button :href="route('companies.create')">Yeni firma</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.filter-panel :count="$filter->activeFilterCount()"
                       :clear-href="route('companies.index', $showTrashed ? ['trashed' => 1] : [])"
                       :per-page-options="$perPageOptions">
        @if ($showTrashed)
            <x-slot:hidden><input type="hidden" name="trashed" value="1"></x-slot:hidden>
        @endif

        <div class="sm:col-span-2">
            <x-ui.input label="Ara" name="q" type="search" :value="request('q')"
                        placeholder="Ünvan, vergi no, telefon, e-posta, yetkili, şehir" />
        </div>

        <x-ui.select label="Şehir" name="city">
            <option value="">Tümü</option>
            @foreach ($cities as $city)
                <option value="{{ $city }}" @selected(request('city') === $city)>{{ $city }}</option>
            @endforeach
        </x-ui.select>

        <x-ui.select label="Durum" name="is_active">
            <option value="">Tümü</option>
            <option value="1" @selected(request('is_active') === '1')>Aktif</option>
            <option value="0" @selected(request('is_active') === '0')>Pasif</option>
        </x-ui.select>
    </x-ui.filter-panel>

    <x-ui.table class="mt-4">
        <thead class="border-b border-line">
            <tr>
                <x-ui.sort-header :filter="$filter" column="name">Ünvan</x-ui.sort-header>
                <x-ui.sort-header :filter="$filter" column="tax_number">Vergi no</x-ui.sort-header>
                <x-ui.th>Telefon</x-ui.th>
                <x-ui.th>Yetkili</x-ui.th>
                <x-ui.sort-header :filter="$filter" column="city">Şehir</x-ui.sort-header>
                <x-ui.th>Durum</x-ui.th>
            </tr>
        </thead>
        <tbody>
            @forelse ($companies as $company)
                <tr class="border-b border-line last:border-0 hover:bg-well">
                    <td class="px-4 py-3">
                        <a href="{{ route('companies.show', $company) }}" class="text-tx hover:text-acc">{{ $company->name }}</a>
                    </td>
                    <td class="px-4 py-3 tabular-nums text-mut">{{ $company->tax_number ?: '—' }}</td>
                    <td class="px-4 py-3 tabular-nums text-mut">{{ $company->phone ?: '—' }}</td>
                    <td class="px-4 py-3 text-mut">{{ $company->contact_person ?: '—' }}</td>
                    <td class="px-4 py-3 text-mut">{{ $company->city ?: '—' }}</td>
                    <td class="px-4 py-3">@include('companies::_status', ['company' => $company])</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-10 text-center text-sm text-mut">
                        @if ($filter->hasActiveFilters())
                            Bu filtrelerle eşleşen kayıt bulunamadı.
                            <a href="{{ route('companies.index', $showTrashed ? ['trashed' => 1] : []) }}"
                               class="ml-1 text-acc-tx underline underline-offset-2 hover:text-tx">Filtreleri temizle</a>
                        @else
                            Kayıt bulunamadı.
                        @endif
                    </td>
                </tr>
            @endforelse
        </tbody>
    </x-ui.table>

    <div class="mt-4 space-y-3 lg:hidden">
        @forelse ($companies as $company)
            <a href="{{ route('companies.show', $company) }}" class="block">
                <x-ui.card>
                    <div class="flex items-start justify-between gap-3">
                        <span class="min-w-0 truncate text-tx">{{ $company->name }}</span>
                        @include('companies::_status', ['company' => $company])
                    </div>

                    <dl class="mt-2 space-y-1 text-sm text-mut">
                        @if ($company->city)
                            <div><dt class="inline">Şehir:</dt> <dd class="inline text-tx">{{ $company->city }}</dd></div>
                        @endif
                        @if ($company->phone)
                            <div><dt class="inline">Telefon:</dt> <dd class="inline tabular-nums text-tx">{{ $company->phone }}</dd></div>
                        @endif
                        @if ($company->contact_person)
                            <div><dt class="inline">Yetkili:</dt> <dd class="inline text-tx">{{ $company->contact_person }}</dd></div>
                        @endif
                    </dl>
                </x-ui.card>
            </a>
        @empty
            <x-ui.empty :filtered="$filter->hasActiveFilters()"
                        :clear-href="route('companies.index', $showTrashed ? ['trashed' => 1] : [])" />
        @endforelse
    </div>

    <div class="mt-4">{{ $companies->links() }}</div>
@endsection
