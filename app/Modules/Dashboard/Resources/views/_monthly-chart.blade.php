{{--
    Son N ay cari hareket grafiği — tek para birimi, tek eksen.

    JS ve grafik paketi YOKTUR (kullanıcı kararı, 23 Eylül 2026): sütunlar
    HTML kutularıdır, net bakiye çizgisi `preserveAspectRatio="none"` bir
    SVG'dir (kalınlığı `vector-effect` ile sabit kalır), işaretçiler ve
    etiketler HTML'dir — dar ekranda yazılar küçülmez. Konumlar
    `MonthlyChart` tarafından tamsayı aritmetiğiyle hesaplanır.

    Katmanlar: sütunlar < çizgi/işaretçiler < fareyle/klavyeyle açılan
    ipucu kolonları. Renk tek başına bilgi taşımaz: gösterge ve tablo
    görünümü her zaman vardır (dataviz: kontrastı 3:1 altı seri → tablo).
--}}
@php
    /** @var \App\Modules\Dashboard\Support\MonthlyChart $chart */
    $count = count($chart->months);
    $currency = $chart->currency;
    $columns = 'grid-template-columns: repeat('.$count.', minmax(0, 1fr))';
@endphp

<div data-monthly-chart>
    <div class="flex gap-2">
        {{-- Y ekseni etiketleri --}}
        <div class="relative h-52 w-14 shrink-0 sm:w-16" aria-hidden="true">
            @foreach ($chart->tickValues as $tick)
                <span class="absolute right-0 translate-y-1/2 text-[11px] leading-none tabular-nums text-mut"
                      style="bottom: {{ $chart->position($tick) }}">{{ $chart->tickLabel($tick) }}</span>
            @endforeach
        </div>

        <div class="relative h-52 min-w-0 flex-1">
            {{-- Yatay kılavuz çizgileri; sıfır çizgisi daha belirgin. --}}
            @foreach ($chart->tickValues as $tick)
                <span @class(['absolute inset-x-0 border-t', 'border-field' => $tick === 0, 'border-line' => $tick !== 0])
                      style="bottom: {{ $chart->position($tick) }}" aria-hidden="true"></span>
            @endforeach

            {{-- Sütunlar: sıfır çizgisinden yukarı büyür, aralarında 2px boşluk. --}}
            <div class="absolute inset-x-0 top-0 grid" style="{{ $columns }}; bottom: {{ $chart->zeroPosition() }}" aria-hidden="true">
                @foreach ($chart->months as $month)
                    <div class="flex h-full items-end justify-center gap-0.5">
                        <span class="block w-[30%] max-w-7 rounded-t-[4px] bg-chart-stay" style="height: {{ $chart->barHeight($month->stays) }}"></span>
                        <span class="block w-[30%] max-w-7 rounded-t-[4px] bg-chart-payment" style="height: {{ $chart->barHeight($month->payments) }}"></span>
                    </div>
                @endforeach
            </div>

            {{-- Net bakiye çizgisi + işaretçiler --}}
            <svg class="pointer-events-none absolute inset-0 size-full overflow-visible" viewBox="0 0 {{ $count * 100 }} 1000"
                 preserveAspectRatio="none" aria-hidden="true">
                <polyline points="{{ $chart->linePoints() }}" fill="none" class="stroke-chart-net"
                          stroke-width="2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke"/>
            </svg>
            @foreach ($chart->months as $index => $month)
                <span class="pointer-events-none absolute size-2.5 -translate-x-1/2 translate-y-1/2 rounded-full bg-chart-net ring-2 ring-card"
                      style="left: {{ $chart->center($index) }}; bottom: {{ $chart->position($month->net) }}" aria-hidden="true"></span>
            @endforeach

            {{-- İpucu kolonları: ay kolonunun tamamı hedef alan, fare VE klavye. --}}
            <div class="absolute inset-0 grid" style="{{ $columns }}">
                @foreach ($chart->months as $index => $month)
                    <div class="group relative rounded-md hover:bg-tx/5 focus-visible:bg-tx/5 focus-visible:-outline-offset-2"
                         tabindex="0"
                         aria-label="{{ $month->label }}: konaklama {{ $month->stays->format($currency) }}, tahsilat {{ $month->payments->format($currency) }}, ay sonu net bakiye {{ $month->net->format($currency) }}">
                        <div @class([
                            'pointer-events-none absolute top-1 z-10 hidden w-44 rounded-lg border border-line bg-card p-3 text-xs group-hover:block group-focus-visible:block',
                            'left-0' => 3 * $index < $count,
                            'left-1/2 -translate-x-1/2' => 3 * $index >= $count && 3 * $index < 2 * $count,
                            'right-0' => 3 * $index >= 2 * $count,
                        ]) aria-hidden="true">
                            <p class="font-semibold text-tx">{{ $month->label }}</p>
                            <dl class="mt-2 space-y-1">
                                <div class="flex items-center justify-between gap-2">
                                    <dt class="flex items-center gap-1.5 text-tx"><span class="size-2.5 rounded-sm bg-chart-stay"></span>Konaklama</dt>
                                    <dd class="font-medium tabular-nums text-tx">{{ $month->stays->format($currency) }}</dd>
                                </div>
                                <div class="flex items-center justify-between gap-2">
                                    <dt class="flex items-center gap-1.5 text-tx"><span class="size-2.5 rounded-sm bg-chart-payment"></span>Tahsilat</dt>
                                    <dd class="font-medium tabular-nums text-tx">{{ $month->payments->format($currency) }}</dd>
                                </div>
                                <div class="flex items-center justify-between gap-2">
                                    <dt class="flex items-center gap-1.5 text-tx"><span class="size-2.5 rounded-full bg-chart-net"></span>Net bakiye</dt>
                                    <dd class="font-medium tabular-nums text-tx">{{ $month->net->format($currency) }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- X ekseni: ay + yıl iki satır — 12 ayda da sığar. --}}
    <div class="mt-2 flex gap-2" aria-hidden="true">
        <div class="w-14 shrink-0 sm:w-16"></div>
        <div class="grid min-w-0 flex-1" style="{{ $columns }}">
            @foreach ($chart->months as $month)
                <span class="text-center text-[11px] leading-tight text-mut">
                    {{ \Illuminate\Support\Str::before($month->label, ' ') }}<span class="block"> {{ \Illuminate\Support\Str::after($month->label, ' ') }}</span>
                </span>
            @endforeach
        </div>
    </div>

    {{-- Gösterge: her zaman görünür, renk tek başına anlam taşımaz. --}}
    <ul class="mt-4 flex flex-wrap items-center justify-center gap-x-5 gap-y-2 text-xs text-tx">
        <li class="flex items-center gap-1.5"><span class="size-3 rounded-sm bg-chart-stay" aria-hidden="true"></span>Konaklama</li>
        <li class="flex items-center gap-1.5"><span class="size-3 rounded-sm bg-chart-payment" aria-hidden="true"></span>Tahsilat</li>
        <li class="flex items-center gap-1.5">
            <span class="relative flex h-3 w-5 items-center" aria-hidden="true">
                <span class="h-0.5 w-full rounded bg-chart-net"></span>
                <span class="absolute left-1/2 size-2 -translate-x-1/2 rounded-full bg-chart-net"></span>
            </span>
            Net bakiye (ay sonu)
        </li>
    </ul>

    <details class="mt-3 text-sm">
        <summary class="inline-flex min-h-11 cursor-pointer items-center text-acc-tx underline underline-offset-2">Tablo olarak göster</summary>

        <div class="mt-2 overflow-x-auto">
            <table class="min-w-full text-xs sm:text-sm">
                <caption class="sr-only">Son {{ $count }} ay — {{ $currency->value }}</caption>
                <thead>
                    <tr class="border-b border-line">
                        <th scope="col" class="px-2 py-2 text-left text-xs font-normal uppercase tracking-wide text-mut">Ay</th>
                        <th scope="col" class="px-2 py-2 text-right text-xs font-normal uppercase tracking-wide text-mut">Konaklama</th>
                        <th scope="col" class="px-2 py-2 text-right text-xs font-normal uppercase tracking-wide text-mut">Tahsilat</th>
                        <th scope="col" class="px-2 py-2 text-right text-xs font-normal uppercase tracking-wide text-mut">Net bakiye</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($chart->months as $month)
                        <tr class="border-b border-line last:border-0">
                            <th scope="row" class="px-2 py-2 text-left font-normal text-tx">{{ $month->label }}</th>
                            <td class="px-2 py-2 text-right tabular-nums text-tx">{{ $month->stays->format($currency) }}</td>
                            <td class="px-2 py-2 text-right tabular-nums text-tx">{{ $month->payments->format($currency) }}</td>
                            <td class="px-2 py-2 text-right tabular-nums text-tx">{{ $month->net->format($currency) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </details>
</div>
