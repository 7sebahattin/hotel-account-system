@extends('layouts.app')
@section('title', 'Pano — '.config('app.name'))

@php
    use App\Modules\CashBook\Enums\CashMovementType;
@endphp

{{-- Masaüstü Lemon Bar: sekme + (varsa) kritik durum metni. --}}
@section('lemon-bar-tabs')
    @include('dashboard::_view-switch', ['active' => 'operations'])
@endsection

@section('lemon-bar-status')
    @if ($summary->needsAttention())
        <span class="hidden items-center gap-1.5 text-sm text-danger md:inline-flex">
            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" class="size-4 shrink-0" aria-hidden="true">
                <path d="{{ \App\Modules\Core\Support\NavIcons::path('warning') }}" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Dikkat gereken durum var
        </span>
    @elseif ($summary->hasActiveSession())
        <span class="hidden items-center gap-1.5 text-sm text-mut md:inline-flex">
            <span class="size-1.5 shrink-0 rounded-full bg-credit" aria-hidden="true"></span>
            Kasa açık · {{ $summary->cash->session->openedBy?->name }} · {{ $summary->cash->movementCount }} hareket
        </span>
    @endif
@endsection

@section('lemon-bar-status-icon')
    @if ($summary->needsAttention())
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
        :subtitle="$today->translatedFormat('d F Y').' · Operasyon'" />

    {{-- Mobilde sekme sayfa içeriğinin başında ayrı bir satırdır (kullanıcı talimatı §12). --}}
    <div class="mt-4 md:hidden">
        @include('dashboard::_view-switch', ['active' => 'operations'])
    </div>

    {{-- =========================================================== A) ŞU AN --}}
    <section class="mt-6">
        <h2 class="text-base font-semibold text-tx">Şu an</h2>

        @if ($summary->hasActiveSession())
            <x-ui.card class="mt-3 p-4">
                <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                    <x-ui.badge tone="credit">Kasa Açık</x-ui.badge>
                    <span class="font-medium text-tx">{{ $summary->cash->session->openedBy?->name }}</span>
                    <span aria-hidden="true" class="text-mut">·</span>
                    <span class="tabular-nums text-mut">
                        {{ $summary->cash->session->opened_at->format('H:i') }}'ten beri
                    </span>
                    <span aria-hidden="true" class="text-mut">·</span>
                    <span class="tabular-nums text-mut">{{ $summary->cash->movementCount }} hareket</span>
                </div>
            </x-ui.card>
        @else
            <x-ui.card class="mt-3 p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <span class="flex items-center gap-2 text-sm text-mut">
                        <x-ui.badge>Kasa Kapalı</x-ui.badge>
                        Açık vardiya yok.
                    </span>

                    <x-ui.button variant="secondary" :href="route('cashbook.index')">Kasa Defteri</x-ui.button>
                </div>
            </x-ui.card>
        @endif
    </section>

    {{-- ========================================================= B) KASADA --}}
    @if ($summary->hasActiveSession() && $summary->cash->hasExpectedCash())
        <section class="mt-6">
            <h2 class="text-base font-semibold text-tx">Kasada</h2>
            <p class="mt-1 text-sm text-mut">Beklenen fiziksel kasa. Para birimleri ayrı hesaplanır, birleştirilmez.</p>

            <div class="mt-3 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($summary->cash->expected as $code => $money)
                    <x-ui.card class="p-4">
                        <x-ui.stat :label="$code" :value="$money->format(\App\Modules\Core\Enums\Currency::from($code))" />
                    </x-ui.card>
                @endforeach
            </div>
        </section>
    @endif

    {{-- =========================================================== C) BUGÜN --}}
    @if ($summary->hasTodayActivity())
        <section class="mt-6">
            <h2 class="text-base font-semibold text-tx">Bugün</h2>

            <div class="mt-3 space-y-3">
                @foreach ($summary->todayTotals as $code => $totals)
                    <x-ui.card class="p-0">
                        <div class="border-b border-line px-4 py-2.5">
                            <h3 class="text-sm font-semibold text-tx">{{ $code }}</h3>
                        </div>

                        <div class="grid grid-cols-2 gap-4 p-4 sm:grid-cols-4">
                            <x-ui.stat label="Oda geliri" tone="credit" :value="$totals->roomIncome->format($totals->currency)" />
                            <x-ui.stat label="Ek gelir" tone="credit" :value="$totals->extraIncome->format($totals->currency)" />
                            <x-ui.stat label="Gider" :value="$totals->expense->format($totals->currency)" />
                            <x-ui.stat label="Ara kasa / çıkış" :value="$totals->cashOut->format($totals->currency)" hint="Gider değildir" />
                        </div>
                    </x-ui.card>
                @endforeach
            </div>
        </section>
    @endif

    {{-- ================================================ D) DİKKAT GEREKENLER --}}
    @if ($summary->needsAttention())
        <section class="mt-6">
            <h2 class="text-base font-semibold text-tx">Dikkat gerekenler</h2>

            <div class="mt-3 space-y-2">
                @if ($summary->pendingOutgoingTransfer)
                    <x-ui.alert tone="danger" title="Bekleyen kasa devri">
                        {{ $summary->pendingOutgoingTransfer->fromUser?->name }} →
                        {{ $summary->pendingOutgoingTransfer->toUser?->name }} devri kabul bekliyor.
                        <a href="{{ route('cashbook.index') }}" class="font-medium underline underline-offset-2">Kasa defteri</a>
                    </x-ui.alert>
                @endif

                @if ($summary->hasCountDifferenceToday)
                    <x-ui.alert tone="danger" title="Kasa farkı">
                        Bugünkü bir vardiyanın kapanış sayımında fark var.
                        <a href="{{ route('cashbook.day') }}" class="font-medium underline underline-offset-2">Günlük defter</a>
                    </x-ui.alert>
                @endif
            </div>
        </section>
    @endif

    {{-- ====================================================== E) SON HAREKETLER --}}
    @if ($summary->hasActiveSession())
        <section class="mt-6">
            <h2 class="text-base font-semibold text-tx">Son hareketler</h2>

            <x-ui.panel title="Açık vardiyadaki son hareketler" class="mt-3" :href="route('cashbook.index')" link-label="Kasa defteri">
                @forelse ($summary->recentMovements as $movement)
                    <div @class(['flex items-center justify-between gap-3 px-4 py-3', 'border-t border-line' => ! $loop->first])>
                        <div class="min-w-0">
                            <p class="truncate text-sm text-tx">{{ $movement->type->label() }}</p>
                            <p class="mt-0.5 flex items-center gap-2 text-xs text-mut">
                                <span class="tabular-nums">{{ $movement->entry_at->format('H:i') }}</span>
                                @if ($movement->is_reversal)
                                    <x-ui.badge tone="danger">Ters kayıt</x-ui.badge>
                                @endif
                            </p>
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
        </section>
    @endif
@endsection
