@extends('layouts.app')
@section('title', 'Pano — '.config('app.name'))

{{--
    Gün 11: personel panosu artık "Vardiyam / Kasam" mantığıyla çalışır —
    finansal yönetim panosu DEĞİLDİR. Firma cari bakiyesi, toplam alacak,
    yönetim KPI'ları burada hiç yoktur (CLAUDE.md §7.4) ve controller bu
    view'a hiç sorgulamadan gelir. Kasa TUTARI istisnadır (Gün 10): personelin
    ELİNDEKİ para, firma parası değildir.
--}}

@section('lemon-bar-status')
    @if ($summary->ownsActiveSession && $summary->mySummary?->isAwaitingHandover())
        <span class="hidden items-center gap-1.5 text-sm text-danger md:inline-flex">
            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" class="size-4 shrink-0" aria-hidden="true">
                <path d="{{ \App\Modules\Core\Support\NavIcons::path('warning') }}" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Kasa devri kabul bekliyor
        </span>
    @elseif ($summary->ownsActiveSession)
        <span class="hidden items-center gap-1.5 text-sm text-mut md:inline-flex">
            <span class="size-1.5 shrink-0 rounded-full bg-credit" aria-hidden="true"></span>
            Kasa sizde · {{ $summary->mySummary?->movementCount }} hareket
        </span>
    @endif
@endsection

@section('lemon-bar-status-icon')
    @if (($summary->ownsActiveSession && $summary->mySummary?->isAwaitingHandover()) || $summary->pendingIncomingTransfer)
        <span class="mr-1 flex size-8 shrink-0 items-center justify-center text-danger" aria-hidden="true">
            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" class="size-5" aria-hidden="true">
                <path d="{{ \App\Modules\Core\Support\NavIcons::path('warning') }}" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span class="sr-only">Dikkat gereken durum var</span>
        </span>
    @endif
@endsection

