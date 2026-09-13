@extends('layouts.app')
@section('title', 'Tahsilat · '.$payment->payment_date->format('d.m.Y'))

@section('content')
    <x-ui.page-header :title="$payment->amountMoney()->format($payment->currency)">
        <x-slot:meta>
            <p class="mt-1 text-sm text-mut">
                {{ $payment->company?->name ?? '—' }}
                · <span class="tabular-nums">{{ $payment->payment_date->format('d.m.Y') }}</span>
            </p>
            <div class="mt-2">@include('payments::_status', ['payment' => $payment])</div>
        </x-slot:meta>

        <x-slot:actions>
            @if ($payment->receipt_no !== null)
                <x-ui.button variant="secondary" :href="route('payments.receipt', $payment)">Makbuzu Yazdır</x-ui.button>
            @endif

            @can('update', $payment)
                <x-ui.button :href="route('payments.edit', $payment)">Bilgileri düzenle</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card title="Ödemenin dağılımı" class="mt-4 p-5">
        @if ($payment->allocation_mode === null)
            <p class="text-sm text-mut">Geçmiş tahsilat: konaklama eşleştirmesi henüz yapılmadı. Cari bakiyeye etkisi korunur.</p>
        @elseif ($payment->isCancelled())
            <p class="text-sm text-mut">Tahsilat iptal edildi; konaklamalara uygulanan tutarlar geri alındı.</p>
        @else
            <ul class="space-y-2">
                @foreach ($allocations as $allocation)
                    <li class="flex flex-wrap justify-between gap-2 text-sm">
                        <a class="text-acc-tx underline" href="{{ route('stays.show', $allocation['stay_id']) }}">#{{ $allocation['stay_id'] }} · {{ $allocation['guest_name'] }}</a>
                        <span class="tabular-nums">{{ $allocation['amount']->format($payment->currency) }}</span>
                    </li>
                @endforeach
            </ul>
            <p class="mt-3 text-sm tabular-nums">Kullanılabilir avans: {{ $availableAdvance->format($payment->currency) }}</p>
        @endif
    </x-ui.card>

    @if ($payment->isCancelled())
        <x-ui.alert tone="danger" class="mt-4">
            Bu tahsilat iptal edilmiştir. Cari hareketi ters kaydedilmiştir; kayıt düzenlenemez
            ve geri alınamaz.
            @if ($payment->cancellation_reason)
                <br><strong>Gerekçe:</strong> {{ $payment->cancellation_reason }}
            @endif
        </x-ui.alert>
    @endif

    <div class="mt-6 grid gap-4 lg:grid-cols-3">
        <x-ui.card title="Tahsilat bilgileri" class="p-6 lg:col-span-2">
            <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-mut">Tarih</dt>
                    <dd class="mt-0.5 text-sm tabular-nums text-tx">{{ $payment->payment_date->format('d.m.Y') }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-mut">Tutar</dt>
                    <dd class="mt-0.5 text-sm tabular-nums text-tx">
                        {{ $payment->amountMoney()->format($payment->currency) }}
                        <x-ui.fx-note :rate="$payment->exchange_rate_to_try" :equivalent="$payment->tryEquivalentMoney()" />
                        @if ($payment->isMissingExchangeRate())
                            <span class="mt-0.5 block text-xs text-mut">Kur bilgisi eksik (geçmiş kayıt).</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-mut">Ödeme yöntemi</dt>
                    <dd class="mt-0.5 text-sm text-tx">{{ $payment->payment_method?->label() ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-mut">Ödeyen</dt>
                    <dd class="mt-0.5 text-sm text-tx">{{ $payment->payer_name ?: 'Geçmiş kayıtta belirtilmemiş' }}
                        @if ($payment->payer_type)<span class="block text-xs text-mut">{{ $payment->payer_type->label() }}</span>@endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-mut">Referans no</dt>
                    <dd class="mt-0.5 text-sm tabular-nums text-tx">{{ $payment->reference_no ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-mut">Makbuz no</dt>
                    <dd class="mt-0.5 text-sm tabular-nums text-tx">
                        @if ($payment->receipt_no !== null)
                            <a href="{{ route('payments.receipt', $payment) }}"
                               class="text-acc-tx underline underline-offset-2 hover:text-tx">{{ $payment->receipt_no }}</a>
                        @else
                            — <span class="text-xs text-mut">(bu özellikten önce oluşturuldu)</span>
                        @endif
                    </dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-xs uppercase tracking-wide text-mut">Açıklama</dt>
                    <dd class="mt-0.5 text-sm whitespace-pre-line text-tx">{{ $payment->description ?: '—' }}</dd>
                </div>
            </dl>
        </x-ui.card>

        <x-ui.card title="Kayıt bilgisi" class="p-6">
            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-mut">Oluşturan</dt>
                    <dd class="mt-0.5 text-tx">
                        {{ $payment->creator?->name ?? '—' }}
                        <span class="text-mut">· {{ $payment->created_at->format('d.m.Y H:i') }}</span>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-mut">Son güncelleyen</dt>
                    <dd class="mt-0.5 text-tx">
                        {{ $payment->updater?->name ?? '—' }}
                        <span class="text-mut">· {{ $payment->updated_at->format('d.m.Y H:i') }}</span>
                    </dd>
                </div>
    @if ($payment->isCancelled())
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-mut">İptal eden</dt>
                        <dd class="mt-0.5 text-tx">
                            {{ $payment->canceller?->name ?? '—' }}
                            <span class="text-mut">· {{ $payment->cancelled_at?->format('d.m.Y H:i') }}</span>
                        </dd>
                    </div>
                @endif
            </dl>

            @if ($payment->company)
                <div class="mt-6">
                    <x-ui.button variant="secondary" :href="route('companies.show', $payment->company)">
                        Cari hesap kartına git
                    </x-ui.button>
                </div>
            @endif
        </x-ui.card>
    </div>

    @can('cancel', $payment)
        <x-ui.card title="Tahsilatı iptal et" class="mt-4 p-6">
            <p class="text-sm text-mut">
                İptal, cari hareketini ve konaklama dağılımlarını geri alır; borcu yeniden açar.
                Kayıt silinmez, listede kalır. <strong class="text-tx">Bu işlem geri alınamaz.</strong>
            </p>

            <form method="POST" action="{{ route('payments.cancel', $payment) }}" class="mt-4 max-w-xl"
                  onsubmit="return confirm('Bu tahsilat iptal edilsin mi? Cari hareketi ters kaydedilecek ve bu işlem geri alınamayacak.')">
                @csrf @method('PATCH')

                <x-ui.input label="İptal gerekçesi" name="cancellation_reason" required maxlength="255"
                            placeholder="Örn. yanlış cari hesaba işlendi" />

                <div class="mt-4">
                    <x-ui.button type="submit" variant="danger">Tahsilatı iptal et</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    @endcan
@endsection
