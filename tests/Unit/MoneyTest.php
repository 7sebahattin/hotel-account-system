<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    #[Test]
    #[DataProvider('decimalStrings')]
    public function ondalik_metinden_dogru_kurus_uretir(string $input, int $expectedMinor, string $expectedDecimal): void
    {
        $money = Money::fromDecimal($input);

        $this->assertSame($expectedMinor, $money->minorUnits);
        $this->assertSame($expectedDecimal, $money->toDecimal());
    }

    /** @return array<string, array{string, int, string}> */
    public static function decimalStrings(): array
    {
        return [
            'tam sayı' => ['1000', 100_000, '1000.00'],
            'iki ondalık' => ['1000.00', 100_000, '1000.00'],
            'tek ondalık' => ['1000.5', 100_050, '1000.50'],
            'kuruş' => ['0.01', 1, '0.01'],
            'sıfır' => ['0', 0, '0.00'],
            'negatif' => ['-42.75', -4_275, '-42.75'],
            'artı işaretli' => ['+3.20', 320, '3.20'],
            'büyük tutar' => ['9999999999999.99', 999_999_999_999_999, '9999999999999.99'],
        ];
    }

    #[Test]
    #[DataProvider('invalidStrings')]
    public function gecersiz_bicimi_reddeder(string $input): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromDecimal($input);
    }

    /** @return array<string, array{string}> */
    public static function invalidStrings(): array
    {
        return [
            'üç ondalık' => ['1.234'],
            'boş' => [''],
            'harf' => ['abc'],
            'virgüllü' => ['1,50'],
            'bilimsel' => ['1e3'],
            'iki nokta' => ['1.2.3'],
            'yalnız nokta' => ['.5'],
        ];
    }

    #[Test]
    public function sinir_disi_tutar_reddedilir(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromMinorUnits(Money::MAX_MINOR_UNITS + 1);
    }

    #[Test]
    public function toplama_ve_cikarma_kesindir(): void
    {
        $a = Money::fromDecimal('0.10');
        $b = Money::fromDecimal('0.20');

        // Kayan noktada 0.1 + 0.2 = 0.30000000000000004 olurdu.
        $this->assertSame('0.30', $a->add($b)->toDecimal());
        $this->assertSame('-0.10', $a->subtract($b)->toDecimal());
    }

    #[Test]
    public function ucyuz_kez_ondalik_toplama_sapma_uretmez(): void
    {
        $total = Money::zero();

        for ($i = 0; $i < 300; $i++) {
            $total = $total->add(Money::fromDecimal('0.01'));
        }

        $this->assertSame('3.00', $total->toDecimal());
    }

    #[Test]
    public function isaret_kontrolleri(): void
    {
        $this->assertTrue(Money::fromDecimal('1.00')->isPositive());
        $this->assertTrue(Money::fromDecimal('-1.00')->isNegative());
        $this->assertTrue(Money::zero()->isZero());
        $this->assertFalse(Money::zero()->isPositive());
    }

    #[Test]
    public function karsilastirma_ve_esitlik(): void
    {
        $this->assertSame(0, Money::fromDecimal('5.00')->compareTo(Money::fromDecimal('5')));
        $this->assertSame(1, Money::fromDecimal('5.01')->compareTo(Money::fromDecimal('5.00')));
        $this->assertSame(-1, Money::fromDecimal('4.99')->compareTo(Money::fromDecimal('5.00')));
        $this->assertTrue(Money::fromDecimal('5.00')->equals(Money::fromMinorUnits(500)));
    }

    #[Test]
    public function turkce_bicimlendirme(): void
    {
        $this->assertSame('1.234.567,05 ₺', Money::fromDecimal('1234567.05')->format(Currency::TRY));
        $this->assertSame('0,00 €', Money::zero()->format(Currency::EUR));
        $this->assertSame('-42,70 £', Money::fromDecimal('-42.7')->format(Currency::GBP));
        $this->assertSame('999,99 ₺', Money::fromDecimal('999.99')->format(Currency::TRY));
        $this->assertSame('1.000,00 ₺', Money::fromDecimal('1000')->format(Currency::TRY));
    }

    #[Test]
    public function negate_isareti_ters_cevirir(): void
    {
        $this->assertSame('-15.25', Money::fromDecimal('15.25')->negate()->toDecimal());
        $this->assertSame('15.25', Money::fromDecimal('-15.25')->negate()->toDecimal());
        $this->assertSame('0.00', Money::zero()->negate()->toDecimal());
    }
}
