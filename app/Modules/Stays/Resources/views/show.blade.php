@extends('layouts.app')
@section('title', $stay->guest_name.' — konaklama')

@section('content')
    <x-ui.page-header :title="$stay->guest_name">
        <x-slot:meta>
            <p class="mt-1 text-sm text-mut">
                {{ $stay->company?->name ?? '—' }}
                · {{ $stay->company?->account_type->label() }} · #{{ $stay->company_id }}
                @if ($stay->roomLabel()) · Oda <span class="tabular-nums">{{ $stay->roomLabel() }}</span> @endif
            </p>
            <div class="mt-2">@include('stays::_status', ['stay' => $stay])</div>
        </x-slot:meta>

        <x-slot:actions>
            @can('update', $stay)
                <x-ui.button :href="route('stays.edit', $stay)">Düzenle</x-ui.button>
            @endcan

            @can('cancel', $stay)
                <form method="POST" action="{{ route('stays.cancel', $stay) }}"
                      onsubmit="return confirm('Bu konaklama iptal edilsin mi? Cari hareketi ters kaydedilecek ve bu işlem geri alınamayacak.')">
                    @csrf @method('PATCH')
                    <x-ui.button type="submit" variant="danger">İptal et</x-ui.button>
                </form>
            @endcan

            {{--
                Kalıcı silme — yalnızca Admin, yalnızca ZATEN iptal edilmiş bir
                kayıtta (StayPolicy::forceDelete). Ledger bu işlemden etkilenmez:
                iptal sırasında yazılan orijinal + ters kayıt çifti deftere
                kalıcı olarak yazılı kalır (CLAUDE.md §6/L1), yalnızca artık
                gereksiz olan bu Stay satırı veritabanından kaldırılır.
            --}}
            @can('forceDelete', $stay)
                <form method="POST" action="{{ route('stays.destroy', $stay) }}"
                      onsubmit="return confirm('Bu konaklama kaydı veritabanından KALICI olarak silinecek. Bu işlem GERİ ALINAMAZ. Emin misiniz?')">
                    @csrf @method('DELETE')
                    <x-ui.button type="submit" variant="danger">Kalıcı olarak sil</x-ui.button>
                </form>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card title="Ödeme durumu" class="mt-4 p-5">
        @include('stays::_payment-status')
        @include('stays::_invoice-toggle', ['interactive' => true])
        @if ($paymentSummary['legacy'])
            <p class="mt-2 text-xs text-mut">Bu hesapta geçmiş tahsilatlar henüz konaklamalarla eşleştirilmedi. Kesin cari bakiye için ekstreyi kullanın.</p>
        @endif
    </x-ui.card>

    @if ($stay->isCancelled())
        <x-ui.alert tone="danger" class="mt-4">
            Bu konaklama iptal edilmiştir. Cari hareketi ters kaydedilmiştir ve kayıt düzenlenemez.
        </x-ui.alert>
    @endif

    <div class="mt-6 grid gap-4 lg:hidden">
        <x-ui.card title="Konaklama bilgileri" class="p-6 lg:col-span-2">
            @include('stays::_stay-information')
        </x-ui.card>

        <x-ui.card title="Kayıt bilgisi" class="p-6">
            @include('stays::_audit-information')

            @if ($stay->company)
                <div class="mt-6">
                    <x-ui.button variant="secondary" :href="route('companies.show', $stay->company)">
                        Cari hesap kartına git
                    </x-ui.button>
                </div>
            @endif
        </x-ui.card>
    </div>

    <div class="mt-6 hidden gap-4 lg:grid lg:grid-cols-3">
        <x-ui.card title="Konaklama bilgileri" class="p-4 lg:col-span-2">
            @include('stays::_stay-information', ['compact' => true])
        </x-ui.card>

        <x-ui.card title="Kayıt bilgisi" class="p-4">
            @include('stays::_audit-information', ['compact' => true])

            @if ($stay->company)
                <div class="mt-4">
                    <x-ui.button variant="secondary" :href="route('companies.show', $stay->company)">
                        Cari hesap kartına git
                    </x-ui.button>
                </div>
            @endif
        </x-ui.card>
    </div>
@endsection
