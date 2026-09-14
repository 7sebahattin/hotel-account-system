@props([
    // Formun ŞU ANKİ para birimi — TRY ise tüm blok gizlenir.
    'currency' => 'TRY',
    // Mevcut değerler (düzenleme ekranında dolu gelir).
    'rate' => null,
    'equivalent' => null,
    /*
        'grid'   — normal form düzeni (Konaklama / Tahsilat / Makbuz).
        'inline' — tablo satırı içinde tek sıra (Geçmiş Döviz Düzeltme); aynı
                   alan adlarını ve aynı JS kancalarını kullanır, yalnızca
                   çerçevesi farklıdır. İki düzen TEK dosyada durur ki alan
                   adları ve ipuçları iki yerde ayrışmasın (CLAUDE.md §4.2/4).
    */
    'layout' => 'grid',
    // Aynı sayfada birden çok blok olabilir (düzeltme ekranı onlarca satır
    // render eder); her blok kendi benzersiz id önekini alır.
    'idPrefix' => 'fx',
])

@php
    $isTry = $currency === 'TRY';
    $mode = old('fx_input_mode', 'rate');
    $inline = $layout === 'inline';
@endphp

{{--
    Döviz girişi — İKİ MOD (K-110, 14 Eylül 2026 kullanıcı talimatı).

    Operatör ya KURU ya da doğrudan TL KARŞILIĞINI girer; diğeri sunucuda
    türetilir. TL modunda girilen tutar AYNEN saklanır, kurdan yeniden
    hesaplanmaz — kur DECIMAL(10,4) olduğu için her toplam dört ondalıkla tam
    ifade edilemez ve operatörün yazdığı rakamı kaydırmak kabul edilemez
    (bkz. Core\Support\ExchangeRateSnapshot).

    Hesaplama HER ZAMAN sunucuda tekrarlanır; buradaki önizleme yalnızca
    bilgi amaçlıdır. Tutar asla TL'ye çevrilerek kaydedilmez (§6/L5).
--}}
<div data-fx-input data-exchange-rate-group
     @class(['sm:col-span-2' => ! $inline, 'flex flex-wrap items-center gap-2' => $inline])
     @if ($isTry) hidden @endif>

    @if ($inline)
        <label class="sr-only" for="{{ $idPrefix }}-mode">Döviz giriş şekli</label>
        <select name="fx_input_mode" id="{{ $idPrefix }}-mode" data-fx-mode
                class="min-h-11 rounded-lg border border-field bg-card px-2 text-sm text-tx focus:border-acc-tx">
            <option value="rate" @selected($mode === 'rate')>Kur</option>
            <option value="total" @selected($mode === 'total')>TL karşılığı</option>
        </select>

        <div data-fx-field="rate" @if ($mode !== 'rate') hidden @endif>
            <label class="sr-only" for="{{ $idPrefix }}-rate">Kur</label>
            <input type="text" name="exchange_rate_to_try" id="{{ $idPrefix }}-rate" inputmode="decimal"
                   data-exchange-rate placeholder="ör. 51,20" value="{{ old('exchange_rate_to_try', $rate) }}"
                   class="min-h-11 w-32 rounded-lg border border-field bg-card px-2 text-sm tabular-nums text-tx focus:border-acc-tx">
        </div>

        <div data-fx-field="total" @if ($mode !== 'total') hidden @endif>
            <label class="sr-only" for="{{ $idPrefix }}-total">TL karşılığı (toplam)</label>
            <input type="text" name="try_equivalent_input" id="{{ $idPrefix }}-total" inputmode="decimal"
                   data-fx-total placeholder="ör. 41.234,57" value="{{ old('try_equivalent_input', $equivalent) }}"
                   class="min-h-11 w-36 rounded-lg border border-field bg-card px-2 text-sm tabular-nums text-tx focus:border-acc-tx">
        </div>

        {{ $slot }}

        <p class="w-full text-xs text-mut" data-exchange-rate-preview aria-live="polite"></p>
    @else
        <div class="grid gap-3 sm:grid-cols-2">
            <x-ui.select label="Döviz giriş şekli" name="fx_input_mode" data-fx-mode
                         :id="$idPrefix.'-mode'"
                         hint="Hangisi elinizdeyse onu girin; diğerini sistem hesaplar.">
                <option value="rate" @selected($mode === 'rate')>Kur gireceğim</option>
                <option value="total" @selected($mode === 'total')>TL karşılığını gireceğim</option>
            </x-ui.select>

            <div data-fx-field="rate" @if ($mode !== 'rate') hidden @endif>
                <x-ui.input label="Kur (1 birim → TL)" name="exchange_rate_to_try" inputmode="decimal"
                            data-exchange-rate placeholder="51,20" class="tabular-nums"
                            :id="$idPrefix.'-rate'"
                            :value="old('exchange_rate_to_try', $rate)"
                            hint="İşlem anındaki kur olarak SAKLANIR, sonradan otomatik güncellenmez." />
            </div>

            <div data-fx-field="total" @if ($mode !== 'total') hidden @endif>
                <x-ui.input label="TL karşılığı (toplam)" name="try_equivalent_input" inputmode="decimal"
                            data-fx-total placeholder="41.234,57" class="tabular-nums"
                            :id="$idPrefix.'-total'"
                            :value="old('try_equivalent_input', $equivalent)"
                            hint="Girdiğiniz tutar AYNEN saklanır; kur bundan hesaplanır." />
            </div>
        </div>

        <p class="mt-1 text-xs text-mut" data-exchange-rate-preview aria-live="polite"></p>
    @endif
</div>
