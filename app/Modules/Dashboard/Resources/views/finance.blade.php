@extends('layouts.app')
@section('title', 'Pano — '.config('app.name'))

@section('lemon-bar-tabs')
    @include('dashboard::_view-switch', ['active' => 'finance'])
@endsection

{{--
    Finans görünümünde kasa verisi HİÇ YÜKLENMEZ (kullanıcı talimatı §17), bu
    yüzden Lemon Bar'daki canlı durum metni burada boştur — o veriye zaten
    sahip olmayan bir ekranda göstermek için ayrı bir sorgu YAZILMAZ.
--}}

@section('content')
    <x-ui.page-header
        title="Pano"
        :subtitle="$today->translatedFormat('d F Y').' · Finans'" />

    <div class="mt-4 md:hidden">
        @include('dashboard::_view-switch', ['active' => 'finance'])
    </div>

    @if ($summary->isEmpty())
        {{-- Boş kurulum: kullanıcı ne yapacağını buradan öğrenir. --}}
        <x-ui.card class="mt-6 p-10 text-center">
            <h2 class="text-base font-semibold text-tx">Sistem henüz boş</h2>

            <p class="mx-auto mt-2 max-w-md text-sm text-mut">
                Cari takibe başlamak için önce bir firma oluşturun. Firma kaydedildikten sonra
                konaklama ve tahsilat girdikçe bakiyeler burada görünür.
            </p>

            <div class="mt-5 flex flex-wrap justify-center gap-2">
                @can('companies.create')
                    <x-ui.button :href="route('companies.create')">Firma ekle</x-ui.button>
                @endcan

                <x-ui.button variant="secondary" :href="route('companies.index')">Firmalar</x-ui.button>
            </div>
        </x-ui.card>
    @else
        {{-- ==================================================== A) CARİ DURUM --}}
        <section class="mt-6">
            <h2 class="text-base font-semibold text-tx">Cari durum</h2>

            <p class="mt-1 text-sm text-mut">
                Para birimleri ayrı hesaplanır ve <strong class="font-medium text-tx">birbirine çevrilmez</strong>.
            </p>

            <div class="mt-3 space-y-4">
                @foreach ($summary->currencies as $totals)
                    <x-ui.card class="p-0">
                        <div class="border-b border-line px-4 py-3">
                            <h3 class="flex items-center gap-2 text-sm font-semibold text-tx">
                                <span>{{ $totals->currency->label() }}</span>
                                <x-ui.badge tone="accent">{{ $totals->currency->value }}</x-ui.badge>
                            </h3>
                        </div>

                        <div class="grid grid-cols-2 gap-4 p-4 sm:grid-cols-3 lg:grid-cols-5">
                            <x-ui.stat
                                label="Açık alacak"
                                tone="debit"
                                :value="$totals->receivable->format($totals->currency)"
                                hint="Firmaların bize borcu" />

                            <x-ui.stat
                                label="Açık borç"
                                tone="credit"
                                :value="$totals->payable->format($totals->currency)"
                                hint="Bizim firmalara borcumuz" />

                            <div>
                                <x-ui.stat
                                    label="Net bakiye"
                                    :tone="$totals->net->isNegative() ? 'credit' : 'debit'"
                                    :value="$totals->net->format($totals->currency)"
                                    :hint="$totals->net->isNegative() ? 'Net borçluyuz' : 'Net alacaklıyız'" />
                                @if ($totals->currency !== \App\Modules\Core\Enums\Currency::TRY)
                                    @if ($totals->netTl !== null)
                                        <x-ui.fx-note :equivalent="$totals->netTl" />
                                    @else
                                        <p class="mt-0.5 text-xs text-mut">TL karşılığı eksik veri nedeniyle tam değil</p>
                                    @endif
                                @endif
                            </div>

                            <x-ui.stat
                                label="Bu ay konaklama"
                                :value="$totals->staysThisMonth->format($totals->currency)"
                                hint="Çıkış tarihine göre" />

                            <x-ui.stat
                                label="Bu ay tahsilat"
                                :value="$totals->paymentsThisMonth->format($totals->currency)"
                                hint="Tahsilat tarihine göre" />
                        </div>
                    </x-ui.card>
                @endforeach

                <x-ui.card class="flex flex-wrap items-center justify-between gap-3">
                    <x-ui.stat label="Aktif firma" :value="$summary->activeCompanyCount" hint="Pasif ve silinmiş firmalar hariç" />

                    <x-ui.button variant="secondary" :href="route('companies.index')">Firmalar</x-ui.button>
                </x-ui.card>
            </div>
        </section>

        {{-- =========================================== B) EN YÜKSEK BAKİYELİ FİRMALAR --}}
        <section class="mt-8">
            <h2 class="text-base font-semibold text-tx">En yüksek bakiyeli firmalar</h2>

            <div class="mt-3">
                {{--
                    Panel satırları TÜMÜYLE dokunulabilirdir (CLAUDE.md §11 —
                    "Satır/card tamamı kolay dokunulabilir olsun"): `<a>` yalnızca
                    başlığı değil satırın tamamını (tutar dahil) sarar.
                --}}
                <x-ui.panel title="Firma bazında açık alacak" :href="route('statements.index')" link-label="Bakiye listesi">
                    @forelse ($summary->topCompanies as $code => $rows)
                        <div @class(['px-4 py-3', 'border-t border-line' => ! $loop->first])>
                            <p class="pb-2 text-xs font-medium uppercase tracking-wide text-mut">{{ $code }}</p>

                            <ul>
                                @foreach ($rows as $row)
                                    <li>
                                        <a href="{{ route('companies.show', $row->companyId) }}"
                                           class="-mx-2 flex min-h-11 items-center justify-between gap-3 rounded-lg px-2 hover:bg-well">
                                            <span class="min-w-0 truncate text-sm text-tx">{{ $row->companyName }}</span>

                                            <span class="shrink-0 text-right">
                                                <span class="block text-sm font-medium tabular-nums text-debit">
                                                    {{ $row->balance->format($row->currency) }}
                                                </span>
                                                @if ($row->balanceTl !== null)
                                                    <x-ui.fx-note :equivalent="$row->balanceTl" class="text-right" />
                                                @endif
                                            </span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @empty
                        <x-ui.empty class="m-4 border-0 bg-transparent py-8">
                            Bize borçlu firma yok.
                        </x-ui.empty>
                    @endforelse
                </x-ui.panel>
            </div>
        </section>

        <div class="mt-8 grid items-start gap-4 lg:grid-cols-2">
            {{-- ===================================================== C) SON TAHSİLATLAR --}}
            <section>
                <h2 class="text-base font-semibold text-tx">Son tahsilatlar</h2>

                <x-ui.panel title="Son tahsilatlar" class="mt-3" :href="route('payments.index')">
                    @forelse ($summary->recentPayments as $payment)
                        <a href="{{ route('payments.show', $payment) }}"
                           @class(['block px-4 py-3 hover:bg-well', 'border-t border-line' => ! $loop->first])>
                            <div class="flex items-baseline justify-between gap-3">
                                <span class="min-w-0 truncate text-sm text-tx">
                                    {{ $summary->companyName((int) $payment->company_id) }}
                                </span>

                                {{-- İptal edilmiş tahsilat "alacak" rengiyle gösterilmez. --}}
                                <span class="shrink-0 text-right">
                                    <span @class([
                                        'block text-sm font-medium tabular-nums',
                                        'text-credit' => ! $payment->isCancelled(),
                                        'text-mut line-through' => $payment->isCancelled(),
                                    ])>{{ $payment->amountMoney()->format($payment->currency) }}</span>
                                    <x-ui.fx-note :rate="$payment->exchange_rate_to_try" :equivalent="$payment->tryEquivalentMoney()" class="text-right" />
                                </span>
                            </div>

                            <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-mut">
                                <span class="tabular-nums">{{ $payment->payment_date->format('d.m.Y') }}</span>

                                @if ($payment->payment_method)
                                    <span aria-hidden="true">·</span>
                                    <span>{{ $payment->payment_method->label() }}</span>
                                @endif

                                @if ($payment->isCancelled())
                                    <x-ui.badge tone="danger">İptal</x-ui.badge>
                                @endif
                            </div>
                        </a>
                    @empty
                        <x-ui.empty class="m-4 border-0 bg-transparent py-8">Henüz tahsilat yok.</x-ui.empty>
                    @endforelse
                </x-ui.panel>
            </section>

            {{-- ================================================ D) YAKLAŞAN KONAKLAMALAR --}}
            <section>
                <h2 class="text-base font-semibold text-tx">Yaklaşan konaklamalar</h2>

                <x-ui.panel title="Devam eden ve yaklaşan konaklamalar" class="mt-3" :href="route('stays.index')">
                    @forelse ($summary->upcomingStays as $stay)
                        <a href="{{ route('stays.show', $stay) }}"
                           @class(['block px-4 py-3 hover:bg-well', 'border-t border-line' => ! $loop->first])>
                            <div class="flex items-baseline justify-between gap-3">
                                <span class="min-w-0 truncate text-sm text-tx">{{ $stay->guest_name }}</span>

                                <span class="shrink-0 text-right">
                                    <span class="block text-sm font-medium tabular-nums text-tx">
                                        {{ $stay->amountMoney()->format($stay->currency) }}
                                    </span>
                                    <x-ui.fx-note :rate="$stay->exchange_rate_to_try" :equivalent="$stay->tryEquivalentMoney()" class="text-right" />
                                </span>
                            </div>

                            <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-mut">
                                <span class="truncate">{{ $summary->companyName((int) $stay->company_id) }}</span>
                                <span aria-hidden="true">·</span>
                                <span class="tabular-nums">
                                    {{ $stay->check_in_date->format('d.m.Y') }} – {{ $stay->check_out_date->format('d.m.Y') }}
                                </span>
                            </div>
                        </a>
                    @empty
                        <x-ui.empty class="m-4 border-0 bg-transparent py-8">
                            Devam eden veya yaklaşan konaklama yok.
                        </x-ui.empty>
                    @endforelse
                </x-ui.panel>
            </section>
        </div>
    @endif
@endsection
