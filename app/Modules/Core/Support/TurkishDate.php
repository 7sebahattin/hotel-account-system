<?php

declare(strict_types=1);

namespace App\Modules\Core\Support;

use Carbon\CarbonInterface;

/**
 * Türkçe gün/ay adları — uygulama yereline (APP_LOCALE) bağımlı olmadan.
 *
 * Ekranda gösterilen gün ve ay adları test ve üretim ortamında aynı çıkar;
 * `translatedFormat()` yerel ayar yanlış kurulursa İngilizceye düşerdi.
 */
final class TurkishDate
{
    /** @var list<string> Carbon `dayOfWeek` sırası (0 = Pazar). */
    private const array WEEKDAYS = ['Pazar', 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi'];

    /** @var list<string> */
    private const array WEEKDAYS_SHORT = ['Paz', 'Pzt', 'Sal', 'Çar', 'Per', 'Cum', 'Cmt'];

    /** @var list<string> */
    private const array MONTHS = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];

    /** @var list<string> */
    private const array MONTHS_SHORT = ['Oca', 'Şub', 'Mar', 'Nis', 'May', 'Haz', 'Tem', 'Ağu', 'Eyl', 'Eki', 'Kas', 'Ara'];

    public static function weekday(CarbonInterface $date): string
    {
        return self::WEEKDAYS[$date->dayOfWeek];
    }

    public static function shortWeekday(CarbonInterface $date): string
    {
        return self::WEEKDAYS_SHORT[$date->dayOfWeek];
    }

    public static function month(CarbonInterface $date): string
    {
        return self::MONTHS[$date->month - 1];
    }

    public static function shortMonth(CarbonInterface $date): string
    {
        return self::MONTHS_SHORT[$date->month - 1];
    }

    /** "23 Eylül 2026" */
    public static function long(CarbonInterface $date): string
    {
        return $date->day.' '.self::month($date).' '.$date->year;
    }
}
