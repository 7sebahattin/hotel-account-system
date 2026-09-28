@php
    $priceMap = $offer->prices->keyBy(fn ($price) => $price->offer_room_type_id.'-'.$price->offer_period_id);
@endphp
<div class="max-w-full overflow-x-auto rounded-lg border border-line">
    <table class="min-w-full border-collapse text-sm">
        <thead class="bg-well">
            <tr><th scope="col" class="min-w-36 border-b border-line px-5 py-4 text-left font-semibold text-tx">Oda tipi</th>
                @foreach ($offer->periods as $period)
                    <th scope="col" class="min-w-40 border-b border-line px-5 py-4 text-right font-semibold text-tx">{{ $period->label }}@if ($period->start_date && $period->end_date)<span class="mt-1 block text-xs font-normal text-mut">{{ $period->start_date->format('d.m.Y') }} – {{ $period->end_date->format('d.m.Y') }}</span>@endif</th>
                @endforeach
            </tr>
        </thead>
        <tbody class="divide-y divide-line bg-card">
            @foreach ($offer->roomTypes as $room)
                <tr class="hover:bg-acc-bg/40"><th scope="row" class="px-5 py-4 text-left font-semibold text-tx">{{ $room->name }}</th>
                    @foreach ($offer->periods as $period)
                        @php $price = $priceMap->get($room->id.'-'.$period->id); @endphp
                        <td class="px-5 py-4 text-right font-semibold tabular-nums text-tx">{{ $price ? \App\Modules\Core\Support\Money::fromDecimal($price->price)->formatNumber() . ' ' . $offer->currency : '—' }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
