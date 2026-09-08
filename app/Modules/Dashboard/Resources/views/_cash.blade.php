{{--
    Panodaki "Kasada Şu An" kartı.

    Veri CashBookService'ten hazır gelir; burada hiçbir hesap yapılmaz
    (CLAUDE.md §13). Para birimleri ayrı ayrı gösterilir ve ASLA tek toplamda
    birleştirilmez (§6/L5).
--}}
<section class="mt-8">
    <h2 class="text-base font-semibold text-tx">Kasada şu an</h2>

    <x-ui.panel title="Aktif vardiya" class="mt-3"
                :href="route('cashbook.index')" link-label="Kasa defteri">
        <div class="px-4 py-3">
            <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                <span class="font-medium text-tx">{{ $cash->session->openedBy?->name }}</span>
                <span aria-hidden="true" class="text-mut">·</span>
                <span class="tabular-nums text-mut">
                    {{ $cash->session->opened_at->format('d.m.Y H:i') }}'te devraldı
                </span>
                <span aria-hidden="true" class="text-mut">·</span>
                <span class="tabular-nums text-mut">{{ $cash->movementCount }} hareket</span>
            </div>

            @if ($cash->hasExpectedCash())
                <dl class="mt-3 space-y-1 border-t border-line pt-3 text-sm">
                    @foreach ($cash->expected as $code => $money)
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-mut">{{ $code }}</dt>
                            <dd class="font-medium tabular-nums text-tx">
                                {{ $money->format(\App\Modules\Core\Enums\Currency::from($code)) }}
                            </dd>
                        </div>
                    @endforeach
                </dl>

                <p class="mt-2 text-xs text-mut">
                    Beklenen fiziksel kasa. Para birimleri ayrı hesaplanır ve birbirine çevrilmez.
                </p>
            @else
                <p class="mt-3 border-t border-line pt-3 text-sm text-mut">
                    Bu vardiyada henüz hareket yok.
                </p>
            @endif

            @if ($cash->lastTransfer)
                <p class="mt-3 border-t border-line pt-3 text-xs text-mut">
                    Son devir:
                    {{ $cash->lastTransfer->fromUser?->name }} → {{ $cash->lastTransfer->toUser?->name }}
                    ·
                    <span class="tabular-nums">{{ $cash->lastTransfer->responded_at?->format('d.m.Y H:i') }}</span>
                </p>
            @endif
        </div>
    </x-ui.panel>
</section>
