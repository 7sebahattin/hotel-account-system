@php
    $priceMap = $offer->prices->keyBy(fn ($price) => $price->offer_room_type_id.'-'.$price->offer_period_id);
@endphp
<div class="overflow-x-auto rounded-xl border border-line">
    <table class="min-w-full text-sm">
        <thead class="bg-bg">
            <tr><th class="min-w-36 px-5 py-4 text-left font-semibold">Oda tipi</th>
                @foreach ($offer->periods as $period)
                    <th class="min-w-40 px-5 py-4 text-right font-semibold">{{ $period->label }}@if ($period->start_date && $period->end_date)<span class="mt-1 block text-xs font-normal text-tx-muted">{{ $period->start_date->format('d.m.Y') }} – {{ $period->end_date->format('d.m.Y') }}</span>@endif</th>
                @endforeach
            </tr>
        </thead>
        <tbody class="divide-y divide-line">
            @foreach ($offer->roomTypes as $room)
                <tr><th class="px-5 py-4 text-left font-semibold">{{ $room->name }}</th>
                    @foreach ($offer->periods as $period)
                        @php $price = $priceMap->get($room->id.'-'.$period->id); @endphp
                        <td class="px-5 py-4 text-right tabular-nums">{{ $price ? \App\Modules\Core\Support\Money::fromDecimal($price->price)->formatNumber() . ' ' . $offer->currency : '—' }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
