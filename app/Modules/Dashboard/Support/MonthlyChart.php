<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Support;

use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;

/**
 * Pano "Son N Ay Cari Hareketler" grafiğinin ölçeği.
 *
 * Tek para birimindedir (CLAUDE.md §6/L5) ve tek eksenlidir: konaklama,
 * tahsilat ve net bakiye aynı birimdedir. Konum hesabı tamsayı aritmetiğiyle
 * "baz puan" (0–10000) olarak yapılır; float kullanılmaz. Görünüm bu
 * değerleri yalnızca CSS yüzdesine çevirir — grafik JS gerektirmez.
 */
final readonly class MonthlyChart
{
    private const int FULL = 10_000;

    /** Eksen çizgisi sayısı (sıfır dahil değil, üst sınır hedefi). */
    private const int TARGET_STEPS = 4;

    /** @var list<int> Tick değerleri — kuruş. */
    public array $tickValues;

    public int $minMinor;

    public int $maxMinor;

    /**
     * @param  list<ChartMonth>  $months
     */
    public function __construct(
        public Currency $currency,
        public array $months,
    ) {
        $values = [0];

        foreach ($months as $month) {
            $values[] = $month->stays->minorUnits;
            $values[] = $month->payments->minorUnits;
            $values[] = $month->net->minorUnits;
        }

        $min = min($values);
        $max = max($values);

        // Adım "güzel" bir tam sayıdır (1/2/5 × 10^k ana birim); en az 1 ana birim.
        $step = self::niceStep(intdiv(max($max - $min, 1) + self::TARGET_STEPS * 100 - 1, self::TARGET_STEPS * 100)) * 100;

        $this->minMinor = $min < 0 ? -self::ceilDiv(-$min, $step) * $step : 0;
        $this->maxMinor = max(self::ceilDiv($max, $step) * $step, $this->minMinor + $step);

        $ticks = [];

        for ($value = $this->minMinor; $value <= $this->maxMinor; $value += $step) {
            $ticks[] = $value;
        }

        $this->tickValues = $ticks;
    }

    public function isEmpty(): bool
    {
        foreach ($this->months as $month) {
            if (! $month->stays->isZero() || ! $month->payments->isZero() || ! $month->net->isZero()) {
                return false;
            }
        }

        return true;
    }

    /** Değerin eksendeki yüksekliği — alttan yüzde, CSS için ("37.50%"). */
    public function position(Money|int $value): string
    {
        $minor = $value instanceof Money ? $value->minorUnits : $value;

        return self::percent(self::scaled($minor - $this->minMinor, $this->maxMinor - $this->minMinor, self::FULL));
    }

    /** Sıfır çizgisinin alttan yüzdesi. */
    public function zeroPosition(): string
    {
        return $this->position(0);
    }

    /**
     * Sütun yüksekliği — sıfır çizgisinden üst sınıra kadarki alanın yüzdesi.
     * Konaklama ve tahsilat negatif olamaz.
     */
    public function barHeight(Money $value): string
    {
        return self::percent(self::scaled(max($value->minorUnits, 0), max($this->maxMinor, 1), self::FULL));
    }

    /** Net bakiye çizgisi (SVG `viewBox="0 0 N*100 1000"`) nokta listesi. */
    public function linePoints(): string
    {
        $points = [];

        foreach ($this->months as $index => $month) {
            $fromBottom = self::scaled($month->net->minorUnits - $this->minMinor, $this->maxMinor - $this->minMinor, 1000);
            $points[] = ($index * 100 + 50).','.(1000 - $fromBottom);
        }

        return implode(' ', $points);
    }

    /** Ayın yatay merkezi — soldan yüzde. */
    public function center(int $index): string
    {
        return self::percent(intdiv((2 * $index + 1) * self::FULL, 2 * max(count($this->months), 1)));
    }

    /** Eksen etiketi: ana birim, binlik ayraçlı, kuruşsuz ("300.000"). */
    public function tickLabel(int $minor): string
    {
        return number_format(intdiv($minor, 100), 0, ',', '.');
    }

    private static function niceStep(int $raw): int
    {
        $magnitude = 1;

        while ($magnitude * 10 <= $raw) {
            $magnitude *= 10;
        }

        foreach ([1, 2, 5, 10] as $factor) {
            if ($factor * $magnitude >= $raw) {
                return $factor * $magnitude;
            }
        }

        return 10 * $magnitude;
    }

    /**
     * `value / total × scale` — tamsayı; DECIMAL(15,2) üst sınırına yakın
     * tutarlarda çarpım taşmasın diye önce bölen küçültülür.
     */
    private static function scaled(int $value, int $total, int $scale): int
    {
        if ($value > intdiv(PHP_INT_MAX, $scale)) {
            return intdiv($value, max(intdiv($total, $scale), 1));
        }

        return intdiv($value * $scale, $total);
    }

    private static function ceilDiv(int $value, int $divisor): int
    {
        return intdiv($value + $divisor - 1, $divisor);
    }

    private static function percent(int $basisPoints): string
    {
        $basisPoints = max(0, min(self::FULL, $basisPoints));

        return intdiv($basisPoints, 100).'.'.str_pad((string) ($basisPoints % 100), 2, '0', STR_PAD_LEFT).'%';
    }
}
