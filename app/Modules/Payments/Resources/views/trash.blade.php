@extends('layouts.app')
@section('title', 'Tahsilatlar — çöp kutusu')

@section('content')
    <x-ui.page-header title="Tahsilat çöp kutusu"
                      :subtitle="number_format($payments->total(), 0, ',', '.').' kayıt'">
        <x-slot:actions>
            <x-ui.button variant="secondary" :href="route('payments.index')">Tahsilatlara dön</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <p class="mt-2 text-sm text-mut">
        Burada yalnızca iptal edilip çöpe taşınan tahsilatlar listelenir. Cari hareketleri etkilenmez.
        @can('payments.force_delete')
            Çöpten kalıcı silme yalnızca Admin'dedir ve geri alınamaz.
        @else
            Kalıcı silme yalnızca Admin tarafından yapılır.
        @endcan
    </p>

    <x-ui.table class="mt-4">
        <thead class="border-b border-line">
            <tr>
                <x-ui.th>Tarih</x-ui.th>
                <x-ui.th>Firma</x-ui.th>
                <x-ui.th align="right">Tutar</x-ui.th>
                <x-ui.th>Yöntem</x-ui.th>
                <x-ui.th>İptal gerekçesi</x-ui.th>
                <x-ui.th>Çöpe taşınma</x-ui.th>
                <x-ui.th>İşlem</x-ui.th>
            </tr>
        </thead>
        <tbody>
            @forelse ($payments as $payment)
                <tr class="border-b border-line last:border-0">
                    <td class="px-4 py-3 whitespace-nowrap tabular-nums text-tx">
                        <a href="{{ route('payments.show', $payment) }}" class="underline underline-offset-2">{{ $payment->payment_date->format('d.m.Y') }}</a>
                    </td>
                    <td class="px-4 py-3 text-mut">{{ $payment->company?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-right whitespace-nowrap tabular-nums text-tx">{{ $payment->amountMoney()->format($payment->currency) }}</td>
                    <td class="px-4 py-3 text-mut">{{ $payment->payment_method?->label() ?? '—' }}</td>
                    <td class="px-4 py-3 text-mut">{{ $payment->cancellation_reason ?: '—' }}</td>
                    <td class="px-4 py-3 whitespace-nowrap tabular-nums text-mut">{{ $payment->trashed_at->format('d.m.Y H:i') }}</td>
                    <td class="px-4 py-3">
                        @include('payments::_trash-actions')
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-4 py-10 text-center text-sm text-mut">Çöp kutusu boş.</td>
                </tr>
            @endforelse
        </tbody>
    </x-ui.table>

    {{-- Mobil kart listesi --}}
    <div class="mt-4 space-y-3 lg:hidden">
        @forelse ($payments as $payment)
            <x-ui.card>
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <a href="{{ route('payments.show', $payment) }}" class="block truncate text-tx underline underline-offset-2">{{ $payment->company?->name ?? '—' }}</a>
                        <p class="tabular-nums text-sm text-mut">{{ $payment->payment_date->format('d.m.Y') }}</p>
                    </div>
                    <span class="shrink-0 tabular-nums text-tx">{{ $payment->amountMoney()->format($payment->currency) }}</span>
                </div>
                <p class="mt-2 text-sm text-mut">Çöpe taşınma: <span class="tabular-nums">{{ $payment->trashed_at->format('d.m.Y H:i') }}</span></p>
                <div class="mt-3">@include('payments::_trash-actions')</div>
            </x-ui.card>
        @empty
            <p class="py-6 text-center text-sm text-mut">Çöp kutusu boş.</p>
        @endforelse
    </div>

    <div class="mt-4">{{ $payments->links() }}</div>
@endsection
