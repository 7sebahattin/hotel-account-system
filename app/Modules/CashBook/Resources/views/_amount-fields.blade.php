{{--
    Çok para birimli tutar alanları — açılış, sayım ve devir formlarının ortağı.

    Her para birimi AYRI bir alandır ve ayrı kalır: hiçbir yerde tek toplam
    gösterilmez, hiçbir çevrim yapılmaz (CLAUDE.md §6/L5).

    P0 GÜVENLİK KURALI (v2.1.1-A §7): alan ASLA sistemin hesapladığı beklenen
    tutarla ÖN-DOLDURULMAZ. Personel gerçekte saymadan formu olduğu gibi
    gönderebilir hâle gelirse fiziksel sayım kontrolü sessizce etkisiz kalır.
    Beklenen tutar yalnızca REFERANS olarak ipucu metninde gösterilir; giriş
    alanının gerçek değeri değildir. `old()` yalnızca doğrulama hatası sonrası
    kullanıcının KENDİ yazdığı değeri geri getirmek içindir.

    Parametreler:
      $currencies  — gösterilecek para birimleri (Currency[])
      $expected    — array<string, Money>|null: varsa yalnızca "Beklenen: …"
                     ipucunda gösterilir (sayım/devir formları); alanı ASLA
                     doldurmaz.
      $legend      — alan grubunun başlığı
--}}
@php
    $expected ??= [];
@endphp

<fieldset>
    <legend class="block text-sm font-medium text-tx">{{ $legend }}</legend>

    @if ($hint ?? false)
        <p class="mt-1 text-sm text-mut">{{ $hint }}</p>
    @endif

    <div class="mt-3 grid gap-3 sm:grid-cols-2">
        @foreach ($currencies as $currency)
            @php
                $code = $currency->value;
                $expectedMoney = $expected[$code] ?? null;
            @endphp

            <x-ui.input
                :label="$code.' — '.$currency->label()"
                :name="'amounts['.$code.']'"
                :error-key="'amounts.'.$code"
                :id="'amount-'.$code"
                inputmode="decimal"
                placeholder="0,00"
                class="tabular-nums"
                :value="old('amounts.'.$code)"
                :hint="$expectedMoney ? 'Beklenen: '.$expectedMoney->format($currency) : null" />
        @endforeach
    </div>

    @error('amounts')
        <p class="mt-1 text-sm text-danger">{{ $message }}</p>
    @enderror
</fieldset>
