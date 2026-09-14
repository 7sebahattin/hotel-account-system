@if ($banks !== [])
    <details class="mt-4 rounded-xl border border-line bg-card p-4">
        <summary class="min-h-11 cursor-pointer py-3 text-sm font-medium">Banka ve ödeme kuruluşu toplamları</summary>
        <div class="mt-2 grid gap-3 border-t border-line pt-3 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($banks as $method => $methodBanks)
                @foreach ($methodBanks as $bankName => $amounts)
                    <div class="flex min-w-0 flex-wrap justify-between gap-3 rounded-lg border border-line p-3">
                        <div class="min-w-0"><p class="break-words text-sm font-medium">{{ $bankName }}</p><p class="mt-1 text-xs text-mut">{{ $methodLabels[$method] }}</p></div>
                        <div class="text-right text-sm">@include('monthly-account::_amounts', ['amounts' => $amounts])</div>
                    </div>
                @endforeach
            @endforeach
        </div>
    </details>
@endif
