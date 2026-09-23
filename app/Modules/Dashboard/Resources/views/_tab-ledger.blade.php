{{--
    "Son Hareketler": seçili para biriminde en yeni cari hareketler.

    "Genel bakiye", satırdaki hareketten SONRA tüm hesapların o para
    birimindeki net bakiyesidir (sistem geneli) — bir hesabın ekstre
    bakiyesi DEĞİLDİR; başlık ve not bunu açıkça söyler. Satır, hesabın
    cari ekstresine gider.
--}}
@php
    use App\Modules\Core\Support\NavIcons;
    use App\Modules\Ledger\Enums\LedgerEntryType;

    $entries = $summary->ledgerEntries;
    $currency = $summary->selectedCurrency;
    $typeTones = [
        LedgerEntryType::Stay->value => 'debit',
        LedgerEntryType::Payment->value => 'credit',
    ];
@endphp

@if ($entries === null || $entries->isEmpty())
    <x-ui.empty class="m-4 border-0 bg-transparent py-8">{{ $currency->value }} için cari hareket yok.</x-ui.empty>
@else
    <div class="hidden lg:block">
        <table class="min-w-full text-sm" data-ledger-table>
            <thead class="bg-well">
                <tr>
                    <th scope="col" class="px-3 py-2 text-left text-xs font-normal uppercase tracking-wide text-tx">Tarih</th>
                    <th scope="col" class="px-3 py-2 text-left text-xs font-normal uppercase tracking-wide text-tx">İşlem</th>
                    <th scope="col" class="px-3 py-2 text-left text-xs font-normal uppercase tracking-wide text-tx">Açıklama</th>
                    <th scope="col" class="whitespace-nowrap px-3 py-2 text-right text-xs font-normal uppercase tracking-wide text-tx">Borç ({{ $currency->symbol() }})</th>
                    <th scope="col" class="whitespace-nowrap px-3 py-2 text-right text-xs font-normal uppercase tracking-wide text-tx">Alacak ({{ $currency->symbol() }})</th>
                    <th scope="col" class="whitespace-nowrap px-3 py-2 text-right text-xs font-normal uppercase tracking-wide text-tx">Genel bakiye ({{ $currency->symbol() }})</th>
                    <th scope="col" class="w-11 px-1 py-2"><span class="sr-only">Ekstre</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($entries as $entry)
                    @php
                        $type = LedgerEntryType::fromEntry($entry);
                        $href = route('statements.show', $entry->company_id);
                        $balance = $summary->ledgerBalances[$entry->id] ?? null;
                    @endphp
                    <tr class="border-t border-line hover:bg-well">
                        <td class="whitespace-nowrap px-3 py-2 tabular-nums text-tx">{{ $entry->entry_date->format('d.m.Y') }}</td>
                        <td class="px-3 py-2"><x-ui.badge :tone="$typeTones[$type->value] ?? 'neutral'">{{ $type->label() }}</x-ui.badge></td>
                        <td class="w-full max-w-0 px-3 py-2">
                            <span class="line-clamp-2 text-tx" title="{{ $entry->description }}">{{ $entry->description }}</span>
                            <span class="block truncate text-xs text-mut">{{ $summary->companyName((int) $entry->company_id) }}</span>
                        </td>
                        <td @class(['whitespace-nowrap px-3 py-2 text-right tabular-nums', 'text-debit' => ! $entry->debitMoney()->isZero(), 'text-mut' => $entry->debitMoney()->isZero()])>
                            {{ $entry->debitMoney()->isZero() ? '—' : $entry->debitMoney()->formatNumber() }}
                        </td>
                        <td @class(['whitespace-nowrap px-3 py-2 text-right tabular-nums', 'text-credit' => ! $entry->creditMoney()->isZero(), 'text-mut' => $entry->creditMoney()->isZero()])>
                            {{ $entry->creditMoney()->isZero() ? '—' : $entry->creditMoney()->formatNumber() }}
                        </td>
                        <td class="whitespace-nowrap px-3 py-2 text-right font-medium tabular-nums text-tx">{{ $balance?->formatNumber() }}</td>
                        <td class="px-1 py-1 text-right">
                            <a href="{{ $href }}" class="inline-flex size-11 items-center justify-center rounded-lg text-mut hover:bg-card hover:text-tx"
                               aria-label="{{ $summary->companyName((int) $entry->company_id) }} cari ekstresini aç">
                                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="size-4" aria-hidden="true">
                                    <path d="{{ NavIcons::path('chevron-right') }}" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <ul class="lg:hidden">
        @foreach ($entries as $entry)
            @php
                $type = LedgerEntryType::fromEntry($entry);
                $balance = $summary->ledgerBalances[$entry->id] ?? null;
            @endphp
            <li @class(['border-t border-line' => ! $loop->first])>
                <a href="{{ route('statements.show', $entry->company_id) }}" class="block min-h-11 px-4 py-3 hover:bg-well">
                    <span class="flex items-center justify-between gap-3">
                        <span class="flex min-w-0 items-center gap-2">
                            <x-ui.badge :tone="$typeTones[$type->value] ?? 'neutral'">{{ $type->label() }}</x-ui.badge>
                            <span class="truncate text-sm text-tx">{{ $summary->companyName((int) $entry->company_id) }}</span>
                        </span>
                        <span @class(['shrink-0 text-sm font-medium tabular-nums', 'text-debit' => $entry->isDebit(), 'text-credit' => ! $entry->isDebit()])>
                            {{ $entry->isDebit() ? 'Borç' : 'Alacak' }} {{ $entry->amount()->format($currency) }}
                        </span>
                    </span>
                    <span class="mt-1 block truncate text-xs text-mut">{{ $entry->description }}</span>
                    <span class="mt-1 flex justify-between gap-3 text-xs text-mut">
                        <span class="tabular-nums">{{ $entry->entry_date->format('d.m.Y') }}</span>
                        <span>Genel bakiye <span class="tabular-nums text-tx">{{ $balance?->format($currency) }}</span></span>
                    </span>
                </a>
            </li>
        @endforeach
    </ul>

    <div class="border-t border-line px-4 py-3">
        <p class="mb-2 text-xs text-mut">Genel bakiye: hareketten sonra tüm hesapların {{ $currency->value }} net bakiyesi (pozitif = bize borçlu).</p>
        @if ($entries->hasPages())
            {{ $entries->withQueryString()->fragment('hareketler')->links() }}
        @else
            <p class="text-sm text-mut">Toplam <span class="tabular-nums">{{ $entries->total() }}</span> kayıt</p>
        @endif
    </div>
@endif
