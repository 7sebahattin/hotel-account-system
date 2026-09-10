<?php

declare(strict_types=1);

namespace App\Modules\Core\Support;

use App\Modules\Core\Enums\Currency;
use InvalidArgumentException;
use Stringable;

/**
 * Para tutarı — tamsayı KURUŞ olarak tutulur (CLAUDE.md §13).
 *
 * Hiçbir işlemde kayan nokta aritmetiği kullanılmaz; biçimlendirme bile tam
 * sayı bölme ile yapılır. Veritabanında alan DECIMAL(15,2) olarak kalır,
 * dönüşüm toDecimal() / fromDecimal() üzerinden yapılır.
 *
 * `bcmath` gerektirmez: DECIMAL(15,2)'nin azami değeri kuruş cinsinden
 * 999.999.999.999.999'dur ve 64 bit tam sayıya rahatça sığar.
 */
final readonly class Money implements Stringable
{
    /** DECIMAL(15,2) sınırının kuruş karşılığı. */
    public const MAX_MINOR_UNITS = 999_999_999_999_999;

    private function __construct(public int $minorUnits) {}

    public static function fromMinorUnits(int $minorUnits): self
    {
        if (abs($minorUnits) > self::MAX_MINOR_UNITS) {
            throw new InvalidArgumentException('Tutar DECIMAL(15,2) sınırını aşıyor.');
        }

        return new self($minorUnits);
    }

    /**
     * Ondalık metinden üretir: '1000', '1000.5', '1000.50', '-12.34'.
     *
     * Girdi metin olarak ayrıştırılır; float'a çevrilmez.
     */
    public static function fromDecimal(string|int $amount): self
    {
        $value = trim((string) $amount);

        if (! preg_match('/^(?<sign>[+-])?(?<whole>\d+)(?:\.(?<fraction>\d{1,2}))?$/', $value, $m)) {
            throw new InvalidArgumentException(
                "Geçersiz tutar biçimi: '{$value}'. Beklenen: en fazla iki ondalık basamaklı sayı."
            );
        }

        $minor = (int) $m['whole'] * 100 + (int) str_pad($m['fraction'] ?? '0', 2, '0');

        return self::fromMinorUnits($m['sign'] === '-' ? -$minor : $minor);
    }

    /**
     * Kullanıcı girdisini `fromDecimal()`'in beklediği biçime çevirir.
     *
     * Türkçe klavyede ondalık ayırıcı virgüldür ve uygulama tutarları
     * `5.000,50` biçiminde GÖSTERİR; kullanıcı aynı biçimi geri yazabilmelidir.
     *
     * Nokta yalnızca virgül de varsa binlik ayırıcı sayılıp atılır. Aksi hâlde
     * nokta ondalık ayırıcı kabul edilir: `5.50` yazan bir kullanıcının
     * beş buçuk lirası sessizce 550 liraya dönüşmemelidir. Bu yüzden virgülsüz
     * `5.000` bilinçli olarak ÇÖZÜMLENMEZ — doğrulama reddeder ve kullanıcı
     * düzeltir; tahmin etmek para tutarında kabul edilemez.
     */
    public static function normalizeInput(string $value): string
    {
        $value = str_replace(' ', '', trim($value));

        if (str_contains($value, ',')) {
            $value = str_replace('.', '', $value);
        }

        return str_replace(',', '.', $value);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    /** Veritabanına yazılacak biçim: her zaman iki ondalık basamak. */
    public function toDecimal(): string
    {
        $sign = $this->minorUnits < 0 ? '-' : '';
        $absolute = abs($this->minorUnits);

        return sprintf('%s%d.%02d', $sign, intdiv($absolute, 100), $absolute % 100);
    }

    public function add(self $other): self
    {
        return self::fromMinorUnits($this->minorUnits + $other->minorUnits);
    }

    public function subtract(self $other): self
    {
        return self::fromMinorUnits($this->minorUnits - $other->minorUnits);
    }

    /**
     * Tam sayı çarpanla çarpar — gecelik ücret × gece sayısı gibi.
     *
     * Kuruş cinsinden tam sayı çarpımıdır; kayan nokta hatası oluşmaz.
     */
    public function multiply(int $factor): self
    {
        if ($factor !== 0 && abs($this->minorUnits) > intdiv(self::MAX_MINOR_UNITS, abs($factor))) {
            throw new InvalidArgumentException('Tutar DECIMAL(15,2) sınırını aşıyor.');
        }

        return self::fromMinorUnits($this->minorUnits * $factor);
    }

    /**
     * Bir kur oranıyla çarpar — yabancı para biriminin TL karşılığını
     * hesaplamak içindir (v2.1.1-C §2).
     *
     * Oran metin olarak ayrıştırılır (en fazla dört ondalık basamak);
     * kayan nokta ve `bcmath` KULLANILMAZ — oran 10.000 ile ölçeklenip tam
     * sayı olarak çarpılır, sonuç en yakın kuruşa YARIM YUKARI yuvarlanır.
     * Taşma riskine karşı çarpımdan ÖNCE sınır kontrolü yapılır.
     */
    public function multiplyByRate(string $rate): self
    {
        $rate = trim($rate);

        if (! preg_match('/^(?<whole>\d+)(?:\.(?<fraction>\d{1,4}))?$/', $rate, $m)) {
            throw new InvalidArgumentException(
                "Geçersiz kur biçimi: '{$rate}'. Beklenen: en fazla dört ondalık basamaklı pozitif sayı."
            );
        }

        $scaledRate = (int) $m['whole'] * 10000 + (int) str_pad($m['fraction'] ?? '0', 4, '0');

        if ($scaledRate === 0) {
            return self::zero();
        }

        if ($this->minorUnits !== 0 && abs($this->minorUnits) > intdiv(PHP_INT_MAX, $scaledRate)) {
            throw new InvalidArgumentException('Kur hesaplaması taşma sınırını aşıyor.');
        }

        $product = $this->minorUnits * $scaledRate;
        $sign = $product < 0 ? -1 : 1;
        $absolute = abs($product);

        // Yarım yukarı yuvarlama: 10.000 ölçeğinde tam sayı bölme.
        $rounded = intdiv($absolute + 5000, 10000);

        return self::fromMinorUnits($sign * $rounded);
    }

    public function negate(): self
    {
        return new self(-$this->minorUnits);
    }

    public function isZero(): bool
    {
        return $this->minorUnits === 0;
    }

    public function isPositive(): bool
    {
        return $this->minorUnits > 0;
    }

    public function isNegative(): bool
    {
        return $this->minorUnits < 0;
    }

    public function equals(self $other): bool
    {
        return $this->minorUnits === $other->minorUnits;
    }

    public function compareTo(self $other): int
    {
        return $this->minorUnits <=> $other->minorUnits;
    }

    /** Türkçe gösterim: 1.234,56 ₺ — kayan nokta kullanılmaz. */
    public function format(Currency $currency): string
    {
        $sign = $this->minorUnits < 0 ? '-' : '';
        $absolute = abs($this->minorUnits);

        $whole = (string) intdiv($absolute, 100);
        $grouped = strrev(implode('.', str_split(strrev($whole), 3)));

        return sprintf('%s%s,%02d %s', $sign, $grouped, $absolute % 100, $currency->symbol());
    }

    public function __toString(): string
    {
        return $this->toDecimal();
    }
}
