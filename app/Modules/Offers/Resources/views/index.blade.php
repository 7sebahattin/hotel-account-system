@extends('layouts.app')
@section('title', 'Teklifler')
@section('content')
    <header class="flex flex-wrap items-end justify-between gap-4 border-b border-line pb-6">
        <div class="min-w-0">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-acc-tx">Konaklama · Fiyat belgeleri</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-tx sm:text-4xl">Teklifler</h1>
            <p class="mt-2 text-sm text-mut">Hazırlanan teklifleri bulun, inceleyin ve müşteriye sunun.</p>
        </div>
        @can('create', \App\Modules\Offers\Models\Offer::class)
            <a href="{{ route('offers.create') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-acc px-5 text-sm font-semibold text-on-acc hover:bg-acc-strong focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-acc-tx">
                <x-ui.icon name="plus" /> Yeni teklif
            </a>
        @endcan
    </header>

    <section class="mt-6 rounded-xl border border-line bg-card p-4 sm:p-5" aria-label="Teklif filtreleri">
        <div class="mb-4 flex items-baseline justify-between gap-3">
            <h2 class="text-sm font-semibold text-tx">Teklifleri filtrele</h2>
            @if (array_filter($filters))
                <a href="{{ route('offers.index') }}" class="text-sm font-medium text-acc-tx underline-offset-2 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-acc-tx">Filtreleri temizle</a>
            @endif
        </div>
        <form method="GET" action="{{ route('offers.index') }}" class="grid min-w-0 gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_auto] xl:items-end">
            <label class="min-w-0 text-xs font-semibold text-tx sm:col-span-2 xl:col-span-1">Ara
                <span class="relative mt-1 block">
                    <x-ui.icon name="search" class="pointer-events-none absolute left-3 top-3.5 size-4 text-mut" />
                    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Teklif no, müşteri veya başlık" class="min-h-11 w-full min-w-0 rounded-lg border border-field bg-card pl-10 pr-3 text-sm font-normal text-tx focus:border-acc-tx">
                </span>
            </label>
            <label class="min-w-0 text-xs font-semibold text-tx">Durum
                <select name="status" class="mt-1 min-h-11 w-full min-w-0 rounded-lg border border-field bg-card px-3 text-sm font-normal text-tx focus:border-acc-tx">
                    <option value="">Tüm durumlar</option>
                    @foreach ($statuses as $status)<option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>@endforeach
                </select>
            </label>
            <label class="min-w-0 text-xs font-semibold text-tx">Başlangıç
                <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="mt-1 min-h-11 w-full min-w-0 rounded-lg border border-field bg-card px-3 text-sm font-normal text-tx focus:border-acc-tx">
            </label>
            <label class="min-w-0 text-xs font-semibold text-tx">Bitiş
                <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="mt-1 min-h-11 w-full min-w-0 rounded-lg border border-field bg-card px-3 text-sm font-normal text-tx focus:border-acc-tx">
            </label>
            <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-field bg-well px-5 text-sm font-semibold text-tx hover:bg-acc-bg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-acc-tx">Filtrele</button>
        </form>
    </section>

    <div class="mt-6 flex items-baseline justify-between gap-3">
        <h2 class="text-lg font-semibold text-tx">Teklif kayıtları</h2>
        <span class="text-sm text-mut">{{ number_format($offers->total(), 0, ',', '.') }} kayıt</span>
    </div>
    @if ($offers->isEmpty())
        <div class="mt-3 rounded-xl border border-line bg-card px-6 py-14 text-center">
            <p class="text-lg font-semibold text-tx">Teklif bulunamadı</p>
            <p class="mt-1 text-sm text-mut">Arama veya tarih aralığını değiştirebilirsiniz.</p>
        </div>
    @else
        <div class="mt-3 hidden overflow-hidden rounded-xl border border-line bg-card xl:block">
            <table class="w-full table-fixed text-sm">
                <thead class="border-b border-line bg-well/70 text-left text-xs font-semibold uppercase tracking-wider text-mut">
                    <tr>
                        <th scope="col" class="w-[29%] px-5 py-3">Teklif</th>
                        <th scope="col" class="w-[21%] px-4 py-3">Müşteri</th>
                        <th scope="col" class="w-[17%] px-4 py-3">Tarih</th>
                        <th scope="col" class="w-[13%] px-4 py-3">Durum</th>
                        <th scope="col" class="w-[10%] px-4 py-3">Oluşturan</th>
                        <th scope="col" class="w-[10%] px-4 py-3 text-right">İşlem</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($offers as $offer)
                        <tr class="align-top transition-colors hover:bg-acc-bg/50 focus-within:bg-acc-bg/50">
                            <td class="px-5 py-4">
                                <a href="{{ route('offers.show', $offer) }}" class="block min-w-0 rounded-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-acc-tx">
                                    <span class="block text-xs font-bold tracking-wide text-acc-tx">{{ $offer->offer_no }}</span>
                                    <span class="mt-1 block truncate font-semibold text-tx" title="{{ $offer->title }}">{{ $offer->title }}</span>
                                </a>
                            </td>
                            <td class="min-w-0 px-4 py-4 font-medium text-tx"><span class="block truncate" title="{{ $offer->customer_name_snapshot }}">{{ $offer->customer_name_snapshot }}</span></td>
                            <td class="px-4 py-4 text-tx">{{ $offer->offer_date->format('d.m.Y') }}<span class="mt-1 block text-xs text-mut">Geçerlilik: {{ $offer->valid_until?->format('d.m.Y') ?? 'Belirtilmedi' }}</span></td>
                            <td class="px-4 py-4">@include('offers::_status', ['status' => $offer->status])</td>
                            <td class="min-w-0 px-4 py-4 text-mut"><span class="block truncate" title="{{ $offer->creator?->name ?? '—' }}">{{ $offer->creator?->name ?? '—' }}</span></td>
                            <td class="px-4 py-4 text-right">
                                <a href="{{ route('offers.print', $offer) }}" target="_blank" rel="noopener" aria-label="{{ $offer->offer_no }} yazdır" class="inline-flex min-h-10 items-center gap-1 rounded-lg px-2 text-sm font-medium text-acc-tx hover:bg-acc-bg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-acc-tx"><x-ui.icon name="printer" /> Yazdır</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:hidden">
            @foreach ($offers as $offer)
                <article class="min-w-0 rounded-xl border border-line bg-card p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-xs font-bold tracking-wide text-acc-tx">{{ $offer->offer_no }}</p>
                            <h3 class="mt-1 break-words text-base font-semibold leading-snug text-tx"><a href="{{ route('offers.show', $offer) }}" class="rounded-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-acc-tx">{{ $offer->title }}</a></h3>
                        </div>
                        @include('offers::_status', ['status' => $offer->status])
                    </div>
                    <p class="mt-3 break-words text-sm font-medium text-tx">{{ $offer->customer_name_snapshot }}</p>
                    <p class="mt-2 text-xs text-mut">{{ $offer->offer_date->format('d.m.Y') }} · Geçerlilik: {{ $offer->valid_until?->format('d.m.Y') ?? 'Belirtilmedi' }}</p>
                    <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-line pt-2 text-sm">
                        <a href="{{ route('offers.show', $offer) }}" class="inline-flex min-h-10 items-center font-semibold text-acc-tx hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-acc-tx">Detay</a>
                        <a href="{{ route('offers.print', $offer) }}" target="_blank" rel="noopener" class="inline-flex min-h-10 items-center gap-1 font-semibold text-acc-tx hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-acc-tx"><x-ui.icon name="printer" /> Yazdır</a>
                        @can('update', $offer)<a href="{{ route('offers.edit', $offer) }}" class="inline-flex min-h-10 items-center font-semibold text-acc-tx hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-acc-tx">Düzenle</a>@endcan
                    </div>
                </article>
            @endforeach
        </div>
    @endif
    <div class="mt-5">{{ $offers->links() }}</div>
@endsection
