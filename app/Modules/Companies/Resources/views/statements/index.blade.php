@extends('layouts.app')
@section('title', 'Cari Ekstre')

@section('content')
    <x-ui.page-header title="Cari Ekstre" :count="$companies->total()"
                      subtitle="Ekstresini görmek istediğiniz firmayı seçin." />

    <form method="GET" class="mt-4 flex flex-wrap items-end gap-3">
        <div class="min-w-64 flex-1">
            <x-ui.input label="Firma ara" name="q" type="search" :value="request('q')"
                        hint="Ünvan, vergi no, telefon, yetkili veya şehir." />
        </div>

        <x-ui.button type="submit">Ara</x-ui.button>
        <x-ui.button variant="ghost" :href="route('statements.index')">Temizle</x-ui.button>

        <label for="per_page" class="ml-auto flex items-center gap-2 text-sm text-mut">
            Sayfa başına
            <select id="per_page" name="per_page" onchange="this.form.submit()"
                    class="min-h-11 rounded-lg border border-field bg-well px-2 text-tx focus:border-acc">
                @foreach ($perPageOptions as $option)
                    <option value="{{ $option }}" @selected((int) request('per_page', 25) === $option)>{{ $option }}</option>
                @endforeach
            </select>
        </label>
    </form>

    <x-ui.table class="mt-4">
        <thead class="border-b border-line">
            <tr>
                <x-ui.sort-header :filter="$filter" column="name">Firma</x-ui.sort-header>
                <x-ui.th>Bakiye</x-ui.th>
                <x-ui.th>Son hareket</x-ui.th>
                <x-ui.th></x-ui.th>
            </tr>
        </thead>
        <tbody>
            @forelse ($companies as $company)
                @php $companyBalances = $balances[$company->id] ?? []; @endphp
                <tr class="border-b border-line last:border-0 hover:bg-well">
                    <td class="px-4 py-3">
                        <a href="{{ route('statements.show', $company) }}" class="text-tx hover:text-acc">
                            {{ $company->name }}
                        </a>
                    </td>
                    <td class="px-4 py-3">
                        @include('companies::statements._balances', ['companyBalances' => $companyBalances])
                    </td>
                    <td class="px-4 py-3 tabular-nums text-mut">
                        {{ isset($lastDates[$company->id])
                            ? \Illuminate\Support\Carbon::parse($lastDates[$company->id])->format('d.m.Y')
                            : '—' }}
                    </td>
                    <td class="px-4 py-3 text-right">
                        <x-ui.button variant="ghost" :href="route('statements.show', $company)">Ekstre</x-ui.button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-4 py-10 text-center text-mut">Firma bulunamadı.</td></tr>
            @endforelse
        </tbody>
    </x-ui.table>

    <div class="mt-4 space-y-3 lg:hidden">
        @forelse ($companies as $company)
            @php $companyBalances = $balances[$company->id] ?? []; @endphp
            <a href="{{ route('statements.show', $company) }}" class="block">
                <x-ui.card>
                    <p class="truncate text-tx">{{ $company->name }}</p>
                    <div class="mt-2">
                        @include('companies::statements._balances', ['companyBalances' => $companyBalances])
                    </div>
                    <p class="mt-2 text-xs text-mut">
                        Son hareket:
                        <span class="tabular-nums">{{ isset($lastDates[$company->id])
                            ? \Illuminate\Support\Carbon::parse($lastDates[$company->id])->format('d.m.Y')
                            : '—' }}</span>
                    </p>
                </x-ui.card>
            </a>
        @empty
            <x-ui.empty>Firma bulunamadı.</x-ui.empty>
        @endforelse
    </div>

    <div class="mt-4">{{ $companies->links() }}</div>
@endsection
