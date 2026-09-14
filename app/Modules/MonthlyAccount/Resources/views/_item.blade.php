@php
    $prefix = 'items['.$index.']';
    $fieldId = 'monthly-item-'.$index;
    $selectedMethod = $item['method'] ?? 'cash';
    $selectedCurrency = $item['currency'] ?? 'TRY';
    $mode = $item['conversion_mode'] ?? 'rate';
    $foreign = $selectedCurrency !== 'TRY';
    $needsBank = in_array($selectedMethod, ['credit_card', 'link', 'bank_transfer'], true);
    $fieldClass = 'mt-1 block min-h-11 w-full rounded-lg border border-field bg-card px-2 text-tx focus:border-acc-tx';
@endphp
<fieldset class="rounded-lg border border-line bg-card p-3" data-monthly-item data-item-index="{{ $index }}">
    <legend class="px-1 text-xs font-medium">Ödeme <span data-item-number>{{ is_numeric($index) ? $index + 1 : '' }}</span></legend>
    <div class="grid min-w-0 gap-2 sm:grid-cols-2 xl:grid-cols-4">
        <div>
            <label class="block text-sm text-mut" for="{{ $fieldId }}-method">Ödeme yöntemi</label>
            <select id="{{ $fieldId }}-method" name="{{ $prefix }}[method]" class="{{ $fieldClass }}" required data-item-method @error('items.'.$index.'.method') aria-invalid="true" aria-describedby="{{ $fieldId }}-method-error" @enderror>
                @foreach (['cash' => 'Nakit', 'credit_card' => 'Kredi kartı', 'link' => 'Link ödemesi', 'bank_transfer' => 'Havale', 'extra' => 'Ekstra tahsilat'] as $value => $label)
                    <option value="{{ $value }}" @selected($selectedMethod === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('items.'.$index.'.method')<p id="{{ $fieldId }}-method-error" class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
        </div>
        <div data-item-bank-group @if (! $needsBank) hidden @endif>
            <label class="block text-sm text-mut" for="{{ $fieldId }}-bank">Banka / ödeme kuruluşu</label>
            <select id="{{ $fieldId }}-bank" name="{{ $prefix }}[bank_name]" class="{{ $fieldClass }}" data-item-bank @required($needsBank) @disabled(! $needsBank)
                    @error('items.'.$index.'.bank_name') aria-invalid="true" aria-describedby="{{ $fieldId }}-bank-error" @enderror>
                <option value="">Seçin</option>
                @foreach ($bankNames as $bankName)
                    <option value="{{ $bankName }}" @selected(($item['bank_name'] ?? '') === $bankName)>{{ $bankName }}</option>
                @endforeach
            </select>
            @error('items.'.$index.'.bank_name')<p id="{{ $fieldId }}-bank-error" class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm text-mut" for="{{ $fieldId }}-currency">Para birimi</label>
            <select id="{{ $fieldId }}-currency" name="{{ $prefix }}[currency]" class="{{ $fieldClass }}" required data-item-currency @error('items.'.$index.'.currency') aria-invalid="true" aria-describedby="{{ $fieldId }}-currency-error" @enderror>
                @foreach ($currencies as $currency)
                    <option value="{{ $currency->value }}" @selected($selectedCurrency === $currency->value)>{{ $currency->value === 'TRY' ? 'TL — Türk Lirası' : $currency->value.' — '.$currency->label() }}</option>
                @endforeach
            </select>
            @error('items.'.$index.'.currency')<p id="{{ $fieldId }}-currency-error" class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm text-mut" for="{{ $fieldId }}-amount">Tutar (<span data-item-currency-label>{{ $selectedCurrency === 'TRY' ? 'TL' : $selectedCurrency }}</span>) <span class="text-acc-tx" aria-hidden="true">*</span></label>
            <input id="{{ $fieldId }}-amount" name="{{ $prefix }}[amount]" value="{{ $item['amount'] ?? '' }}" class="{{ $fieldClass }} tabular-nums" inputmode="decimal" autocomplete="off" required data-money-input data-item-amount placeholder="0,00" @error('items.'.$index.'.amount') aria-invalid="true" aria-describedby="{{ $fieldId }}-amount-error" @enderror>
            @error('items.'.$index.'.amount')<p id="{{ $fieldId }}-amount-error" class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
        </div>
    </div>
    <div class="mt-2 grid gap-2 rounded-lg border border-line p-2 sm:grid-cols-3" data-item-fx @if (! $foreign) hidden @endif>
        <div>
            <label class="block text-sm text-mut" for="{{ $fieldId }}-mode">TL karşılığını nasıl gireceksiniz?</label>
            <select id="{{ $fieldId }}-mode" name="{{ $prefix }}[conversion_mode]" class="{{ $fieldClass }}" data-item-mode @error('items.'.$index.'.conversion_mode') aria-invalid="true" aria-describedby="{{ $fieldId }}-mode-error" @enderror>
                <option value="rate" @selected($mode === 'rate')>Kur gir</option>
                <option value="total" @selected($mode === 'total')>Toplam TL tutarını gir</option>
            </select>
            @error('items.'.$index.'.conversion_mode')<p id="{{ $fieldId }}-mode-error" class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
        </div>
        <div data-item-rate-group @if ($mode !== 'rate') hidden @endif>
            <x-ui.input label="Kur (1 birim → TL)" :name="$prefix.'[exchange_rate_to_try]'" :id="$fieldId.'-rate'" :value="$item['exchange_rate_to_try'] ?? ''" :error-key="'items.'.$index.'.exchange_rate_to_try'"
                        inputmode="decimal" autocomplete="off" data-item-rate placeholder="Örn. 41,2500" hint="En fazla 4 ondalık basamak" :required="$foreign && $mode === 'rate'" />
        </div>
        <div data-item-total-group @if ($mode !== 'total') hidden @endif>
            <x-ui.input label="Toplam TL tutarı" :name="$prefix.'[try_equivalent]'" :id="$fieldId.'-total'" :value="$item['try_equivalent'] ?? ''" :error-key="'items.'.$index.'.try_equivalent'"
                        inputmode="decimal" autocomplete="off" data-money-input data-item-total placeholder="0,00" hint="Kur bu tutardan hesaplanır" :required="$foreign && $mode === 'total'" />
        </div>
        <p class="self-center text-xs font-medium tabular-nums text-tx" data-item-preview aria-live="polite"></p>
    </div>
    <div class="mt-2 flex flex-wrap items-end gap-2">
        <div class="min-w-0 flex-1 basis-60">
            <x-ui.input :label="$selectedMethod === 'extra' ? 'Ekstra tahsilat notu' : 'Açıklama / not'" :name="$prefix.'[note]'" :id="$fieldId.'-note'" :value="$item['note'] ?? ''" :error-key="'items.'.$index.'.note'"
                        maxlength="1000" data-item-note />
        </div>
        <x-ui.button type="button" variant="ghost" data-remove-item aria-label="Bu ödeme satırını kaldır">Satırı kaldır</x-ui.button>
    </div>
</fieldset>
