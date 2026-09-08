@extends('layouts.app')
@section('title', 'Pano — '.config('app.name'))

@php
    use App\Modules\Ledger\Enums\LedgerEntryType;
@endphp

@section('content')
    <x-ui.page-header
        title="Pano"
        :subtitle="$today->translatedFormat('d F Y').' · '.auth()->user()->name" />

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
        {{--
            Gün 9.6: pano üç bilgi katmanına ayrıldı — "Şu anda bilmem gereken ne?"
            sorusuna cevap sırasıyla verilir:
              A) BUGÜN         — aksiyonlar + güncel/yaklaşan kayıtlar
              B) FİNANSAL DURUM — para birimi bazında bakiye (asla birleştirilmez)
              C) DİKKAT GEREKENLER — yüksek bakiyeli firmalar
            Veri katmanı (DashboardService/FinancialSummary) DEĞİŞMEDİ; bu yalnızca
            aynı verinin görünüm sırasıdır — ekranda hiçbir finansal hesap yapılmaz.
        --}}

        {{-- ============================================================ A) BUGÜN --}}
        <section class="mt-6">
            <h2 class="text-base font-semibold text-tx">Bugün</h2>

            @if (auth()->user()->canAny(['stays.create', 'payments.create', 'companies.create']))
                <div class="mt-3 flex flex-wrap gap-2">
                    @can('stays.create')
                        <x-ui.button :href="route('stays.create')">Yeni konaklama</x-ui.button>
                    @endcan

                    @can('payments.create')
                        <x-ui.button variant="secondary" :href="route('payments.create')">Yeni tahsilat</x-ui.button>
                    @endcan

                    @can('companies.create')
                        <x-ui.button variant="ghost" :href="route('companies.create')">Yeni firma</x-ui.button>
                    @endcan
                </div>
            @endif

            <div class="mt-3 grid items-start gap-4 lg:grid-cols-2">
                {{-- Satırın tamamı dokunulabilir — yalnızca başlık değil, tutar dahil. --}}

                {{-- Devam eden ve yaklaşan konaklamalar --}}
                <x-ui.panel title="Devam eden ve yaklaşan konaklamalar" :href="route('stays.index')">
                    @forelse ($summary->upcomingStays as $stay)
                        <a href="{{ route('stays.show', $stay) }}"
                           @class(['block px-4 py-3 hover:bg-well', 'border-t border-line' => ! $loop->first])>
                            <div class="flex items-baseline justify-between gap-3">
                                <span class="min-w-0 truncate text-sm text-tx">{{ $stay->guest_name }}</span>

                                <span class="shrink-0 text-sm font-medium tabular-nums text-tx">
                                    {{ $stay->amountMoney()->format($stay->currency) }}
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

                {{-- Son cari hareketler --}}
                <x-ui.panel title="Son cari hareketler">
                    @forelse ($summary->recentEntries as $entry)
                        @php $type = LedgerEntryType::fromEntry($entry); @endphp

                        <a href="{{ route('statements.show', $entry->company_id) }}"
                           @class(['block px-4 py-3 hover:bg-well', 'border-t border-line' => ! $loop->first])>
                            <div class="flex items-baseline justify-between gap-3">
                                <span class="min-w-0 truncate text-sm text-tx">
                                    {{ $summary->companyName((int) $entry->company_id) }}
                                </span>

                                @if ($entry->debitMoney()->isPositive())
                                    <span class="shrink-0 text-sm font-medium tabular-nums text-debit">
                                        {{ $entry->debitMoney()->format($entry->currency) }}
                                        <span class="sr-only">borç</span>
                                    </span>
                                @else
                                    <span class="shrink-0 text-sm font-medium tabular-nums text-credit">
                                        {{ $entry->creditMoney()->format($entry->currency) }}
                                        <span class="sr-only">alacak</span>
                                    </span>
                                @endif
                            </div>

                            <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-mut">
                                <x-ui.badge :tone="$entry->is_reversal ? 'danger' : 'neutral'">{{ $type->label() }}</x-ui.badge>
                                <span class="tabular-nums">{{ $entry->entry_date->format('d.m.Y') }}</span>
                                <span aria-hidden="true">·</span>
                                <span class="truncate">{{ $entry->description }}</span>
                            </div>
                        </a>
                    @empty
                        <x-ui.empty class="m-4 border-0 bg-transparent py-8">Henüz cari hareket yok.</x-ui.empty>
                    @endforelse
                </x-ui.panel>

                {{-- Son tahsilatlar --}}
                <x-ui.panel title="Son tahsilatlar" :href="route('payments.index')">
                    @forelse ($summary->recentPayments as $payment)
                        <a href="{{ route('payments.show', $payment) }}"
                           @class(['block px-4 py-3 hover:bg-well', 'border-t border-line' => ! $loop->first])>
                            <div class="flex items-baseline justify-between gap-3">
                                <span class="min-w-0 truncate text-sm text-tx">
                                    {{ $summary->companyName((int) $payment->company_id) }}
                                </span>

                                {{-- İptal edilmiş tahsilat "alacak" rengiyle gösterilmez:
                                     cariye etkisi ters kayıtla sıfırlanmıştır. --}}
                                <span @class([
                                    'shrink-0 text-sm font-medium tabular-nums',
                                    'text-credit' => ! $payment->isCancelled(),
                                    'text-mut line-through' => $payment->isCancelled(),
                                ])>{{ $payment->amountMoney()->format($payment->currency) }}</span>
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
            </div>
        </section>

        {{--
            Buraya İLERİDE (Kasa Defteri, Gün 10) "Kasada Şu An" kartı eklenecek:
            para birimi bazında fiziksel nakit, aktif vardiya, vardiya başlangıcı,
            son devir, işlem sayısı. Bu turda YAZILMAZ ve sahte veri KONMAZ
            (CLAUDE.md §3.2, kullanıcı talimatı) — yalnızca yerleşim, ayrı bir
            <section> olarak buraya (Bugün'den sonra, Finansal Durum'dan önce)
            eklenebilecek şekilde hazır bırakılıyor.
        --}}

        {{-- ==================================================== B) FİNANSAL DURUM --}}
        <section class="mt-8">
            <h2 class="text-base font-semibold text-tx">Finansal durum</h2>

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

                            <x-ui.stat
                                label="Net bakiye"
                                :tone="$totals->net->isNegative() ? 'credit' : 'debit'"
                                :value="$totals->net->format($totals->currency)"
                                :hint="$totals->net->isNegative() ? 'Net borçluyuz' : 'Net alacaklıyız'" />

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

        {{-- ============================================ C) DİKKAT GEREKENLER --}}
        <section class="mt-8">
            <h2 class="text-base font-semibold text-tx">Dikkat gerekenler</h2>

            <div class="mt-3">
                {{--
                    Panel satırları TÜMÜYLE dokunulabilirdir (CLAUDE.md §11 —
                    "Satır/card tamamı kolay dokunulabilir olsun"): `<a>` yalnızca
                    başlığı değil satırın tamamını (tutar dahil) sarar, tıpkı liste
                    ekranlarının mobil kart deseninde olduğu gibi.
                --}}
                <x-ui.panel title="En yüksek bakiyeli firmalar" :href="route('statements.index')" link-label="Bakiye listesi">
                    @forelse ($summary->topCompanies as $code => $rows)
                        <div @class(['px-4 py-3', 'border-t border-line' => ! $loop->first])>
                            <p class="pb-2 text-xs font-medium uppercase tracking-wide text-mut">{{ $code }}</p>

                            <ul>
                                @foreach ($rows as $row)
                                    <li>
                                        <a href="{{ route('companies.show', $row->companyId) }}"
                                           class="-mx-2 flex min-h-11 items-center justify-between gap-3 rounded-lg px-2 hover:bg-well">
                                            <span class="min-w-0 truncate text-sm text-tx">{{ $row->companyName }}</span>

                                            <span class="shrink-0 text-sm font-medium tabular-nums text-debit">
                                                {{ $row->balance->format($row->currency) }}
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
    @endif
@endsection
