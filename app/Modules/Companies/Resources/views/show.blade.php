@extends('layouts.app')
@section('title', $company->name)

@section('content')
    <x-ui.page-header :title="$company->name">
        <x-slot:meta>
            <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-3">
                <span class="text-sm text-mut">{{ $company->account_type->label() }} · #{{ $company->id }}</span>
                @include('companies::_status', ['company' => $company])

                {{--
                    İletişim bilgileri üstte, tek satırda: ekranı açan kişinin
                    ilk ihtiyacı firmayı arayabilmek. Boş alanlar hiç çizilmez.

                    tel:/mailto: linkleri min-h-11 ile 44px dokunma yüksekliğini
                    garanti eder (padding tahminine güvenilmez); gap-y-3 satırın
                    büyümesine zaten pay bırakır.
                --}}
                @if ($company->phone)
                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', $company->phone) }}"
                       class="inline-flex min-h-11 items-center text-sm text-mut underline-offset-2 hover:text-tx hover:underline">
                        {{ $company->phone }}
                    </a>
                @endif

                @if ($company->email)
                    <a href="mailto:{{ $company->email }}"
                       class="inline-flex min-h-11 items-center truncate text-sm text-mut underline-offset-2 hover:text-tx hover:underline">
                        {{ $company->email }}
                    </a>
                @endif

                @if ($company->contact_person)
                    <span class="text-sm text-mut">{{ $company->contact_person }}</span>
                @endif

                @if ($company->city)
                    <span class="text-sm text-mut">{{ $company->city }}</span>
                @endif
            </div>
        </x-slot:meta>

        <x-slot:actions>
            @can('ledger.view')
                <x-ui.button variant="secondary" :href="route('statements.show', $company)">
                    Cari Ekstre
                </x-ui.button>
            @endcan

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

    @can('ledger.view')
        {{--
            Cari bakiye — para birimi bazında AYRI kartlar.
            TRY, EUR ve GBP hiçbir koşulda tek toplamda birleştirilmez (CLAUDE.md §6/L5).
        --}}
        <section class="mt-6" aria-labelledby="bakiye-basligi">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 id="bakiye-basligi" class="text-sm font-semibold text-tx">Cari bakiye</h2>

                <a href="{{ route('statements.show', $company) }}"
                   class="inline-flex min-h-11 shrink-0 items-center rounded px-2 text-xs text-acc-tx underline underline-offset-2 hover:text-tx">
                    Ekstreyi aç
                </a>
            </div>

            @if ($balances === [])
                <x-ui.empty class="mt-3">Bu firmanın cari hareketi yok.</x-ui.empty>
            @else
                <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($balances as $code => $balance)
                        @php
                            $currency = \App\Modules\Core\Enums\Currency::from($code);
                            $balanceFx = ($balancesFx ?? [])[$code] ?? null;
                        @endphp

                        <x-ui.card>
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-xs font-medium uppercase tracking-wide text-mut">
                                    {{ $currency->label() }}
                                </p>

                                <x-ui.badge tone="accent">{{ $code }}</x-ui.badge>
                            </div>

                            <p @class([
                                'mt-1 text-xl font-semibold tabular-nums',
                                'text-debit' => $balance->isPositive(),
                                'text-credit' => $balance->isNegative(),
                                'text-tx' => $balance->isZero(),
                            ])>{{ $balance->format($currency) }}</p>

                            @if ($currency !== \App\Modules\Core\Enums\Currency::TRY && $balanceFx)
                                <x-ui.fx-note :equivalent="$balanceFx['net']" />
                                @if (! $balanceFx['complete'])
                                    <p class="mt-0.5 text-xs text-mut">TL karşılığı eksik veri nedeniyle tam değil</p>
                                @endif
                            @endif

                            {{-- Anlam renge değil metne bağlıdır (CLAUDE.md §11.6). --}}
                            <p class="mt-0.5 text-xs text-mut">
                                @if ($balance->isPositive())
                                    {{ $company->account_type->value === 'individual' ? 'Kişi bize borçlu' : 'Firma bize borçlu' }}
                                @elseif ($balance->isNegative())
                                    {{ $company->account_type->value === 'individual' ? 'Biz kişiye borçluyuz' : 'Biz firmaya borçluyuz' }}
                                @else
                                    Bakiye kapalı
                                @endif
                            </p>
                        </x-ui.card>
                    @endforeach
                </div>
            @endif
        </section>
    @endcan

    <div class="mt-4 grid gap-4 lg:hidden">
        <x-ui.card title="Bilgiler" class="p-6 lg:col-span-2">
            @include('companies::_details-information')
        </x-ui.card>

        <x-ui.card title="Kayıt bilgisi" class="p-6">
            @include('companies::_audit-information')
        </x-ui.card>
    </div>

    <div class="mt-4 hidden space-y-3 lg:block">
        <details class="group rounded-xl border border-line bg-card">
            <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-3 px-4 py-2 text-sm font-medium text-tx focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-acc-tx">
                <span>Bilgiler</span>
                <svg class="size-4 shrink-0 text-mut transition-transform group-open:rotate-180" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <path d="m5 7.5 5 5 5-5" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" />
                </svg>
            </summary>
            <div class="border-t border-line px-4 py-4">
                @include('companies::_details-information', ['compact' => true])
            </div>
        </details>

        <details class="group rounded-xl border border-line bg-card">
            <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-3 px-4 py-2 text-sm font-medium text-tx focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-acc-tx">
                <span>Kayıt bilgisi</span>
                <svg class="size-4 shrink-0 text-mut transition-transform group-open:rotate-180" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <path d="m5 7.5 5 5 5-5" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" />
                </svg>
            </summary>
            <div class="border-t border-line px-4 py-4">
                @include('companies::_audit-information', ['compact' => true])
            </div>
        </details>
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
                Bu cari hesaba ait konaklama kaydı yok.
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
                                <span class="text-right">
                                    <span class="block text-sm tabular-nums text-tx">{{ $stay->amountMoney()->format($stay->currency) }}</span>
                                    <x-ui.fx-note :rate="$stay->exchange_rate_to_try" :equivalent="$stay->tryEquivalentMoney()" class="text-right" />
                                </span>
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
                            Cari Tahsilat
                        </x-ui.button>
                    @endunless
                @endcan
            </div>
        </div>

        @if ($recentPayments->isEmpty())
            <x-ui.empty class="mt-4 border-line bg-transparent">
                Bu cari hesaba ait tahsilat kaydı yok.
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
                                <span class="text-right">
                                    <span class="block text-sm tabular-nums text-tx">{{ $payment->amountMoney()->format($payment->currency) }}</span>
                                    <x-ui.fx-note :rate="$payment->exchange_rate_to_try" :equivalent="$payment->tryEquivalentMoney()" class="text-right" />
                                </span>
                                @include('payments::_status', ['payment' => $payment])
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>
@endsection
