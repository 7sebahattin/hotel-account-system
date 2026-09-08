@extends('layouts.app')
@section('title', 'Kasa Defteri')

@section('content')
    @php
        /*
            Alt başlık burada kurulur, bileşen etiketinde DEĞİL: Blade'in
            bileşen etiketi ayrıştırıcısı öznitelik değerini çift tırnakla
            sınırlar, gömülü bir çift tırnak etiketi erken kapatır ve şablon
            sessizce derlenmez hâle gelir.
        */
        $subtitle = $session
            ? $session->openedBy?->name.' · '.$session->opened_at->format('d.m.Y H:i').' itibarıyla kasada'
            : 'Açık vardiya yok';
    @endphp

    <x-ui.page-header title="Kasa Defteri" :subtitle="$subtitle">
        <x-slot:actions>
            @if ($canViewAll)
                <x-ui.button variant="ghost" :href="route('cashbook.day')">Günlük defter</x-ui.button>
            @endif

            @if ($session && $isOwner && ! ($summary?->isAwaitingHandover() ?? false))
                <x-ui.button :href="route('cashbook.movements.create')">+ Hareket ekle</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    {{-- ----------------------------------------------- devir kabul kartı --}}
    @if ($incomingTransfer)
        @include('cashbook::transfers._incoming', ['transfer' => $incomingTransfer])
    @endif

    @if ($session === null)
        {{-- ------------------------------------------------- boş durum --}}
        <x-ui.card class="mt-4 p-10 text-center">
            <h2 class="text-base font-semibold text-tx">Henüz açık kasa yok</h2>

            <p class="mx-auto mt-2 max-w-md text-sm text-mut">
                Kasa hareketi girebilmek için önce vardiyayı açmanız gerekir. Açılışta
                kasada bulunan parayı para birimi bazında girersiniz.
            </p>

            @can('create', \App\Modules\CashBook\Models\CashSession::class)
                <div class="mt-5 flex justify-center">
                    <x-ui.button :href="route('cashbook.sessions.create')">Kasayı aç</x-ui.button>
                </div>
            @endcan
        </x-ui.card>
    @elseif ($summary === null)
        {{--
            Vardiya başkasında: varlığı ve sahibi görünür, TUTARLAR görünmez.
            Personelin kasanın kimde olduğunu bilmesi operasyonel ihtiyaçtır;
            başkasının kasasındaki parayı görmesi değil.
        --}}
        <x-ui.card class="mt-4">
            <h2 class="text-sm font-semibold text-tx">Kasa şu anda {{ $session->openedBy?->name }} vardiyasında</h2>

            <p class="mt-1 text-sm text-mut">
                {{ $session->opened_at->format('d.m.Y H:i') }}'te açıldı. Kasa tutarlarını yalnızca
                vardiya sahibi ve yetkili yöneticiler görür.
            </p>
        </x-ui.card>
    @else
        {{-- ------------------------------------------ kasada beklenen --}}
        @if ($summary->isAwaitingHandover())
            <x-ui.alert tone="info" class="mt-4">
                Devir <strong class="font-medium">{{ $summary->outgoingTransfer->toUser?->name }}</strong>
                tarafından kabul bekliyor. Kabul edilene kadar bu vardiyaya yeni hareket girilemez.
            </x-ui.alert>
        @endif

        @include('cashbook::_expected', ['summary' => $summary, 'title' => 'Kasada beklenen'])

        @if ($isOwner)
            <div class="mt-4 flex flex-wrap gap-2">
                @if ($summary->isAwaitingHandover())
                    <form method="POST" action="{{ route('cashbook.transfers.cancel', $summary->outgoingTransfer) }}">
                        @csrf
                        @method('PATCH')
                        <x-ui.button type="submit" variant="danger"
                                     onclick="return confirm('Devir iptal edilsin mi? Kasa sizde kalmaya devam eder.')">
                            Devri iptal et
                        </x-ui.button>
                    </form>
                @else
                    {{--
                        Birincil aksiyon ("+ Hareket ekle") sayfa başlığındadır:
                        kaydırmadan erişilebilir olmalıdır. Burada YALNIZCA
                        ikincil aksiyonlar durur; aynı butonu iki kez çizmek
                        kullanıcıya iki farklı şey varmış izlenimi verir.
                    --}}
                    <x-ui.button variant="secondary" :href="route('cashbook.transfers.create')">Kasayı devret</x-ui.button>
                    <x-ui.button variant="ghost" :href="route('cashbook.sessions.close', $session)">Vardiyayı kapat</x-ui.button>
                @endif
            </div>
        @endif

        {{-- ------------------------------------------- son hareketler --}}
        <x-ui.panel title="Son hareketler" class="mt-4"
                    :href="route('cashbook.sessions.show', $session)" link-label="Vardiya detayı">
            @forelse ($movements as $movement)
                @include('cashbook::movements._row', ['movement' => $movement, 'canReverse' => $isOwner])
            @empty
                <x-ui.empty class="m-4 border-0 bg-transparent py-8">
                    Bu vardiyada henüz hareket yok.
                </x-ui.empty>
            @endforelse
        </x-ui.panel>
    @endif
@endsection
