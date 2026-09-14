<?php

declare(strict_types=1);

namespace App\Modules\MonthlyAccount\Services;

use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
use App\Modules\MonthlyAccount\Enums\ConversionMode;
use InvalidArgumentException;

final class MonthlyAccountConversion
{
    /** Kullanıcının seçtiği kur/toplam yönteminden değişmez TL anlık görüntüsü. */
    public static function snapshot(array $item): array
    {
        $amount = self::money($item['amount']);

        if (! $amount->isPositive()) {
            throw new InvalidArgumentException('Tutar sıfırdan büyük olmalıdır.');
        }

        if ($item['currency'] === Currency::TRY->value) {
            return [
                'amount' => $amount->toDecimal(),
                'conversion_mode' => null,
                'exchange_rate_to_try' => null,
                'try_equivalent' => $amount->toDecimal(),
            ];
        }

        $mode = ConversionMode::from($item['conversion_mode']);

        if ($mode === ConversionMode::Total) {
            $equivalent = self::money($item['try_equivalent']);
            if (! $equivalent->isPositive()) {
                throw new InvalidArgumentException('TL karşılığı sıfırdan büyük olmalıdır.');
            }

            // Bölüm ve kalan ayrı ölçeklenir; tam tutar × 10.000 taşması oluşmaz.
            $whole = intdiv($equivalent->minorUnits, $amount->minorUnits);
            if ($whole > 999999) {
                throw new InvalidArgumentException('Hesaplanan kur 999.999,9999 sınırını aşıyor.');
            }
            $remainder = $equivalent->minorUnits % $amount->minorUnits;
            $fraction = 0;
            for ($digit = 0; $digit < 4; $digit++) {
                $remainder *= 10;
                $fraction = $fraction * 10 + intdiv($remainder, $amount->minorUnits);
                $remainder %= $amount->minorUnits;
            }
            $scaledRate = $whole * 10000 + $fraction;
            if ($remainder * 2 >= $amount->minorUnits) {
                $scaledRate++;
            }
        } else {
            $rate = $item['exchange_rate_to_try'];
            if (! is_string($rate) || ! preg_match('/^\d{1,6}(?:\.\d{1,4})?$/', $rate)) {
                throw new InvalidArgumentException('Kur en fazla dört ondalık basamak içermelidir.');
            }
            [$whole, $fraction] = array_pad(explode('.', $rate, 2), 2, '');
            $scaledRate = (int) $whole * 10000 + (int) str_pad($fraction, 4, '0');
            if ($scaledRate < 1) {
                throw new InvalidArgumentException('Kur sıfırdan büyük olmalıdır.');
            }

            // Money'nin 64 bit sınırını aşan ara çarpımlar yerine bölüm/kalan.
            $wholeMinor = intdiv($amount->minorUnits, 10000);
            if ($wholeMinor > intdiv(Money::MAX_MINOR_UNITS, $scaledRate)) {
                throw new InvalidArgumentException('TL karşılığı desteklenen tutar sınırını aşıyor.');
            }
            $minor = $wholeMinor * $scaledRate
                + intdiv(($amount->minorUnits % 10000) * $scaledRate + 5000, 10000);
            $equivalent = Money::fromMinorUnits($minor);
            if (! $equivalent->isPositive()) {
                throw new InvalidArgumentException('TL karşılığı en az 0,01 TL olmalıdır.');
            }
        }

        if ($scaledRate < 1 || $scaledRate > 9_999_999_999) {
            throw new InvalidArgumentException('Hesaplanan kur 0,0001–999.999,9999 aralığında olmalıdır.');
        }

        return [
            'amount' => $amount->toDecimal(),
            'conversion_mode' => $mode->value,
            'exchange_rate_to_try' => sprintf('%d.%04d', intdiv($scaledRate, 10000), $scaledRate % 10000),
            'try_equivalent' => $equivalent->toDecimal(),
        ];
    }

    public static function money(mixed $value): Money
    {
        if (! (is_string($value) || is_int($value)) || ! preg_match('/^\d{1,13}(?:\.\d{1,2})?$/', (string) $value)) {
            throw new InvalidArgumentException('Tutar en fazla iki ondalık basamak içermelidir.');
        }

        return Money::fromDecimal($value);
    }
}
