@extends('layouts.app')
@section('title', 'Geçmiş Döviz Düzeltme')

@section('content')
    <x-ui.page-header title="Geçmiş Döviz Düzeltme"
                      subtitle="Yabancı para birimli, kur bilgisi EKSİK geçmiş kayıtlar" />

    <x-ui.alert tone="info" class="mt-4">
        Buradaki kayıtlar bu özellikten ÖNCE oluşturulmuş — kur bilgisi hiç girilmemiş.
        Aşağıya yalnızca <strong>işlem anındaki gerçek kuru</strong> girin: orijinal tutar ve
        para birimi DEĞİŞMEZ, ikinci bir tahsilat/konaklama veya cari hareket OLUŞMAZ. Bir kayıt
        kaydedildikten sonra bu listeden düşer ve bir daha buradan düzenlenemez.
    </x-ui.alert>

    <x-ui.panel title="Konaklamalar ({{ $stays->count() }})" class="mt-6">
        @if ($stays->isEmpty())
            <p class="p-4 text-sm text-mut">Kur bilgisi eksik konaklama yok.</p>
        @else
            <x-ui.table>
                <thead class="border-b border-line">
                    <tr>
                        <x-ui.th>Çıkış tarihi</x-ui.th>
                        <x-ui.th>Firma / Misafir</x-ui.th>
                        <x-ui.th align="right">Tutar</x-ui.th>
                        <x-ui.th>Kur</x-ui.th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($stays as $stay)
                        <tr class="border-b border-line last:border-0">
                            <td class="px-4 py-3 tabular-nums text-tx">{{ $stay->check_out_date->format('d.m.Y') }}</td>
                            <td class="px-4 py-3 text-mut">
                                <a href="{{ route('stays.show', $stay) }}" class="text-tx hover:text-acc">{{ $stay->company?->name ?? '—' }}</a>
                                <span class="block text-xs">{{ $stay->guest_name }}</span>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-tx">
                                {{ $stay->amountMoney()->format($stay->currency) }}
                            </td>
                            <td class="px-4 py-3">
                                <form method="POST" action="{{ route('fx-correction.stays.update', $stay) }}"
                                      class="flex flex-wrap items-center gap-2">
                                    @csrf @method('PATCH')
                                    {{-- Kur ya da doğrudan TL karşılığı (K-110) — aynı bileşen, satır içi düzen. --}}
                                    <x-ui.fx-input layout="inline" :currency="$stay->currency->value"
                                                   :id-prefix="'fx-stay-'.$stay->id">
                                        <x-ui.button type="submit" variant="secondary" class="text-xs">Kaydet</x-ui.button>
                                    </x-ui.fx-input>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        @endif
    </x-ui.panel>

    <x-ui.panel title="Tahsilatlar ({{ $payments->count() }})" class="mt-6">
        @if ($payments->isEmpty())
            <p class="p-4 text-sm text-mut">Kur bilgisi eksik tahsilat yok.</p>
        @else
            <x-ui.table>
                <thead class="border-b border-line">
                    <tr>
                        <x-ui.th>Tarih</x-ui.th>
                        <x-ui.th>Firma / Referans</x-ui.th>
                        <x-ui.th align="right">Tutar</x-ui.th>
                        <x-ui.th>Kur</x-ui.th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($payments as $payment)
                        <tr class="border-b border-line last:border-0">
                            <td class="px-4 py-3 tabular-nums text-tx">{{ $payment->payment_date->format('d.m.Y') }}</td>
                            <td class="px-4 py-3 text-mut">
                                <a href="{{ route('payments.show', $payment) }}" class="text-tx hover:text-acc">{{ $payment->company?->name ?? '—' }}</a>
                                @if ($payment->reference_no)
                                    <span class="block text-xs tabular-nums">Ref: {{ $payment->reference_no }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-tx">
                                {{ $payment->amountMoney()->format($payment->currency) }}
                            </td>
                            <td class="px-4 py-3">
                                <form method="POST" action="{{ route('fx-correction.payments.update', $payment) }}"
                                      class="flex flex-wrap items-center gap-2">
                                    @csrf @method('PATCH')
                                    {{-- Kur ya da doğrudan TL karşılığı (K-110) — aynı bileşen, satır içi düzen. --}}
                                    <x-ui.fx-input layout="inline" :currency="$payment->currency->value"
                                                   :id-prefix="'fx-payment-'.$payment->id">
                                        <x-ui.button type="submit" variant="secondary" class="text-xs">Kaydet</x-ui.button>
                                    </x-ui.fx-input>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        @endif
    </x-ui.panel>
@endsection
