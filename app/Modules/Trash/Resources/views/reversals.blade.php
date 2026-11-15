@extends('layouts.app')
@section('title', 'Ters Kayıt Temizliği')

@section('content')
    <x-ui.page-header title="Ters Kayıt Temizliği"
                      :subtitle="$orphanCount.' çiftin kaynak kaydı yok · toplam '.$totalCount.' ters kayıt çifti'">
        <x-slot:actions>
            <x-ui.button :href="route('trash.index')" variant="secondary">Çöp Kutusu</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.alert tone="info" class="mt-3">
        Geçici yönetim ekranı. Her satır bir <strong>çifttir</strong>: orijinal hareket ve onu sıfırlayan [İPTAL] satırı.
        Silince ikisi birlikte veritabanından <strong>kalıcı</strong> olarak kalkar ve ekstrede görünmez; çift net sıfır
        olduğu için hiçbir cari bakiye değişmez. Bu işlem geri alınamaz.
    </x-ui.alert>

    <div class="mt-4 flex flex-wrap items-center gap-2">
        @foreach ([['', 'Kaynağı silinmiş olanlar', $orphanCount], ['tumu', 'Tüm ters kayıtlar', $totalCount]] as [$value, $label, $count])
            <a href="{{ route('trash.reversals.index', array_filter(['goster' => $value, 'company_id' => $companyId ?: null])) }}"
               @if ($showAll === ($value === 'tumu')) aria-current="page" @endif
               @class([
                   'inline-flex min-h-11 items-center gap-2 rounded-lg border px-4 text-sm',
                   'border-acc bg-acc-bg font-semibold text-tx' => $showAll === ($value === 'tumu'),
                   'border-line bg-card text-mut hover:bg-well' => $showAll !== ($value === 'tumu'),
               ])>
                {{ $label }} <x-ui.badge>{{ $count }}</x-ui.badge>
            </a>
        @endforeach

        <form method="GET" action="{{ route('trash.reversals.index') }}" class="ml-auto flex items-center gap-2" data-allow-resubmit>
            @if ($showAll) <input type="hidden" name="goster" value="tumu"> @endif
            <label for="company_id" class="sr-only">Cari hesap</label>
            <select id="company_id" name="company_id" onchange="this.form.submit()"
                    class="min-h-11 rounded-lg border border-field bg-card px-3 text-base text-tx">
                <option value="">Tüm cari hesaplar</option>
                @foreach ($companyOptions as $id => $name)
                    <option value="{{ $id }}" @selected($companyId === $id)>{{ $name }}</option>
                @endforeach
            </select>
            <noscript><x-ui.button type="submit" variant="secondary">Süz</x-ui.button></noscript>
        </form>
    </div>

    <form id="toplu-sil" method="POST" action="{{ route('trash.reversals.destroy') }}"
          onsubmit="return confirm('Seçili ters kayıt çiftleri veritabanından KALICI olarak silinecek. Bu işlem GERİ ALINAMAZ. Emin misiniz?')">
        @csrf @method('DELETE')
    </form>

    <x-ui.card class="mt-4 overflow-hidden p-0">
        @if ($pairs->isNotEmpty())
            <div class="flex flex-wrap items-center gap-3 border-b border-line bg-well px-4 py-2">
                <label class="inline-flex min-h-11 items-center gap-2 text-sm text-tx">
                    <input type="checkbox" class="size-5"
                           onchange="document.querySelectorAll('[data-pair-check]').forEach(c => c.checked = this.checked)">
                    Tümünü seç ({{ $pairs->count() }})
                </label>
                <x-ui.button type="submit" form="toplu-sil" variant="danger" class="ml-auto">Seçilenleri kalıcı sil</x-ui.button>
            </div>
        @endif

        @forelse ($pairs as $pair)
            @php
                $original = $pair['original'];
                $reversal = $pair['reversal'];
            @endphp
            <div @class(['flex flex-wrap items-start gap-3 px-4 py-3', 'border-t border-line' => ! $loop->first])>
                <label class="inline-flex min-h-11 items-center">
                    <input type="checkbox" name="ids[]" value="{{ $reversal->id }}" form="toplu-sil" data-pair-check class="size-5">
                    <span class="sr-only">#{{ $original->id }} çiftini seç</span>
                </label>

                <div class="min-w-0 flex-1">
                    <p class="font-medium text-tx">
                        <a href="{{ route('statements.show', $original->company_id) }}" class="underline underline-offset-2">{{ $names[$original->company_id] ?? 'Cari #'.$original->company_id }}</a>
                        @if ($pair['source_exists'])
                            <x-ui.badge>Kaynak kayıt duruyor</x-ui.badge>
                        @else
                            <x-ui.badge tone="danger">Kaynak kayıt yok</x-ui.badge>
                        @endif
                    </p>
                    <p class="text-sm text-tx">{{ $original->description }}</p>
                    <p class="text-xs text-mut">
                        <span class="tabular-nums">{{ $original->entry_date->format('d.m.Y') }}</span>
                        · Orijinal #{{ $original->id }} ({{ $original->isDebit() ? 'Borç' : 'Alacak' }})
                        · Ters kayıt #{{ $reversal->id }} ({{ $reversal->isDebit() ? 'Borç' : 'Alacak' }},
                        <span class="tabular-nums">{{ $reversal->created_at->format('d.m.Y H:i') }}</span>)
                    </p>
                </div>

                <p class="min-w-28 text-right font-medium tabular-nums text-tx">{{ $original->amount()->format($original->currency) }}</p>

                <form method="POST" action="{{ route('trash.reversals.destroy') }}"
                      onsubmit="return confirm('Bu çift (orijinal + ters kayıt) veritabanından KALICI olarak silinecek. Bu işlem GERİ ALINAMAZ. Emin misiniz?')">
                    @csrf @method('DELETE')
                    <input type="hidden" name="ids[]" value="{{ $reversal->id }}">
                    <x-ui.button type="submit" variant="danger" class="px-3">Kalıcı sil</x-ui.button>
                </form>
            </div>
        @empty
            <p class="px-4 py-10 text-center text-sm text-mut">Bu görünümde ters kayıt çifti yok.</p>
        @endforelse
    </x-ui.card>
@endsection
