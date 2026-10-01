@extends('layouts.app')
@section('title', $entry->receipt_no)

@section('content')
    <x-ui.page-header :title="$entry->receipt_no" subtitle="Tahsilat Makbuzu Defteri kaydı">
        <x-slot:meta><x-brand-logo variant="on-light" :decorative="true" class="mt-2 h-9 w-auto" /></x-slot:meta>
        <x-slot:actions>
            <x-ui.button :href="route('receipt-book.print', $entry)">Yazdır</x-ui.button>

            @can('update', $entry)
                <x-ui.button variant="secondary" :href="route('receipt-book.edit', $entry)">Düzenle</x-ui.button>
            @endcan

            @can('delete', $entry)
                <form method="POST" action="{{ route('receipt-book.destroy', $entry) }}"
                      onsubmit="return confirm('Bu makbuz kalıcı olarak silinsin mi? Bu işlem geri alınamaz.')">
                    @csrf @method('DELETE')
                    <x-ui.button type="submit" variant="danger">Sil</x-ui.button>
                </form>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.alert tone="info" class="mt-4">
        Bu kayıt muhasebe kaydı DEĞİLDİR — cari bakiyeyi, kasayı veya herhangi bir cari
        hareketi etkilemez.
    </x-ui.alert>

    <x-ui.card title="Makbuz bilgileri" class="mt-6 p-6">
        <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs uppercase tracking-wide text-mut">Tarih</dt>
                <dd class="mt-0.5 text-sm tabular-nums text-tx">{{ $entry->receipt_date->format('d.m.Y') }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-mut">Müşteri / Cari</dt>
                <dd class="mt-0.5 text-sm text-tx">{{ $entry->customer_name }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-mut">Tutar</dt>
                <dd class="mt-0.5 text-sm tabular-nums text-tx">
                    {{ $entry->amountMoney()->format($entry->currency) }}
                    <x-ui.fx-note :rate="$entry->exchange_rate_to_try" :equivalent="$entry->tryEquivalentMoney()" />
                </dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-mut">Ödeme şekli</dt>
                <dd class="mt-0.5 text-sm text-tx">{{ $entry->payment_method?->label() ?? '—' }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-xs uppercase tracking-wide text-mut">Açıklama</dt>
                <dd class="mt-0.5 text-sm whitespace-pre-line text-tx">{{ $entry->description ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-mut">Oluşturan</dt>
                <dd class="mt-0.5 text-tx">
                    {{ $entry->creator?->name ?? '—' }}
                    <span class="text-mut">· {{ $entry->created_at->format('d.m.Y H:i') }}</span>
                </dd>
            </div>
        </dl>
    </x-ui.card>
@endsection
