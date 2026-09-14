<?php

declare(strict_types=1);

namespace App\Modules\Core\Support;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Döviz girişinin PAYLAŞILAN doğrulama sözleşmesi (v2.1.1-F, K-110).
 *
 * Dört ekran (Konaklama, Tahsilat, Tahsilat Makbuzu Defteri, Geçmiş Döviz
 * Düzeltme) aynı üç alanı aynı kurallarla ister; kurallar burada TEK yerdedir
 * (CLAUDE.md §4.2/4). Alanlar:
 *
 * - `fx_input_mode`        — `rate` | `total`; hangi alanın doldurulacağını söyler
 * - `exchange_rate_to_try` — `rate` modunda zorunlu (DECIMAL(10,4))
 * - `try_equivalent_input` — `total` modunda zorunlu (DECIMAL(15,2))
 *
 * `try_equivalent_input` bilerek modelin kolon adından (`try_equivalent`)
 * FARKLIDIR: bu bir İSTEK alanıdır ve hiçbir `$fillable` yoluyla modele
 * doğrudan yazılamaz — değer her zaman `ExchangeRateSnapshot` üzerinden
 * geçer.
 *
 * `nullable` + `required_unless` ikilisi v2.1.1-C'deki gerekçenin AYNISIYLA
 * durur: alan HTML'de gizliyken bile tarayıcı onu BOŞ değerle gönderir
 * (`hidden`, `disabled` değil); `nullable` boşsa biçim kurallarını atlatır ama
 * `required_unless` yine de çalışır (Laravel önce "zorunlu mu" sorusunu
 * çözer, `nullable` onu bastırmaz).
 */
final class ExchangeRateInput
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        $onlyWhenForeign = ['nullable', 'prohibited_if:currency,TRY', 'required_unless:currency,TRY'];

        return [
            /*
             * Mod İSTEĞE BAĞLIDIR ve yokluğu "kur" demektir. İki gerekçe:
             * (1) v2.1.1-C'den beri var olan tek alanlı sözleşme (yalnızca
             * `exchange_rate_to_try` gönderen çağrılar) kırılmadan çalışmaya
             * devam eder; (2) `prohibited_if` BURADA KULLANILAMAZ — blok TRY
             * seçildiğinde gizlense bile `<select>` değerini yine GÖNDERİR
             * ("rate"), yani boş gelmez ve kural haksız yere patlardı. Mod
             * bir VERİ değil, hangi alanın okunacağını söyleyen bir
             * ayraçtır; TRY'de zaten iki değer alanı da boştur.
             */
            'fx_input_mode' => [
                'nullable',
                Rule::in([ExchangeRateSnapshot::MODE_RATE, ExchangeRateSnapshot::MODE_TOTAL]),
            ],
            'exchange_rate_to_try' => [
                'exclude_if:fx_input_mode,'.ExchangeRateSnapshot::MODE_TOTAL,
                ...$onlyWhenForeign,
                'numeric', 'gt:0', 'decimal:0,4', 'max:999999.9999',
            ],
            // `exclude_unless`: mod AÇIKÇA "total" değilse alan tamamen
            // düşer — mod hiç gönderilmediğinde "zorunlu" sayılmaz.
            'try_equivalent_input' => [
                'exclude_unless:fx_input_mode,'.ExchangeRateSnapshot::MODE_TOTAL,
                ...$onlyWhenForeign,
                'numeric', 'gt:0', 'decimal:0,2', 'max:9999999999999.99',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        return [
            'fx_input_mode' => 'döviz giriş şekli',
            'exchange_rate_to_try' => 'kur',
            'try_equivalent_input' => 'TL karşılığı',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'fx_input_mode.required_unless' => 'TL dışındaki para birimlerinde kur mu yoksa TL karşılığı mı gireceğinizi seçiniz.',
            'fx_input_mode.prohibited_if' => 'TL için döviz giriş şekli seçilmez.',
            'exchange_rate_to_try.required_unless' => 'Kur girişi seçildiğinde kur zorunludur.',
            'exchange_rate_to_try.prohibited_if' => 'TL için kur girilmez.',
            'exchange_rate_to_try.decimal' => 'Kur en fazla dört ondalık basamak içerebilir.',
            'try_equivalent_input.required_unless' => 'TL karşılığı girişi seçildiğinde toplam TL tutarı zorunludur.',
            'try_equivalent_input.prohibited_if' => 'TL için TL karşılığı girilmez.',
            'try_equivalent_input.decimal' => 'TL karşılığı en fazla iki ondalık basamak içerebilir.',
        ];
    }

    /** Türkçe klavyede ondalık ayırıcı virgüldür; noktaya çevrilir. */
    public static function normalize(FormRequest $request): void
    {
        foreach (['exchange_rate_to_try', 'try_equivalent_input'] as $field) {
            $value = $request->input($field);

            if (is_string($value)) {
                $request->merge([$field => Money::normalizeInput($value)]);
            }
        }
    }
}
