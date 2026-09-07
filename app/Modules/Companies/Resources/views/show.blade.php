@extends('layouts.app')
@section('title', $company->name)

@section('content')
    <x-ui.page-header :title="$company->name">
        <x-slot:meta>
            <div class="mt-2">@include('companies::_status', ['company' => $company])</div>
        </x-slot:meta>

        <x-slot:actions>
            @can('update', $company)
                <x-ui.button :href="route('companies.edit', $company)">Düzenle</x-ui.button>
            @endcan

            @can('delete', $company)
                <form method="POST" action="{{ route('companies.destroy', $company) }}"
                      onsubmit="return confirm('Bu firma silinsin mi? Kayıt saklanır, geri alınabilir.')">
                    @csrf @method('DELETE')
                    <x-ui.button type="submit" variant="danger">Sil</x-ui.button>
                </form>
            @endcan

            @can('restore', $company)
                <form method="POST" action="{{ route('companies.restore', $company) }}">
                    @csrf @method('PATCH')
                    <x-ui.button type="submit" variant="secondary">Geri al</x-ui.button>
                </form>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mt-6 grid gap-4 lg:grid-cols-3">
        <x-ui.card title="Bilgiler" class="p-6 lg:col-span-2">
            <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
                @foreach ([
                    'Vergi dairesi' => $company->tax_office,
                    'Vergi numarası' => $company->tax_number,
                    'Telefon' => $company->phone,
                    'E-posta' => $company->email,
                    'Yetkili kişi' => $company->contact_person,
                    'Şehir' => $company->city,
                ] as $label => $value)
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-mut">{{ $label }}</dt>
                        <dd class="mt-0.5 text-sm text-tx">{{ $value ?: '—' }}</dd>
                    </div>
                @endforeach

                <div class="sm:col-span-2">
                    <dt class="text-xs uppercase tracking-wide text-mut">Adres</dt>
                    <dd class="mt-0.5 text-sm whitespace-pre-line text-tx">{{ $company->address ?: '—' }}</dd>
                </div>

                <div class="sm:col-span-2">
                    <dt class="text-xs uppercase tracking-wide text-mut">Notlar</dt>
                    <dd class="mt-0.5 text-sm whitespace-pre-line text-tx">{{ $company->notes ?: '—' }}</dd>
                </div>
            </dl>
        </x-ui.card>

        <x-ui.card title="Kayıt bilgisi" class="p-6">
            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-mut">Oluşturan</dt>
                    <dd class="mt-0.5 text-tx">
                        {{ $company->creator?->name ?? '—' }}
                        <span class="text-mut">· {{ $company->created_at->format('d.m.Y H:i') }}</span>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-mut">Son güncelleyen</dt>
                    <dd class="mt-0.5 text-tx">
                        {{ $company->updater?->name ?? '—' }}
                        <span class="text-mut">· {{ $company->updated_at->format('d.m.Y H:i') }}</span>
                    </dd>
                </div>
            </dl>

            <p class="mt-6 rounded-md bg-well px-3 py-2 text-xs text-mut">
                Cari ekstre, Ledger ekranları geldiğinde bu karta eklenecektir.
            </p>
        </x-ui.card>
    </div>

    <x-ui.card class="mt-4 p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-sm font-medium text-tx">
                Konaklamalar <span class="tabular-nums text-mut">({{ $stayCount }})</span>
            </h2>

            <div class="flex flex-wrap items-center gap-2">
                @can('viewAny', \App\Modules\Stays\Models\Stay::class)
                    <x-ui.button variant="ghost" :href="route('stays.index', ['company_id' => $company->id])">
                        Tümünü gör
                    </x-ui.button>
                @endcan

                @can('create', \App\Modules\Stays\Models\Stay::class)
                    @unless ($company->trashed())
                        <x-ui.button :href="route('stays.create', ['company_id' => $company->id])">
                            Konaklama ekle
                        </x-ui.button>
                    @endunless
                @endcan
            </div>
        </div>

        @if ($recentStays->isEmpty())
            <x-ui.empty class="mt-4 border-line bg-transparent">
                Bu firmaya ait konaklama kaydı yok.
            </x-ui.empty>
        @else
            <ul class="mt-4">
                @foreach ($recentStays as $stay)
                    <li class="border-b border-line last:border-0">
                        <a href="{{ route('stays.show', $stay) }}"
                           class="flex flex-wrap items-center justify-between gap-2 py-3 hover:bg-well">
                            <span class="text-sm">
                                <span class="text-tx">{{ $stay->guest_name }}</span>
                                <span class="text-mut">
                                    · <span class="tabular-nums">{{ $stay->check_in_date->format('d.m.Y') }}</span>
                                    → <span class="tabular-nums">{{ $stay->check_out_date->format('d.m.Y') }}</span>
                                    · <span class="tabular-nums">{{ $stay->nights }}</span> gece
                                </span>
                            </span>

                            <span class="flex items-center gap-3">
                                <span class="text-sm tabular-nums text-tx">{{ $stay->amountMoney()->format($stay->currency) }}</span>
                                @include('stays::_status', ['stay' => $stay])
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>

    <x-ui.card class="mt-4 p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-sm font-medium text-tx">
                Tahsilatlar <span class="tabular-nums text-mut">({{ $paymentCount }})</span>
            </h2>

            <div class="flex flex-wrap items-center gap-2">
                @can('viewAny', \App\Modules\Payments\Models\Payment::class)
                    <x-ui.button variant="ghost" :href="route('payments.index', ['company_id' => $company->id])">
                        Tümünü gör
                    </x-ui.button>
                @endcan

                @can('create', \App\Modules\Payments\Models\Payment::class)
                    @unless ($company->trashed())
                        <x-ui.button :href="route('payments.create', ['company_id' => $company->id])">
                            Tahsilat ekle
                        </x-ui.button>
                    @endunless
                @endcan
            </div>
        </div>

        @if ($recentPayments->isEmpty())
            <x-ui.empty class="mt-4 border-line bg-transparent">
                Bu firmaya ait tahsilat kaydı yok.
            </x-ui.empty>
        @else
            <ul class="mt-4">
                @foreach ($recentPayments as $payment)
                    <li class="border-b border-line last:border-0">
                        <a href="{{ route('payments.show', $payment) }}"
                           class="flex flex-wrap items-center justify-between gap-2 py-3 hover:bg-well">
                            <span class="text-sm">
                                <span class="tabular-nums text-tx">{{ $payment->payment_date->format('d.m.Y') }}</span>
                                <span class="text-mut">
                                    · {{ $payment->payment_method?->label() ?? 'Yöntem belirtilmedi' }}
                                    @if ($payment->reference_no)
                                        · Ref <span class="tabular-nums">{{ $payment->reference_no }}</span>
                                    @endif
                                </span>
                            </span>

                            <span class="flex items-center gap-3">
                                <span class="text-sm tabular-nums text-tx">{{ $payment->amountMoney()->format($payment->currency) }}</span>
                                @include('payments::_status', ['payment' => $payment])
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>
@endsection
