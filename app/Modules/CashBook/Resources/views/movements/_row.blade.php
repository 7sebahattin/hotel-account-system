{{--
    Tek kasa hareketi satırı — mobilde de okunaklı olsun diye tablo değil liste.

    Yön yalnızca RENKLE anlatılmaz: tutarın önünde + / − işareti ve tür etiketi
    her zaman metin olarak vardır (CLAUDE.md §11.6, renk körlüğü).
--}}
<div @class(['px-4 py-3', 'border-t border-line' => ! $loop->first])>
    <div class="flex items-baseline justify-between gap-3">
        <span class="min-w-0 truncate text-sm text-tx">{{ $movement->label() }}</span>

        <span @class([
            'shrink-0 text-sm font-medium tabular-nums',
            'text-credit' => $movement->isIn(),
            'text-debit' => ! $movement->isIn(),
        ])>
            {{ $movement->isIn() ? '+' : '−' }}{{ $movement->amountMoney()->format($movement->currency) }}
        </span>
    </div>

    <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-mut">
        <span class="tabular-nums">{{ $movement->entry_at->format('H:i') }}</span>

        @if ($movement->is_reversal)
            <x-ui.badge tone="danger">Ters kayıt</x-ui.badge>
        @endif

        @if ($movement->counterparty)
            <span aria-hidden="true">·</span>
            <span class="truncate">{{ $movement->counterparty }}</span>
        @endif

        @if ($movement->description)
            <span aria-hidden="true">·</span>
            <span class="truncate">{{ $movement->description }}</span>
        @endif

        @if (($canReverse ?? false) && ! $movement->is_reversal)
            @can('reverse', $movement)
                <form method="POST" action="{{ route('cashbook.movements.reverse', $movement) }}" class="ml-auto">
                    @csrf
                    @method('PATCH')
                    {{--
                        Silme değil, TERS KAYIT: onay metni bunu açıkça söyler ki
                        kullanıcı "kayıt kaybolacak" sanmasın.
                    --}}
                    <button type="submit"
                            class="inline-flex min-h-11 items-center rounded px-2 text-xs text-acc-tx underline underline-offset-2 hover:text-tx"
                            onclick="return confirm('Bu hareket ters kayıtla iptal edilsin mi? Orijinal kayıt defterde kalır.')">
                        İptal et
                    </button>
                </form>
            @endcan
        @endif
    </div>
</div>
