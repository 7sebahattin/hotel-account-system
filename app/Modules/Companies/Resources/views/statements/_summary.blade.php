{{-- Para birimi bazında özet. Farklı para birimleri ASLA tek toplamda birleşmez. --}}
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    @foreach ($statement->summaries as $summary)
        <x-ui.card class="p-5">
            <div class="flex items-baseline justify-between gap-3">
                <h3 class="text-sm font-medium text-tx">{{ $summary->currency->value }}</h3>
                <span class="text-xs text-mut">{{ $summary->currency->label() }}</span>
            </div>

            @unless ($criteria->isBalanceOnly())
            <dl class="mt-3 space-y-1.5 text-sm">
                @if ($summary->hasCarryForward())
                    <div class="flex justify-between gap-3">
                        <dt class="text-mut">Devreden bakiye</dt>
                        <dd class="tabular-nums text-mut">{{ $summary->carryForward->format($summary->currency) }}</dd>
                    </div>
                @endif
                <div class="flex justify-between gap-3">
                    <dt class="text-mut">Toplam borç</dt>
                    <dd class="font-medium tabular-nums text-debit">{{ $summary->totalDebit->format($summary->currency) }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-mut">Toplam alacak</dt>
                    <dd class="font-medium tabular-nums text-credit">{{ $summary->totalCredit->format($summary->currency) }}</dd>
                </div>
            </dl>
            @endunless

            @php $closingFx = $fx['closing'][$summary->currency->value] ?? null; @endphp
            <div @class(['mt-3', 'border-t border-line pt-3' => ! $criteria->isBalanceOnly()])>
                <div class="flex items-baseline justify-between gap-3">
                    <span class="text-sm text-mut">{{ $criteria->isBalanceOnly() ? 'Cari bakiye' : 'Bakiye' }}</span>
                    <span class="text-right">
                        <span @class([
                            'block text-base tabular-nums',
                            'text-debit' => $summary->balanceTone() === 'danger',
                            'text-credit' => $summary->balanceTone() === 'success',
                            'text-tx' => $summary->balanceTone() === 'neutral',
                        ])>{{ $summary->closing->format($summary->currency) }}</span>
                        @if ($closingFx)
                            <x-ui.fx-note :equivalent="$closingFx['value']" class="text-right" />
                            @if (! $closingFx['complete'])
                                <span class="block text-xs text-mut">TL karşılığı eksik veri nedeniyle tam değil</span>
                            @endif
                        @endif
                    </span>
                </div>
                {{-- Anlam yalnızca renge bağlanmaz: metin etiketi her zaman yazılır. --}}
                <p class="mt-1 text-xs text-mut">{{ $summary->balanceLabel($company->account_type->value === 'individual' ? 'Kişi' : 'Firma') }}</p>
            </div>
        </x-ui.card>
    @endforeach
</div>
