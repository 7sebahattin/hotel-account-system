<?php

declare(strict_types=1);

namespace App\Modules\Core\Support;

/**
 * Sayılara Türkçe iyelik eki ("%31'i", "%47'si", "%0'ı").
 *
 * Ek, sayının okunuşundaki SON kelimeye göre seçilir: birler basamağı
 * sıfır değilse o rakam, değilse onlar basamağı, o da sıfırsa yüz/bin.
 */
final class TurkishSuffix
{
    private const array UNITS = [1 => 'i', 2 => 'si', 3 => 'ü', 4 => 'ü', 5 => 'i', 6 => 'sı', 7 => 'si', 8 => 'i', 9 => 'u'];

    private const array TENS = [1 => 'u', 2 => 'si', 3 => 'u', 4 => 'ı', 5 => 'si', 6 => 'ı', 7 => 'i', 8 => 'i', 9 => 'ı'];

    public static function possessive(int $number): string
    {
        $number = abs($number);

        if ($number === 0) {
            return 'ı';
        }

        if ($number % 10 !== 0) {
            return self::UNITS[$number % 10];
        }

        if ($number % 100 !== 0) {
            return self::TENS[intdiv($number % 100, 10)];
        }

        // yüz → "ü", bin → "i", milyon → "u"
        return $number % 1000 !== 0 ? 'ü' : ($number % 1_000_000 !== 0 ? 'i' : 'u');
    }
}
