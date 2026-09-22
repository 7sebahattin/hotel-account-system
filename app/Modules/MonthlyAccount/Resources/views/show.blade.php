@extends('layouts.app')
@section('title', 'Günlük Hesap')

@section('content')
    @php
        $revision = $day->currentRevision;
        $summary = $revision->summary();
        $try = \App\Modules\Core\Enums\Currency::TRY;
        $methodLabels = ['cash' => 'Nakit', 'credit_card' => 'Kredi kartı', 'link' => 'Link ödemesi', 'bank_transfer' => 'Havale', 'extra' => 'Ekstra tahsilat'];
    @endphp
    <div class="monthly-account">
        <x-ui.page-header :title="$day->account_date->translatedFormat('d F Y, l')" subtitle="Aylık Hesap">
            <x-slot:actions>
                <x-ui.button variant="secondary" :href="route('monthly-account.index', ['month' => $day->account_date->format('Y-m'), 'owner_id' => $day->owner_id])">Aylık liste</x-ui.button>
                @can('update', $day)
                    <x-ui.button :href="route('monthly-account.edit', $day)">{{ $day->isDeleted() ? 'Yeniden etkinleştir' : 'Düzenle' }}</x-ui.button>
                @endcan
            </x-slot:actions>
        </x-ui.page-header>

        @if ($day->isDeleted())
            <x-ui.alert tone="info" class="mt-4">Bu gün silinmiş olarak saklanıyor ve ay toplamlarına dahil edilmiyor. Önceki tutarlar ve kayıt geçmişi aşağıdadır.</x-ui.alert>
        @endif

        <div class="mt-5 grid gap-5 rounded-xl border border-line bg-card p-5 sm:grid-cols-2">
            <x-ui.stat label="Günlük Liste Toplamı" :value="$summary['daily_total']->format($try)" hint="Elle girilen liste tutarı" />
            <x-ui.stat label="Alınan ödemeler · TL karşılığı" :value="$summary['receipt_total']->format($try)" hint="Liste tutarı bu toplama ayrıca eklenmez" />
            @if ($revision->note)
                <div class="border-t border-line pt-4 sm:col-span-2"><h2 class="text-sm font-medium">Günlük not</h2><p class="mt-1 whitespace-pre-wrap break-words text-sm text-mut">{{ $revision->note }}</p></div>
            @endif
        </div>

        @include('monthly-account::_banks', ['banks' => $summary['banks']])

        <h2 class="mt-6 text-base font-medium">Ödeme ayrıntıları <span class="text-sm font-normal text-mut">({{ $revision->items->count() }})</span></h2>
        <div class="mt-3 grid gap-3 lg:grid-cols-2">
            @forelse ($revision->items as $item)
                <article class="min-w-0 rounded-xl border border-line bg-card p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0"><h3 class="text-sm font-medium">{{ $methodLabels[$item->method->value] }}</h3><p class="mt-1 break-words text-sm text-mut">{{ $item->bank_name ? $item->bank_name.' · ' : '' }}{{ $item->currency->value === 'TRY' ? 'TL' : $item->currency->value }} hesabı</p></div>
                        <div class="text-right">
                            <p class="text-base font-semibold tabular-nums">{{ \App\Modules\Core\Support\Money::fromDecimal($item->amount)->format($item->currency) }}</p>
                            @if ($item->currency !== $try)
                                <x-ui.fx-note :rate="$item->exchange_rate_to_try" :equivalent="\App\Modules\Core\Support\Money::fromDecimal($item->try_equivalent)" />
                            @endif
                        </div>
                    </div>
                    @if ($item->note)<p class="mt-3 whitespace-pre-wrap break-words border-t border-line pt-3 text-sm text-mut">{{ $item->note }}</p>@endif
                </article>
            @empty
                <p class="rounded-xl border border-line bg-card p-5 text-sm text-mut lg:col-span-2">Bu gün için ödeme satırı girilmedi.</p>
            @endforelse
        </div>

        <details class="mt-5 rounded-xl border border-line bg-card p-4">
            <summary class="min-h-11 cursor-pointer py-3 text-sm font-medium">Kayıt geçmişi</summary>
            <ol class="mt-2 space-y-3 border-t border-line pt-3">
                @foreach ($day->revisions as $history)
                    <li class="flex flex-wrap items-baseline justify-between gap-2 text-sm">
                        <span>{{ $history->creator?->name ?? 'Kullanıcı' }} · {{ $history->status->value === 'deleted' ? 'Silindi' : ($history->version === 1 ? 'Oluşturuldu' : 'Düzenlendi') }} <span class="text-xs text-mut">(Sürüm {{ $history->version }})</span></span>
                        <time class="text-xs tabular-nums text-mut" datetime="{{ $history->created_at->toIso8601String() }}">{{ $history->created_at->format('d.m.Y H:i') }}</time>
                    </li>
                @endforeach
            </ol>
        </details>

        @can('delete', $day)
            <details class="mt-5 rounded-xl border border-line bg-card p-4">
                <summary class="min-h-11 cursor-pointer py-3 text-sm font-medium text-danger">Günü sil</summary>
                <p class="mt-2 text-sm text-mut">{{ $day->account_date->format('d.m.Y') }} tarihli kayıt aylık toplamdan çıkarılır. Kayıt geçmişi saklanır.</p>
                <form method="POST" action="{{ route('monthly-account.destroy', $day) }}" class="mt-3">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="version" value="{{ $day->current_version }}">
                    <x-ui.button variant="danger">Onayla ve günü sil</x-ui.button>
                </form>
            </details>
        @endcan
    </div>
@endsection
