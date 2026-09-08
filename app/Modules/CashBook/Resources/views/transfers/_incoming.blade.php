{{--
    Kullanıcıya gelen devir teklifi.

    Kabul YANLIŞLIKLA yapılamamalı (kullanıcı talimatı §21): buton ayrı bir
    onay adımı ister ve devredilen tutarlar kabul etmeden ÖNCE tam olarak
    listelenir — kullanıcı neyi kabul ettiğini görmeden onaylayamaz.
--}}
<x-ui.card class="mt-4 border-acc-tx">
    <h2 class="text-sm font-semibold text-tx">
        {{ $transfer->fromUser?->name }} kasayı size devretmek istiyor
    </h2>

    <p class="mt-1 text-xs text-mut tabular-nums">
        {{ $transfer->initiated_at->format('d.m.Y H:i') }}
        @if ($transfer->note) · {{ $transfer->note }} @endif
    </p>

    <dl class="mt-3 space-y-1 border-t border-line pt-3 text-sm">
        @forelse ($transfer->amounts as $amount)
            <div class="flex items-center justify-between gap-3">
                <dt class="text-mut">{{ $amount->currency->value }} — {{ $amount->currency->label() }}</dt>
                <dd class="font-medium tabular-nums text-tx">
                    {{ $amount->amountMoney()->format($amount->currency) }}
                </dd>
            </div>
        @empty
            <p class="text-sm text-mut">Devredilen tutar yok (boş kasa devri).</p>
        @endforelse
    </dl>

    <div class="mt-4 flex flex-wrap gap-2">
        <form method="POST" action="{{ route('cashbook.transfers.accept', $transfer) }}">
            @csrf
            @method('PATCH')
            <x-ui.button type="submit"
                         onclick="return confirm('Yukarıdaki tutarları teslim aldığınızı onaylıyor musunuz? Onaylarsanız kasa sizin sorumluluğunuza geçer.')">
                Devri kabul et
            </x-ui.button>
        </form>
    </div>
</x-ui.card>
