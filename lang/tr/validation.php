<?php

declare(strict_types=1);

/*
 * Türkçe doğrulama mesajları.
 *
 * APP_LOCALE ve APP_FALLBACK_LOCALE 'tr' olduğu için bu dosya yoksa Laravel
 * çeviri anahtarını ham hâliyle ("validation.required") ekrana basar. Burada
 * yalnızca projede fiilen kullanılan kurallar bulunur; yeni bir kural
 * eklendiğinde karşılığı da buraya eklenir.
 *
 * Alan adları (":attribute") her FormRequest'in kendi attributes() metodundan
 * gelir; bu yüzden aşağıda 'attributes' listesi tutulmaz.
 */

return [
    'after' => ':attribute alanı :date tarihinden sonra olmalıdır.',
    'after_or_equal' => ':attribute alanı :date tarihinden önce olamaz.',
    'array' => ':attribute alanı bir liste olmalıdır.',
    'before' => ':attribute alanı :date tarihinden önce olmalıdır.',
    'boolean' => ':attribute alanı yalnızca evet veya hayır olabilir.',
    'confirmed' => ':attribute alanı tekrarıyla uyuşmuyor.',
    'current_password' => 'Mevcut şifre hatalı.',
    'date' => ':attribute geçerli bir tarih değil.',
    'decimal' => ':attribute alanı :decimal ondalık basamak içermelidir.',
    'different' => ':attribute alanı :other alanından farklı olmalıdır.',
    'email' => ':attribute geçerli bir e-posta adresi olmalıdır.',
    'exists' => 'Seçilen :attribute geçersiz.',
    'gt' => [
        'numeric' => ':attribute alanı :value değerinden büyük olmalıdır.',
        'string' => ':attribute alanı :value karakterden uzun olmalıdır.',
    ],
    'gte' => [
        'numeric' => ':attribute alanı :value değerinden küçük olamaz.',
        'string' => ':attribute alanı en az :value karakter olmalıdır.',
    ],
    'in' => 'Seçilen :attribute geçersiz.',
    'integer' => ':attribute alanı tam sayı olmalıdır.',
    'lt' => [
        'numeric' => ':attribute alanı :value değerinden küçük olmalıdır.',
        'string' => ':attribute alanı :value karakterden kısa olmalıdır.',
    ],
    'lte' => [
        'numeric' => ':attribute alanı :value değerinden büyük olamaz.',
        'string' => ':attribute alanı en fazla :value karakter olmalıdır.',
    ],
    'max' => [
        'array' => ':attribute alanı en fazla :max öge içerebilir.',
        'file' => ':attribute alanı en fazla :max kilobayt olabilir.',
        'numeric' => ':attribute alanı :max değerinden büyük olamaz.',
        'string' => ':attribute alanı en fazla :max karakter olabilir.',
    ],
    'min' => [
        'array' => ':attribute alanı en az :min öge içermelidir.',
        'file' => ':attribute alanı en az :min kilobayt olmalıdır.',
        'numeric' => ':attribute alanı en az :min olmalıdır.',
        'string' => ':attribute alanı en az :min karakter olmalıdır.',
    ],
    'numeric' => ':attribute alanı sayı olmalıdır.',
    'prohibited' => ':attribute alanı gönderilemez.',
    'required' => ':attribute alanı zorunludur.',
    'required_if' => ':other :value olduğunda :attribute alanı zorunludur.',
    'same' => ':attribute alanı :other alanıyla aynı olmalıdır.',
    'string' => ':attribute alanı metin olmalıdır.',
    'unique' => 'Bu :attribute zaten kullanılıyor.',
    'uploaded' => ':attribute yüklenemedi.',
    'url' => ':attribute geçerli bir adres olmalıdır.',

    'custom' => [],
    'attributes' => [],
];
