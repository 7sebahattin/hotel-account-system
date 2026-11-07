@csrf

<x-ui.errors />

@php
    $s = $stay ?? null;
    $mode = old('pricing_mode', $s?->pricing_mode?->value ?? 'total');
@endphp

<div class="grid gap-4 sm:grid-cols-2" data-stay-pricing>
    <div class="sm:col-span-2">
        <x-ui.select label="Firma (isteğe bağlı)" name="company_id" data-stay-company data-searchable-select
                     hint="Yazarak arayabilirsiniz.">
            <option value="">Bireysel konaklama — firma yok</option>
            @foreach ($companies as $id => $name)
                <option value="{{ $id }}"
                    @selected((string) old('company_id', $selectedCompanyId ?? '') === (string) $id)>
                    {{ $name }}
                </option>
            @endforeach
        </x-ui.select>
    </div>

    <div class="sm:col-span-2" data-stay-individual>
        <x-ui.select label="Bireysel hesap" name="individual_account_id"
                     hint="Önceden gelen kişiyi seçin. Yeni hesap seçerseniz misafir adına ayrı bir cari hesap açılır; aynı isimler birleştirilmez.">
            <option value="">Yeni bireysel hesap aç</option>
            @foreach ($individuals as $id => $name)
                <option value="{{ $id }}" @selected((string) old('individual_account_id', $selectedIndividualId ?? '') === (string) $id)>{{ $name }}</option>
            @endforeach
        </x-ui.select>
    </div>

    <x-ui.input label="Misafir adı" name="guest_name" required maxlength="150" :value="$s->guest_name ?? null" />

    <div>
        <x-ui.select label="Oda" name="room_id">
            <option value="">Seçilmedi</option>
            @foreach ($rooms as $id => $label)
                <option value="{{ $id }}" @selected((string) old('room_id', $s->room_id ?? '') === (string) $id)>
                    {{ $label }}
                </option>
            @endforeach
        </x-ui.select>

        @if ($s?->room_id === null && $s?->room_no)
            <p class="mt-1 text-xs text-mut">
                Geçmiş kayıt oda bilgisi (serbest metin): <span class="tabular-nums">{{ $s->room_no }}</span>
            </p>
        @endif
    </div>

    <x-ui.input label="Giriş tarihi" name="check_in_date" type="date" required
                data-check-in
                hint="Başka bir programdan kopyaladığınız tarihi (31.10.2026) Ctrl+V ile yapıştırabilirsiniz."
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

    {{--
        Kur yalnızca YENİ kayıtta istenir (v2.1.1-C §2) — StayRequest bu
        alanı düzenlemede hiç TANIMLAMAZ, bu yüzden formda da göstermek
        yanıltıcı olurdu ("gönderilse bile hiçbir etkisi yok"). Geçmiş bir
        kaydın eksik kuru YALNIZCA "Yönetim → Geçmiş Döviz Düzeltme" ile
        doldurulur.
    --}}
    @unless (isset($stay))
        <div data-exchange-rate-group @if (old('currency', 'TRY') === 'TRY') hidden @endif>
            <x-ui.input label="Kur (1 birim → TL)" name="exchange_rate_to_try" inputmode="decimal"
                        data-exchange-rate placeholder="51,20" class="tabular-nums"
                        :value="old('exchange_rate_to_try')"
                        hint="TL dışı para biriminde zorunludur; işlem anındaki kur olarak SAKLANIR, sonradan güncellenmez." />
            <p class="mt-1 text-xs text-mut" data-exchange-rate-preview aria-live="polite"></p>
        </div>
    @endunless

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
        {{--
            "Basitleştir" turu (v2.1.1-C §1): notlar sık kullanılmayan bir
            alandır, formun ana akışında yer kaplamasın diye katlanabilir bir
            bölüme alındı. Doldurulmuşsa (düzenleme) varsayılan olarak AÇIK
            gelir — kullanıcı kendi girdiğini kapalı bir panelin ARKASINDA
            kaybetmemelidir.
        --}}
        <details @if (trim((string) old('notes', $s->notes ?? '')) !== '') open @endif>
            <summary class="cursor-pointer text-sm text-mut select-none hover:text-tx">Notlar (isteğe bağlı)</summary>
            <div class="mt-2">
                <x-ui.textarea label="Notlar" name="notes" :rows="3" maxlength="5000" :value="$s->notes ?? null" />
            </div>
        </details>
    </div>
</div>

@isset($stay)
    <x-ui.alert tone="info" class="mt-4">
        Firma, tarih, ücret giriş şekli, günlük ücret, tutar veya para birimi değişirse mevcut cari
        hareketi ters kaydedilir ve yeni bir borç hareketi oluşturulur. Eski hareket silinmez.
    </x-ui.alert>
@endisset
