@extends('layouts.app')
@section('title', 'Konaklamalar')

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
            Konaklamalar <span class="ml-1 text-sm font-normal text-slate-500">({{ $stays->total() }})</span>
        </h1>

        @can('create', \App\Modules\Stays\Models\Stay::class)
            <a href="{{ route('stays.create') }}"
               class="inline-flex min-h-11 items-center rounded-md bg-slate-900 px-4 text-sm font-medium text-white hover:bg-slate-800">
                Yeni konaklama
            </a>
        @endcan
    </div>

    <form method="GET" class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="sm:col-span-2">
            <label for="q" class="block text-xs font-medium text-slate-600">Misafir adı veya oda</label>
            <input id="q" type="search" name="q" value="{{ request('q') }}"
                   class="mt-1 block min-h-11 w-full rounded-md border border-slate-300 px-3 text-sm">
        </div>

        <div>
            <label for="company_id" class="block text-xs font-medium text-slate-600">Firma</label>
            <select id="company_id" name="company_id" class="mt-1 block min-h-11 w-full rounded-md border border-slate-300 px-3 text-sm">
                <option value="">Tümü</option>
                @foreach ($companies as $id => $name)
                    <option value="{{ $id }}" @selected((string) request('company_id') === (string) $id)>{{ $name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="status" class="block text-xs font-medium text-slate-600">Durum</label>
            <select id="status" name="status" class="mt-1 block min-h-11 w-full rounded-md border border-slate-300 px-3 text-sm">
                <option value="">Tümü</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="date_from" class="block text-xs font-medium text-slate-600">Giriş tarihi (baş.)</label>
            <input id="date_from" type="date" name="date_from" value="{{ request('date_from') }}"
                   class="mt-1 block min-h-11 w-full rounded-md border border-slate-300 px-3 text-sm">
        </div>

        <div>
            <label for="date_to" class="block text-xs font-medium text-slate-600">Giriş tarihi (bit.)</label>
            <input id="date_to" type="date" name="date_to" value="{{ request('date_to') }}"
                   class="mt-1 block min-h-11 w-full rounded-md border border-slate-300 px-3 text-sm">
        </div>

        <div>
            <label for="currency" class="block text-xs font-medium text-slate-600">Para birimi</label>
            <select id="currency" name="currency" class="mt-1 block min-h-11 w-full rounded-md border border-slate-300 px-3 text-sm">
                <option value="">Tümü</option>
                @foreach ($currencies as $currency)
                    <option value="{{ $currency->value }}" @selected(request('currency') === $currency->value)>{{ $currency->value }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex items-end gap-3 sm:col-span-2 lg:col-span-4">
            <button type="submit" class="min-h-11 rounded-md bg-slate-900 px-4 text-sm font-medium text-white hover:bg-slate-800">Filtrele</button>
            <a href="{{ route('stays.index') }}" class="inline-flex min-h-11 items-center px-2 text-sm text-slate-600 hover:underline">Temizle</a>

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

    {{-- Masaüstü --}}
    <div class="mt-4 hidden overflow-hidden rounded-xl border border-slate-200 bg-white md:block">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3"><a href="{{ $sortLink('check_in_date') }}" class="hover:underline">Giriş {{ $sortMark('check_in_date') }}</a></th>
                    <th class="px-4 py-3"><a href="{{ $sortLink('check_out_date') }}" class="hover:underline">Çıkış {{ $sortMark('check_out_date') }}</a></th>
                    <th class="px-4 py-3 text-right"><a href="{{ $sortLink('nights') }}" class="hover:underline">Gece {{ $sortMark('nights') }}</a></th>
                    <th class="px-4 py-3"><a href="{{ $sortLink('guest_name') }}" class="hover:underline">Misafir {{ $sortMark('guest_name') }}</a></th>
                    <th class="px-4 py-3">Oda</th>
                    <th class="px-4 py-3">Firma</th>
                    <th class="px-4 py-3 text-right"><a href="{{ $sortLink('amount') }}" class="hover:underline">Tutar {{ $sortMark('amount') }}</a></th>
                    <th class="px-4 py-3">Durum</th>
                    <th class="px-4 py-3">Oluşturan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($stays as $stay)
                    <tr class="hover:bg-slate-50 {{ $stay->isCancelled() ? 'text-slate-400' : '' }}">
                        <td class="px-4 py-3 whitespace-nowrap">
                            <a href="{{ route('stays.show', $stay) }}" class="font-medium hover:underline">
                                {{ $stay->check_in_date->format('d.m.Y') }}
                            </a>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $stay->check_out_date->format('d.m.Y') }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $stay->nights }}</td>
                        <td class="px-4 py-3">{{ $stay->guest_name }}</td>
                        <td class="px-4 py-3">{{ $stay->room_no ?: '—' }}</td>
                        <td class="px-4 py-3">{{ $stay->company?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap tabular-nums">
                            {{ $stay->amountMoney()->format($stay->currency) }}
                        </td>
                        <td class="px-4 py-3">@include('stays::_status', ['stay' => $stay])</td>
                        <td class="px-4 py-3 text-slate-600">{{ $stay->creator?->name ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-4 py-10 text-center text-slate-500">Kayıt bulunamadı.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Mobil: kart listesi --}}
    <div class="mt-4 space-y-3 md:hidden">
        @forelse ($stays as $stay)
            <a href="{{ route('stays.show', $stay) }}"
               class="block rounded-xl border border-slate-200 bg-white p-4 {{ $stay->isCancelled() ? 'opacity-60' : '' }}">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <span class="font-medium">{{ $stay->guest_name }}</span>
                        @if ($stay->room_no)
                            <span class="ml-1 text-sm text-slate-500">· Oda {{ $stay->room_no }}</span>
                        @endif
                    </div>
                    @include('stays::_status', ['stay' => $stay])
                </div>

                <p class="mt-1 text-sm text-slate-600">{{ $stay->company?->name ?? '—' }}</p>

                <div class="mt-2 flex items-end justify-between gap-3">
                    <p class="text-sm text-slate-600">
                        {{ $stay->check_in_date->format('d.m.Y') }} → {{ $stay->check_out_date->format('d.m.Y') }}
                        <span class="text-slate-500">· {{ $stay->nights }} gece</span>
                    </p>
                    <span class="font-medium tabular-nums">{{ $stay->amountMoney()->format($stay->currency) }}</span>
                </div>
            </a>
        @empty
            <p class="rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">
                Kayıt bulunamadı.
            </p>
        @endforelse
    </div>

    <div class="mt-4">{{ $stays->links() }}</div>
@endsection
