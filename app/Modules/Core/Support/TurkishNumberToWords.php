<?php

declare(strict_types=1);

namespace App\Modules\Core\Support;

use InvalidArgumentException;

/**
 * Tam sayıyı Türkçe kelimelere çevirir — yalnızca Tahsilat Makbuzu'ndaki
 * "Yalnız" alanı için (v2.1.1-B §15).
 *
 * KASITLI OLARAK yalnızca TAM SAYI alır (lira/kuruş kısmı ayrı ayrı,
 * çağıran tarafından — bkz. `Payment::amountInWords()`). Yabancı para
 * birimleri için ÇEVİRİ ÜRETİLMEZ: yanlış/uydurma bir çeviri "decorative
 * completeness"tan daha kötüdür (kullanıcı talimatı §15 — "Correctness
 * over decorative completeness").
 */
final class TurkishNumberToWords
{
    /** @var array<int, string> */
    private const ONES = ['', 'bir', 'iki', 'üç', 'dört', 'beş', 'altı', 'yedi', 'sekiz', 'dokuz'];

    /** @var array<int, string> */
    private const TENS = ['', 'on', 'yirmi', 'otuz', 'kırk', 'elli', 'altmış', 'yetmiş', 'seksen', 'doksan'];

    /** @var array<int, string> */
    private const GROUP_NAMES = ['', 'bin', 'milyon', 'milyar', 'trilyon'];

    public static function convert(int $number): string
    {
        if ($number < 0) {
            throw new InvalidArgumentException('Negatif sayı kelimeye çevrilemez.');
        }

        if ($number === 0) {
            return 'sıfır';
        }

        $groups = [];
        $remaining = $number;

        while ($remaining > 0) {
            $groups[] = $remaining % 1000;
            $remaining = intdiv($remaining, 1000);
        }

        $parts = [];

        for ($i = count($groups) - 1; $i >= 0; $i--) {
            $group = $groups[$i];

            if ($group === 0) {
                continue;
            }

            // "bir bin" değil, yalnız "bin" denir — başka hiçbir grupta bu istisna yoktur.
            if ($i === 1 && $group === 1) {
                $parts[] = 'bin';

                continue;
            }

            $groupWords = self::threeDigitsToWords($group);
            $parts[] = $i > 0 ? trim($groupWords.' '.self::GROUP_NAMES[$i]) : $groupWords;
        }

        return implode(' ', $parts);
    }

    /**
     * Her kelimenin ilk harfini büyütür — TÜRKÇE İ/I kuralına göre.
     *
     * `mb_convert_case(..., MB_CASE_TITLE)` KULLANILMAZ: mbstring'in Unicode
     * büyük harf dönüşümü yerel-duyarsızdır ve küçük "i"yi ASCII "I"ya
     * çevirir ("iki" → "Iki") — Türkçede doğrusu noktalı büyük İ'dir
     * ("İki"). Bu metod yalnızca kelimenin İLK karakterini, Türkçe kuralına
     * göre elle büyütür; kelimenin geri kalanına dokunmaz (zaten küçük harf
     * üretilir, bkz. `convert()`).
     */
    public static function titleCase(string $text): string
    {
        $words = explode(' ', $text);

        foreach ($words as &$word) {
            if ($word === '') {
                continue;
            }

            $first = mb_substr($word, 0, 1, 'UTF-8');
            $rest = mb_substr($word, 1, null, 'UTF-8');
            $upperFirst = $first === 'i' ? 'İ' : mb_strtoupper($first, 'UTF-8');
            $word = $upperFirst.$rest;
        }

        return implode(' ', $words);
    }

    private static function threeDigitsToWords(int $n): string
    {
        $hundreds = intdiv($n, 100);
        $remainder = $n % 100;
        $tens = intdiv($remainder, 10);
        $ones = $remainder % 10;

        $words = [];

        if ($hundreds > 0) {
            // "bir yüz" değil, yalnız "yüz" denir.
            if ($hundreds > 1) {
                $words[] = self::ONES[$hundreds];
            }
            $words[] = 'yüz';
        }

        if ($tens > 0) {
            $words[] = self::TENS[$tens];
        }

        if ($ones > 0) {
            $words[] = self::ONES[$ones];
        }

        return implode(' ', $words);
    }
}