@section('content')
    <x-ui.page-header
        title="Pano"
        :subtitle="'Hoş geldiniz, '.auth()->user()->name" />

    {{-- ================================================ ÖNCELİK: BEKLEYEN DEVİR --}}
    @if ($summary->pendingIncomingTransfer)
        <x-ui.alert tone="danger" title="Kasa devri sizi bekliyor" class="mt-6">
            <p>{{ $summary->pendingIncomingTransfer->fromUser?->name }} size kasa devretmek istiyor.</p>
            <x-ui.button :href="route('cashbook.index')" class="mt-3">Devri İncele</x-ui.button>
        </x-ui.alert>
    @endif

    {{--
        ===================================================================== VARDİYAM

        Bekleyen bir devir varken (henüz vardiyası olmayan hedef kullanıcı) bu
        bölüm hiç render edilmez — yukarıdaki öncelikli uyarı zaten tek anlamlı
        içeriktir; boş bir "Vardiyam" başlığı altında hiçbir şey göstermek
        gereksiz görsel gürültü olurdu (kullanıcı talimatı §14).
    --}}
    @unless ($summary->pendingIncomingTransfer && ! $summary->ownsActiveSession)
    <section class="mt-6">
        <h2 class="text-base font-semibold text-tx">Vardiyam</h2>

        @if ($summary->ownsActiveSession)
            <x-ui.card class="mt-3 p-4">
                <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                    <x-ui.badge tone="credit">Kasa Sizde</x-ui.badge>
                    <span class="tabular-nums text-mut">
                        {{ $summary->activeSession->opened_at->format('H:i') }}'ten beri
                    </span>
                    <span aria-hidden="true" class="text-mut">·</span>
                    <span class="tabular-nums text-mut">{{ $summary->mySummary?->movementCount }} hareket</span>
                </div>
            </x-ui.card>

            {{-- ================================================================= KASAM --}}
            @if ($summary->mySummary && $summary->mySummary->totals !== [])
                <h2 class="mt-6 text-base font-semibold text-tx">Kasam</h2>

                <div class="mt-3 grid grid-cols-2 gap-4 sm:grid-cols-3">
                    @foreach ($summary->mySummary->totals as $code => $totals)
                        <x-ui.card class="p-4">
                            <x-ui.stat :label="$code" :value="$totals->expected()->format($totals->currency)" />
                        </x-ui.card>
                    @endforeach
                </div>
            @endif

            {{--
                Hızlı aksiyonlar. Dar ekranda 3 öncelikli aksiyon + "Diğer
                işlemler"; geniş ekranda 5'i birden (kullanıcı talimatı §3 —
                "kararı gerçek browser QA ile ver"). Görünürlük SARMALAYICI
                `<div>` düzeyinde değiştirilir — `<x-ui.button>`'ın kendi
                `inline-flex` temel sınıfıyla AYNI eleman üzerinde `hidden`
                çakıştırmak Tailwind'de öngörülemez kazanır (ikisi de temel
                `display` yardımcı sınıfıdır); sarmalayıcıda tutmak bunu önler.
            --}}
            <div class="mt-4 flex flex-wrap gap-2">
                <x-ui.button :href="route('cashbook.movements.create', ['type' => 'room_income'])">+ Oda Geliri</x-ui.button>
                <x-ui.button variant="secondary" :href="route('cashbook.movements.create', ['type' => 'expense'])">− Gider</x-ui.button>
                <x-ui.button variant="secondary" :href="route('cashbook.transfers.create')">⇄ Kasayı Devret</x-ui.button>

                <div class="hidden gap-2 sm:flex">
                    <x-ui.button variant="ghost" :href="route('cashbook.movements.create', ['type' => 'extra_income'])">+ Ek Gelir</x-ui.button>
                    <x-ui.button variant="ghost" :href="route('cashbook.movements.create', ['type' => 'cash_out'])">Kasa Çıkışı</x-ui.button>
                </div>

                <div class="sm:hidden">
                    <x-ui.button variant="ghost" :href="route('cashbook.movements.create')">Diğer işlemler</x-ui.button>
                </div>
            </div>

            {{-- ================================================================ DİKKAT --}}
            @if ($summary->mySummary?->isAwaitingHandover())
                <x-ui.alert tone="danger" title="Devir kabul bekliyor" class="mt-4">
                    Devrettiğiniz kasa henüz karşı tarafça kabul edilmedi; kasa hâlâ sizin vardiyanızda görünür.
                </x-ui.alert>
            @endif

            {{-- ========================================================== SON HAREKETLER --}}
            <h2 class="mt-6 text-base font-semibold text-tx">Son hareketler</h2>

            <x-ui.panel title="Vardiyamdaki son hareketler" class="mt-3" :href="route('cashbook.index')" link-label="Kasa defteri">
                @forelse ($summary->myRecentMovements as $movement)
                    <div @class(['flex items-center justify-between gap-3 px-4 py-3', 'border-t border-line' => ! $loop->first])>
                        <div class="min-w-0">
                            <p class="truncate text-sm text-tx">{{ $movement->type->label() }}</p>
                            <p class="mt-0.5 tabular-nums text-xs text-mut">{{ $movement->entry_at->format('H:i') }}</p>
                        </div>

                        <span @class([
                            'shrink-0 text-sm font-medium tabular-nums',
                            'text-credit' => $movement->direction->value === 'in',
                            'text-tx' => $movement->direction->value !== 'in',
                        ])>
                            {{ $movement->direction->value === 'in' ? '+' : '−' }}{{ $movement->amountMoney()->format($movement->currency) }}
                        </span>
                    </div>
                @empty
                    <x-ui.empty class="m-4 border-0 bg-transparent py-8">Bu vardiyada henüz hareket yok.</x-ui.empty>
                @endforelse
            </x-ui.panel>
        @else
            <x-ui.card class="mt-3 p-6">
                <p class="text-sm font-medium text-tx">Açık vardiyanız yok.</p>

                @if ($summary->hasSomeoneElsesSession())
                    <p class="mt-1 text-sm text-mut">
                        Kasa şu anda {{ $summary->activeSession?->openedBy?->name }} üzerinde.
                    </p>
                @else
                    <p class="mt-1 text-sm text-mut">Kasadan sorumlu olmak için bir vardiya açın.</p>
                @endif

                @if ($summary->canOpenSession && $summary->activeSession === null)
                    <x-ui.button :href="route('cashbook.sessions.create')" class="mt-3">Kasayı Aç</x-ui.button>
                @endif
            </x-ui.card>
        @endif
    </section>
    @endunless

    {{-- ========================================================================== BUGÜN --}}
    <section class="mt-8">
        <h2 class="text-base font-semibold text-tx">Bugün girdiğim kayıtlar</h2>

        <div class="mt-3 grid grid-cols-2 gap-4 sm:grid-cols-4">
            <x-ui.card>
                <x-ui.stat label="Bugün konaklama" :value="$summary->staysToday" hint="Girdiğiniz kayıt" />
            </x-ui.card>

            <x-ui.card>
                <x-ui.stat label="Bu hafta konaklama" :value="$summary->staysThisWeek" hint="Girdiğiniz kayıt" />
            </x-ui.card>

            <x-ui.card>
                <x-ui.stat label="Bugün tahsilat" :value="$summary->paymentsToday" hint="Girdiğiniz kayıt" />
            </x-ui.card>

            <x-ui.card>
                <x-ui.stat label="Bu hafta tahsilat" :value="$summary->paymentsThisWeek" hint="Girdiğiniz kayıt" />
            </x-ui.card>
        </div>

        <div class="mt-4 flex flex-wrap gap-2">
            @can('stays.create')
                <x-ui.button variant="secondary" :href="route('stays.create')">Konaklama ekle</x-ui.button>
            @endcan

            @can('payments.create')
                <x-ui.button variant="secondary" :href="route('payments.create')">Tahsilat ekle</x-ui.button>
            @endcan
        </div>

        @if ($summary->hasRecords())
            <div class="mt-4 grid items-start gap-4 lg:grid-cols-2">
                <x-ui.panel title="Son girdiğiniz konaklamalar" :href="route('stays.index')">
                    @forelse ($summary->recentStays as $stay)
                        <a href="{{ route('stays.show', $stay) }}"
                           @class(['block px-4 py-3 hover:bg-well', 'border-t border-line' => ! $loop->first])>
                            <p class="truncate text-sm text-tx">{{ $stay->guest_name }}</p>

                            <p class="mt-1 text-xs tabular-nums text-mut">
                                {{ $stay->check_in_date->format('d.m.Y') }} – {{ $stay->check_out_date->format('d.m.Y') }}
                            </p>
                        </a>
                    @empty
                        <x-ui.empty class="m-4 border-0 bg-transparent py-8">Konaklama kaydınız yok.</x-ui.empty>
                    @endforelse
                </x-ui.panel>

                <x-ui.panel title="Son girdiğiniz tahsilatlar" :href="route('payments.index')">
                    @forelse ($summary->recentPayments as $payment)
                        <a href="{{ route('payments.show', $payment) }}"
                           @class(['block px-4 py-3 hover:bg-well', 'border-t border-line' => ! $loop->first])>
                            <p class="truncate text-sm text-tx">{{ $payment->reference_no ?: 'Referans yok' }}</p>

                            <p class="mt-1 flex flex-wrap items-center gap-2 text-xs tabular-nums text-mut">
                                {{ $payment->payment_date->format('d.m.Y') }}

                                @if ($payment->isCancelled())
                                    <x-ui.badge tone="danger">İptal</x-ui.badge>
                                @endif
                            </p>
                        </a>
                    @empty
                        <x-ui.empty class="m-4 border-0 bg-transparent py-8">Tahsilat kaydınız yok.</x-ui.empty>
                    @endforelse
                </x-ui.panel>
            </div>
        @endif
    </section>
@endsection
