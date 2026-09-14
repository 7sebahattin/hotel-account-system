<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\MonthlyAccount\Services\MonthlyAccountConversion;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MonthlyAccountConversionTest extends TestCase
{
    public static function rates(): array
    {
        return [
            'yarım kuruş yukarı' => ['0.01', '0.5000', '0.01'],
            'dört basamaklı kur' => ['300.01', '34.5678', '10370.69'],
            'ara çarpım 64 biti aşsa da sonuç geçerli' => ['9999999999999.99', '1.0000', '9999999999999.99'],
            'büyük tutar ve küçük kur' => ['9999999999999.99', '0.0001', '1000000000.00'],
        ];
    }

    #[Test]
    #[DataProvider('rates')]
    public function kur_kurus_hassasiyetinde_ve_tasmadan_hesaplanir(string $amount, string $rate, string $total): void
    {
        $result = MonthlyAccountConversion::snapshot([
            'currency' => 'USD', 'amount' => $amount,
            'conversion_mode' => 'rate', 'exchange_rate_to_try' => $rate,
        ]);

        $this->assertSame($total, $result['try_equivalent']);
    }

    #[Test]
    public function buyuk_tl_toplamindan_kur_hesabi_tamsayi_sinirini_asmaz(): void
    {
        $result = MonthlyAccountConversion::snapshot([
            'currency' => 'USD', 'amount' => '9999999999999.99',
            'conversion_mode' => 'total', 'try_equivalent' => '9999999999999.99',
        ]);

        $this->assertSame('1.0000', $result['exchange_rate_to_try']);
        $this->assertSame('9999999999999.99', $result['try_equivalent']);
    }

    #[Test]
    public function kurdan_geri_carpim_girilen_tl_toplamini_degistirmez(): void
    {
        $result = MonthlyAccountConversion::snapshot([
            'currency' => 'USD', 'amount' => '300',
            'conversion_mode' => 'total', 'try_equivalent' => '10000',
        ]);

        $this->assertSame('33.3333', $result['exchange_rate_to_try']);
        $this->assertSame('10000.00', $result['try_equivalent']);
    }

    #[Test]
    public function tl_karsiligi_siniri_asilirsa_kayit_icin_sonuc_uretilmez(): void
    {
        $this->expectException(InvalidArgumentException::class);
        MonthlyAccountConversion::snapshot([
            'currency' => 'USD', 'amount' => '9999999999999.99',
            'conversion_mode' => 'rate', 'exchange_rate_to_try' => '2',
        ]);
    }
}
