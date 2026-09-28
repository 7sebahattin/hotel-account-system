@extends('layouts.app')
@section('title', 'Tahsilat Makbuzu Defteri')

@section('content')
    <x-ui.page-header title="Tahsilat Makbuzu Defteri"
                      :subtitle="number_format($entries->total(), 0, ',', '.').' makbuz — bu ekran muhasebe kaydı ÜRETMEZ'">
        <x-slot:meta><x-brand-logo variant="on-light" :decorative="true" class="mt-2 h-9 w-auto" /></x-slot:meta>
        <x-slot:actions>
            @can('create', \App\Modules\ReceiptBook\Models\ReceiptBookEntry::class)
                <a href="{{ route('receipt-book.create') }}" data-receipt-dialog-trigger
                   class="inline-flex min-h-11 items-center gap-2 rounded-lg bg-acc px-4 text-sm font-medium text-on-acc hover:bg-acc-strong">
                    Yeni Makbuz
                </a>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.alert tone="info" class="mt-4">
        Bu defter, muhasebe "Tahsilatlar" ekranından <strong>AYRIDIR</strong>. Buradaki makbuzlar
        firma cari hesabını, fiziksel kasayı veya herhangi bir cari hareketi ETKİLEMEZ — yalnızca
        yazdırılabilir bir kayıt tutar.
    </x-ui.alert>

    <x-ui.table class="mt-4">
        <thead class="border-b border-line">
            <tr>
                <x-ui.th>Makbuz No</x-ui.th>
                <x-ui.th>Tarih</x-ui.th>
                <x-ui.th>Müşteri / Cari</x-ui.th>
                <x-ui.th align="right">Tutar</x-ui.th>
                <x-ui.th>Ödeme şekli</x-ui.th>
                <x-ui.th></x-ui.th>
            </tr>
        </thead>
        <tbody>
            @forelse ($entries as $entry)
                <tr class="border-b border-line last:border-0 hover:bg-well">
                    <td class="px-4 py-3 tabular-nums text-tx">
                        <a href="{{ route('receipt-book.show', $entry) }}" class="hover:text-acc">{{ $entry->receipt_no }}</a>
                    </td>
                    <td class="px-4 py-3 tabular-nums text-mut">{{ $entry->receipt_date->format('d.m.Y') }}</td>
                    <td class="px-4 py-3 text-tx">{{ $entry->customer_name }}</td>
                    <td class="px-4 py-3 text-right tabular-nums text-tx">
                        {{ $entry->amountMoney()->format($entry->currency) }}
                        <x-ui.fx-note :rate="$entry->exchange_rate_to_try" :equivalent="$entry->tryEquivalentMoney()" class="text-right" />
                    </td>
                    <td class="px-4 py-3 text-mut">{{ $entry->payment_method?->label() ?? '—' }}</td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex justify-end gap-2">
                            @can('update', $entry)
                                <x-ui.button variant="ghost" :href="route('receipt-book.edit', $entry)" class="text-xs">Düzenle</x-ui.button>
                            @endcan
                            <x-ui.button variant="secondary" :href="route('receipt-book.print', $entry)" class="text-xs">Yazdır</x-ui.button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-10 text-center text-sm text-mut">Henüz makbuz yok.</td>
                </tr>
            @endforelse
        </tbody>
    </x-ui.table>

    <div class="mt-4">{{ $entries->links() }}</div>

    {{--
        "Yeni Makbuz" dialog kabuğu — bu sayfada BİR KEZ, boş durur (Kasa
        Hareketi dialog'unun AYNI deseni, v2.1.1-B.1). İçerik yalnızca
        kullanıcı tıkladığında `fetch()` ile yüklenir.
    --}}
    @can('create', \App\Modules\ReceiptBook\Models\ReceiptBookEntry::class)
        <dialog id="makbuz-dialog" class="app-dialog" aria-labelledby="makbuz-dialog-baslik">
            <div data-receipt-dialog-content></div>
        </dialog>
    @endcan
@endsection
