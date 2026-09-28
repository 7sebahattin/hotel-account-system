@extends('layouts.app')
@section('title', 'Teklifler')
@section('content')
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div><p class="text-xs font-bold uppercase tracking-widest text-acc">Konaklama fiyatları</p><h1 class="mt-1 text-3xl font-bold tracking-tight">Teklifler</h1><p class="mt-1 text-sm text-tx-muted">Müşteriye sunulan fiyat belgeleri.</p></div>
        @can('create', \App\Modules\Offers\Models\Offer::class)
            <a href="{{ route('offers.create') }}" class="rounded-xl bg-acc px-5 py-3 font-semibold text-on-acc shadow-sm">+ Yeni teklif</a>
        @endcan
    </div>
    <form method="GET" class="mt-6 grid gap-3 rounded-2xl border border-line bg-surface p-4 shadow-sm sm:grid-cols-2 xl:grid-cols-[2fr_1fr_1fr_1fr_auto]">
        <label class="text-sm font-medium">Ara
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Teklif no, müşteri, başlık" class="mt-1 w-full rounded-lg border border-line bg-bg px-3 py-2.5">
        </label>
        <label class="text-sm font-medium">Durum
            <select name="status" class="mt-1 w-full rounded-lg border border-line bg-bg px-3 py-2.5">
                <option value="">Tümü</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm font-medium">Başlangıç
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="mt-1 w-full rounded-lg border border-line bg-bg px-3 py-2.5">
        </label>
        <label class="text-sm font-medium">Bitiş
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="mt-1 w-full rounded-lg border border-line bg-bg px-3 py-2.5">
        </label>
        <button class="self-end rounded-lg border border-line px-4 py-2.5 font-semibold hover:bg-bg">Filtrele</button>
    </form>
    <div class="mt-5 overflow-x-auto rounded-2xl border border-line bg-surface shadow-sm">
        <table class="min-w-[950px] w-full text-sm">
            <thead class="bg-bg text-left text-xs uppercase tracking-wide text-tx-muted">
                <tr><th class="px-5 py-4">Teklif no</th><th class="px-4 py-4">Müşteri</th><th class="px-4 py-4">Başlık</th><th class="px-4 py-4">Tarih</th><th class="px-4 py-4">Geçerlilik</th><th class="px-4 py-4">Durum</th><th class="px-4 py-4">Oluşturan</th><th class="px-4 py-4">İşlemler</th></tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($offers as $offer)
                    <tr class="hover:bg-bg/60">
                        <td class="px-5 py-4 font-semibold"><a href="{{ route('offers.show', $offer) }}" class="text-acc hover:underline">{{ $offer->offer_no }}</a></td>
                        <td class="px-4 py-4">{{ $offer->customer_name_snapshot }}</td>
                        <td class="max-w-60 truncate px-4 py-4">{{ $offer->title }}</td>
                        <td class="whitespace-nowrap px-4 py-4">{{ $offer->offer_date->format('d.m.Y') }}</td>
                        <td class="whitespace-nowrap px-4 py-4">{{ $offer->valid_until?->format('d.m.Y') ?? '—' }}</td>
                        <td class="px-4 py-4">@include('offers::_status', ['status' => $offer->status])</td>
                        <td class="px-4 py-4">{{ $offer->creator?->name ?? '—' }}</td>
                        <td class="whitespace-nowrap px-4 py-4"><a class="font-semibold text-acc hover:underline" href="{{ route('offers.show', $offer) }}">Gör</a> · <a class="hover:underline" href="{{ route('offers.print', $offer) }}" target="_blank" rel="noopener">Yazdır</a>@can('update', $offer) · <a class="hover:underline" href="{{ route('offers.edit', $offer) }}">Düzenle</a>@endcan</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-5 py-12 text-center text-tx-muted">Bu filtrelerle teklif bulunamadı.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $offers->links() }}</div>
@endsection
