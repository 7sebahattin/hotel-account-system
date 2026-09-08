{{--
    Kasada beklenen fiziksel para — PARA BİRİMİ BAZINDA.

    Para birimleri asla tek toplamda birleştirilmez (CLAUDE.md §6/L5); bu yüzden
    burada "genel toplam" satırı YOKTUR ve hiçbir çevrim yapılmaz. Yalnızca
    hareket görmüş para birimleri gösterilir.

    Beklenen: array<string, Money> — $summary->expected()
--}}
@php
    $expected = $summary->expected();
@endphp

<section class="mt-4">
    <h2 class="text-sm font-semibold text-tx">{{ $title ?? 'Kasada beklenen' }}</h2>

    @if ($expected === [])
        <x-ui.empty class="mt-2">Bu vardiyada henüz para hareketi yok.</x-ui.empty>
    @else
        <div class="mt-2 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($summary->totals as $code => $totals)
                <x-ui.card class="p-0">
                    <div class="flex items-center justify-between gap-2 border-b border-line px-4 py-2.5">
                        <h3 class="text-sm font-medium text-tx">{{ $totals->currency->label() }}</h3>
                        <x-ui.badge tone="accent">{{ $code }}</x-ui.badge>
                    </div>

                    <div class="px-4 py-3">
                        <p class="text-lg font-semibold tabular-nums text-tx">
                            {{ $totals->expected()->format($totals->currency) }}
                        </p>
                        <p class="mt-0.5 text-xs text-mut">Beklenen fiziksel kasa</p>

                        <dl class="mt-3 space-y-1 border-t border-line pt-2 text-xs">
                            @foreach (\App\Modules\CashBook\Enums\CashMovementType::cases() as $type)
                                @php $net = $totals->forType($type); @endphp

                                @unless ($net->isZero())
                                    <div class="flex items-center justify-between gap-3">
                                        <dt class="text-mut">{{ $type->label() }}</dt>
                                        <dd @class([
                                            'tabular-nums',
                                            'text-credit' => $net->isPositive(),
                                            'text-debit' => $net->isNegative(),
                                        ])>{{ $net->format($totals->currency) }}</dd>
                                    </div>
                                @endunless
                            @endforeach
                        </dl>
                    </div>
                </x-ui.card>
            @endforeach
        </div>
    @endif
</section>
