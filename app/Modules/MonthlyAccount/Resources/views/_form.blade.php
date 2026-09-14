@php
    $revision = $day?->currentRevision;
    $defaultItems = $revision?->items->map(fn ($entry) => [
        'method' => $entry->method->value,
        'currency' => $entry->currency->value,
        'bank_name' => $entry->bank_name,
        'amount' => $entry->amount,
        'conversion_mode' => $entry->conversion_mode instanceof \BackedEnum ? $entry->conversion_mode->value : $entry->conversion_mode,
        'exchange_rate_to_try' => $entry->exchange_rate_to_try,
        'try_equivalent' => $entry->try_equivalent,
        'note' => $entry->note,
    ])->all() ?? [['method' => 'cash', 'currency' => 'TRY']];
    $oldItems = session()->hasOldInput() ? old('items', []) : $defaultItems;
    $items = is_array($oldItems) ? array_filter($oldItems, 'is_array') : [];
    $items = array_map(static fn (array $item): array => array_map(static fn ($value): string => is_scalar($value) ? (string) $value : '', $item), $items);
@endphp
@csrf
@if ($day)
    @method('PUT')
    <input type="hidden" name="version" value="{{ old('version', $day->current_version) }}">
@endif

<x-ui.errors />

<section class="rounded-lg border border-line bg-card p-3">
    <h2 class="text-sm font-medium">Günün bilgileri</h2>
    <div class="mt-2 grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
        <x-ui.input label="Tarih" name="account_date" type="date" required :value="$date" :readonly="(bool) $day" />
        @if (! $day && $owners->isNotEmpty())
            <x-ui.select label="Hesap sahibi" name="owner_id" required>
                @foreach ($owners as $candidate)
                    <option value="{{ $candidate->id }}" @selected((string) old('owner_id', $owner->id) === (string) $candidate->id)>{{ $candidate->name }}</option>
                @endforeach
            </x-ui.select>
        @else
            <input type="hidden" name="owner_id" value="{{ $owner->id }}">
            <div><p class="text-xs text-mut">Hesap sahibi</p><p class="mt-1 flex min-h-9 items-center font-medium">{{ $owner->name }}</p></div>
        @endif
        <x-ui.input label="Günlük Liste Toplamı (TL)" name="daily_total" required :value="$revision?->daily_total ?? ''" inputmode="decimal" autocomplete="off" data-money-input data-daily-total class="monthly-daily-total tabular-nums" placeholder="0,00" hint="Liste tutarını elle girin." />
    </div>
    <div class="mt-2"><x-ui.textarea label="Günlük not" name="note" :value="$revision?->note" rows="1" maxlength="2000" placeholder="Bu güne ait genel notunuz" /></div>
</section>

<section class="mt-3">
    <div class="mb-2">
        <h2 class="text-sm font-medium">Alınan ödemeler</h2>
        <p class="mt-0.5 text-xs text-mut">Her ödeme için bir satır ekleyin. Ekstra, notuyla birlikte kaydedilen ek tahsilattır.</p>
    </div>
    <div class="space-y-2" data-monthly-items>
        @foreach ($items as $index => $item)
            @include('monthly-account::_item', ['index' => $index, 'item' => $item])
        @endforeach
    </div>
    <p class="mt-2 text-xs text-mut" data-no-items @if (count($items) > 0) hidden @endif>Henüz ödeme eklenmedi. Sadece günlük liste toplamını da kaydedebilirsiniz.</p>
    <div class="mt-2 flex justify-end">
        <x-ui.button type="button" variant="secondary" data-add-item>+ Ödeme ekle</x-ui.button>
    </div>
    <template data-monthly-item-template>@include('monthly-account::_item', ['index' => '__INDEX__', 'item' => ['method' => 'cash', 'currency' => 'TRY']])</template>
</section>

<div class="mt-3 rounded-lg border border-line bg-card p-3">
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div><p class="text-xs text-mut">Alınan ödemeler · TL karşılığı</p><p class="mt-0.5 text-lg font-semibold tabular-nums" data-monthly-total aria-live="polite">0,00 TL</p><p class="mt-0.5 text-xs text-mut">Liste toplamı, ödeme toplamına ayrıca eklenmez.</p></div>
        <div class="flex flex-wrap gap-2">
            <x-ui.button type="submit">{{ $day ? ($day->isDeleted() ? 'Yeniden etkinleştir ve kaydet' : 'Değişiklikleri kaydet') : 'Günü kaydet' }}</x-ui.button>
            <x-ui.button variant="ghost" :href="$day ? route('monthly-account.show', $day) : route('monthly-account.index', ['month' => substr($date, 0, 7), 'owner_id' => $owner->id])">Vazgeç</x-ui.button>
        </div>
    </div>
    <p class="mt-2 text-xs text-mut" data-monthly-summary-hint>Listeye göre fark, günlük liste ve ödeme tutarlarını girdikçe gösterilir.</p>
</div>
