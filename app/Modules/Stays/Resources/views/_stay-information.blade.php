<dl @class([
    'grid gap-x-6 gap-y-4 sm:grid-cols-2',
    'lg:grid-cols-3' => $compact ?? false,
])>
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
            <x-ui.fx-note :rate="$stay->exchange_rate_to_try" :equivalent="$stay->tryEquivalentMoney()" />
            @if ($stay->isMissingExchangeRate())
                <span class="mt-0.5 block text-xs text-mut">Kur bilgisi eksik (geçmiş kayıt).</span>
            @endif
        </dd>
    </div>
    <div @class(['sm:col-span-2', 'lg:col-span-3' => $compact ?? false])>
        <dt class="text-xs uppercase tracking-wide text-mut">Notlar</dt>
        <dd class="mt-0.5 text-sm whitespace-pre-line text-tx">{{ $stay->notes ?: '—' }}</dd>
    </div>
</dl>
