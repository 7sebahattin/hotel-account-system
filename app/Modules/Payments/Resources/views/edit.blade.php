@extends('layouts.app')
@section('title', 'Tahsilat bilgilerini düzenle')

@section('content')
    <x-ui.page-header :title="$payment->company?->name ?? 'Tahsilat'" subtitle="Tahsilat bilgilerini düzenle" />

    <form method="POST" action="{{ route('payments.update', $payment) }}" class="mt-6 max-w-3xl">
        @csrf @method('PUT')

        <x-ui.card class="p-6">
            <x-ui.errors />

            {{-- Finansal alanlar salt okunur GÖSTERİLİR; form alanı olarak
                 gönderilmezler. Değişmezlik servis ve model katmanında zorlanır. --}}
            <dl class="grid gap-x-6 gap-y-4 rounded-[10px] border border-line bg-well p-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-mut">Tarih</dt>
                    <dd class="mt-0.5 text-sm tabular-nums text-tx">{{ $payment->payment_date->format('d.m.Y') }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-mut">Tutar</dt>
                    <dd class="mt-0.5 text-sm tabular-nums text-tx">
                        {{ $payment->amountMoney()->format($payment->currency) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-mut">Ödeme yöntemi</dt>
                    <dd class="mt-0.5 text-sm text-tx">{{ $payment->payment_method?->label() ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-mut">Firma</dt>
                    <dd class="mt-0.5 text-sm text-tx">{{ $payment->company?->name ?? '—' }}</dd>
                </div>
                <p class="text-xs text-mut sm:col-span-2">
                    Bu alanlar değiştirilemez. Düzeltme için tahsilatı iptal edip yeniden girin.
                </p>
            </dl>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <x-ui.input label="Referans no" name="reference_no" maxlength="50"
                            :value="$payment->reference_no" hint="Dekont / slip numarası." />

                <div class="sm:col-span-2">
                    <x-ui.input label="Açıklama" name="description" maxlength="255"
                                :value="$payment->description" />
                </div>
            </div>
        </x-ui.card>

        <div class="mt-4 flex flex-wrap gap-3">
            <x-ui.button type="submit">Güncelle</x-ui.button>
            <x-ui.button variant="ghost" :href="route('payments.show', $payment)">Vazgeç</x-ui.button>
        </div>
    </form>
@endsection
