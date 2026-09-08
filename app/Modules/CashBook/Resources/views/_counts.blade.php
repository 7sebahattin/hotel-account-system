{{--
    Kapanış sayımı: beklenen / sayılan / fark — para birimi bazında.

    Fark yalnızca renkle değil, METİN etiketiyle de anlatılır (CLAUDE.md §11.6):
    renk körü bir kullanıcı da eksik/fazla ayrımını görebilmelidir.
--}}
<div class="overflow-x-auto">
    <table class="min-w-full text-sm">
        <thead class="border-b border-line">
            <tr>
                <th scope="col" class="px-4 py-2 text-left text-xs font-normal uppercase tracking-wide text-mut">Para birimi</th>
                <th scope="col" class="px-4 py-2 text-right text-xs font-normal uppercase tracking-wide text-mut">Beklenen</th>
                <th scope="col" class="px-4 py-2 text-right text-xs font-normal uppercase tracking-wide text-mut">Sayılan</th>
                <th scope="col" class="px-4 py-2 text-right text-xs font-normal uppercase tracking-wide text-mut">Fark</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($counts as $count)
                @php $difference = $count->differenceMoney(); @endphp

                <tr class="border-b border-line last:border-0">
                    <td class="px-4 py-2.5 text-tx">{{ $count->currency->value }}</td>
                    <td class="px-4 py-2.5 text-right tabular-nums text-mut">
                        {{ $count->expectedMoney()->format($count->currency) }}
                    </td>
                    <td class="px-4 py-2.5 text-right tabular-nums text-tx">
                        {{ $count->countedMoney()->format($count->currency) }}
                    </td>
                    <td class="px-4 py-2.5 text-right">
                        @if ($difference->isZero())
                            <span class="tabular-nums text-mut">{{ $difference->format($count->currency) }}</span>
                        @else
                            <span @class([
                                'tabular-nums font-medium',
                                'text-debit' => $difference->isNegative(),
                                'text-credit' => $difference->isPositive(),
                            ])>{{ $difference->format($count->currency) }}</span>
                            <span class="ml-1 text-xs text-mut">
                                {{ $difference->isNegative() ? 'eksik' : 'fazla' }}
                            </span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
