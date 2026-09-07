@extends('layouts.app')
@section('title', $stay->guest_name.' — konaklama')

@section('content')
    <x-ui.page-header :title="$stay->guest_name">
        <x-slot:meta>
            <p class="mt-1 text-sm text-mut">
                {{ $stay->company?->name ?? '—' }}
                @if ($stay->room_no) · Oda <span class="tabular-nums">{{ $stay->room_no }}</span> @endif
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
        </x-slot:actions>
    </x-ui.page-header>

    @if ($stay->isCancelled())
        <x-ui.alert tone="danger" class="mt-4">
            Bu konaklama iptal edilmiştir. Cari hareketi ters kaydedilmiştir ve kayıt düzenlenemez.
        </x-ui.alert>
    @endif

    <div class="mt-6 grid gap-4 lg:grid-cols-3">
        <x-ui.card title="Konaklama bilgileri" class="p-6 lg:col-span-2">
            <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-mut">Giriş</dt>
                    <dd class="mt-0.5 text-sm tabular-nums text-tx">{{ $stay->check_in_date->format('d.m.Y') }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-mut">Çıkış</dt>
                    <dd class="mt-0.5 text-sm tabular-nums text-tx">{{ $stay->check_out_date->format('d.m.Y') }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-mut">Gece</dt>
                    <dd class="mt-0.5 text-sm tabular-nums text-tx">{{ $stay->nights }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-mut">Ücretlendirme</dt>
                    <dd class="mt-0.5 text-sm text-tx">{{ $stay->pricing_mode->label() }}</dd>
                </div>
                @if ($stay->isDailyPriced())
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-mut">Günlük ücret</dt>
                        <dd class="mt-0.5 text-sm tabular-nums text-tx">
                            {{ $stay->dailyRateMoney()?->format($stay->currency) }}
                        </dd>
                    </div>
                @endif
                <div>
                    <dt class="text-xs uppercase tracking-wide text-mut">Toplam</dt>
                    <dd class="mt-0.5 text-sm tabular-nums text-tx">
                        {{ $stay->amountMoney()->format($stay->currency) }}
                        @if ($stay->isDailyPriced())
                            <span class="block text-xs text-mut">
                                {{ $stay->nights }} gece × {{ $stay->dailyRateMoney()?->format($stay->currency) }}
                            </span>
                        @endif
                    </dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-xs uppercase tracking-wide text-mut">Notlar</dt>
                    <dd class="mt-0.5 text-sm whitespace-pre-line text-tx">{{ $stay->notes ?: '—' }}</dd>
                </div>
            </dl>
        </x-ui.card>

        <x-ui.card title="Kayıt bilgisi" class="p-6">
            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-mut">Oluşturan</dt>
                    <dd class="mt-0.5 text-tx">
                        {{ $stay->creator?->name ?? '—' }}
                        <span class="text-mut">· {{ $stay->created_at->format('d.m.Y H:i') }}</span>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-mut">Son güncelleyen</dt>
                    <dd class="mt-0.5 text-tx">
                        {{ $stay->updater?->name ?? '—' }}
                        <span class="text-mut">· {{ $stay->updated_at->format('d.m.Y H:i') }}</span>
                    </dd>
                </div>
            </dl>

            @if ($stay->company)
                <div class="mt-6">
                    <x-ui.button variant="secondary" :href="route('companies.show', $stay->company)">
                        Firma kartına git
                    </x-ui.button>
                </div>
            @endif
        </x-ui.card>
    </div>
@endsection
