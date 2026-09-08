@csrf

<x-ui.errors />

@php
    $s = $stay ?? null;
    $mode = old('pricing_mode', $s?->pricing_mode?->value ?? 'total');
@endphp

<div class="grid gap-4 sm:grid-cols-2" data-stay-pricing>
    <div class="sm:col-span-2">
        <x-ui.select label="Firma" name="company_id" required>
            <option value="">Seçiniz…</option>
            @foreach ($companies as $id => $name)
                <option value="{{ $id }}"
                    @selected((string) old('company_id', $s->company_id ?? ($selectedCompanyId ?? '')) === (string) $id)>
                    {{ $name }}
                </option>
            @endforeach
        </x-ui.select>
    </div>

    <x-ui.input label="Misafir adı" name="guest_name" required maxlength="150" :value="$s->guest_name ?? null" />
    <x-ui.input label="Oda" name="room_no" maxlength="20" :value="$s->room_no ?? null" />

    <x-ui.input label="Giriş tarihi" name="check_in_date" type="date" required
                data-check-in
                :value="$s?->check_in_date?->toDateString()" />

    <x-ui.input label="Çıkış tarihi" name="check_out_date" type="date" required
                data-check-out
                :value="$s?->check_out_date?->toDateString()"
                hint="En az 1 gece. Gece sayısı tarihlerden otomatik hesaplanır." />

    <div class="sm:col-span-2">
        <x-ui.select label="Ücret giriş şekli" name="pricing_mode" required data-pricing-mode
                     hint="Günlük ücret seçilirse toplam tutar sunucuda gece sayısıyla çarpılarak hesaplanır.">
            @foreach ($pricingModes as $pricingMode)
                <option value="{{ $pricingMode->value }}" @selected($mode === $pricingMode->value)>
                    {{ $pricingMode->label() }}
                </option>
            @endforeach
        </x-ui.select>
    </div>

    <div data-pricing-total @if ($mode !== 'total') hidden @endif>
        <x-ui.input label="Toplam tutar" name="amount" inputmode="decimal" :required="$mode === 'total'"
                    placeholder="9000,00" :value="$s->amount ?? null" class="tabular-nums" />
    </div>

    <div data-pricing-daily @if ($mode !== 'daily') hidden @endif>
        <x-ui.input label="Günlük ücret" name="daily_rate" inputmode="decimal" :required="$mode === 'daily'"
                    data-daily-rate
                    placeholder="2500,00" :value="$s->daily_rate ?? null" class="tabular-nums" />
    </div>

    <x-ui.select label="Para birimi" name="currency" required data-currency>
        @foreach ($currencies as $currency)
            <option value="{{ $currency->value }}" data-symbol="{{ $currency->symbol() }}"
                @selected(old('currency', $s?->currency?->value ?? 'TRY') === $currency->value)>
                {{ $currency->value }} — {{ $currency->label() }}
            </option>
        @endforeach
    </x-ui.select>

    <div class="sm:col-span-2" data-pricing-daily @if ($mode !== 'daily') hidden @endif>
        <p class="block text-sm text-mut">Hesaplanan toplam</p>
        <p class="mt-1 flex min-h-11 items-center rounded-lg border border-line bg-well px-3 tabular-nums text-tx"
           data-daily-total aria-live="polite">
            @if ($s?->isDailyPriced())
                {{ $s->nights }} gece × {{ $s->dailyRateMoney()?->format($s->currency) }}
                = {{ $s->amountMoney()->format($s->currency) }}
            @else
                —
            @endif
        </p>
        <p class="mt-1 text-xs text-mut">
            Bu alan yalnızca gösterim içindir; kaydedilen tutar sunucuda yeniden hesaplanır.
        </p>
    </div>

    <div class="sm:col-span-2">
        <x-ui.textarea label="Notlar" name="notes" :rows="3" maxlength="5000" :value="$s->notes ?? null" />
    </div>
</div>

@isset($stay)
    <x-ui.alert tone="info" class="mt-4">
        Firma, tarih, ücret giriş şekli, günlük ücret, tutar veya para birimi değişirse mevcut cari
        hareketi ters kaydedilir ve yeni bir borç hareketi oluşturulur. Eski hareket silinmez.
    </x-ui.alert>
@endisset
