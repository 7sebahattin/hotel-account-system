<?php

declare(strict_types=1);

namespace Tests\Unit\MonthlyAccount;

use App\Modules\MonthlyAccount\Models\MonthlyAccountItem;
use App\Modules\MonthlyAccount\Models\MonthlyAccountRevision;
use App\Modules\MonthlyAccount\Services\MonthlyAccountSummary;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MonthlyAccountSummaryTest extends TestCase
{
    /** @param array<int, array<string, string|null>> $items */
    private function revision(string $dailyTotal, array $items): MonthlyAccountRevision
    {
        $revision = new MonthlyAccountRevision(['daily_total' => $dailyTotal]);
        $revision->setRelation('items', new Collection(array_map(
            fn (array $item) => new MonthlyAccountItem(array_replace(['method' => 'cash', 'currency' => 'TRY', 'bank_name' => null], $item)),
            $items,
        )));

        return $revision;
    }

    #[Test]
    public function yontem_banka_ve_para_birimleri_ayri_toplanir_tl_karsiliklari_ayrica_toplanir(): void
    {
        $summary = MonthlyAccountSummary::fromRevisions([
            $this->revision('15000.00', [
                ['method' => 'credit_card', 'bank_name' => 'Yapı Kredi', 'currency' => 'TRY', 'amount' => '3000.00', 'try_equivalent' => '3000.00'],
                ['method' => 'credit_card', 'bank_name' => 'Yapı Kredi', 'currency' => 'USD', 'amount' => '300.00', 'try_equivalent' => '10000.00'],
                ['method' => 'bank_transfer', 'bank_name' => 'Yapı Kredi', 'currency' => 'USD', 'amount' => '20.00', 'try_equivalent' => '680.00'],
            ]),
            $this->revision('5000.00', [
                ['method' => 'credit_card', 'bank_name' => 'Yapı Kredi', 'currency' => 'USD', 'amount' => '100.00', 'try_equivalent' => '3400.00'],
                ['method' => 'credit_card', 'bank_name' => 'İş Bankası', 'currency' => 'USD', 'amount' => '50.00', 'try_equivalent' => '1700.00'],
                ['method' => 'cash', 'currency' => 'EUR', 'amount' => '10.01', 'try_equivalent' => '385.39'],
                ['method' => 'extra', 'currency' => 'TRY', 'amount' => '0.01', 'try_equivalent' => '0.01'],
            ]),
        ]);

        $this->assertSame('3000.00', $summary['methods']['credit_card']['TRY']['amount']->toDecimal());
        $this->assertSame('450.00', $summary['methods']['credit_card']['USD']['amount']->toDecimal());
        $this->assertSame('15100.00', $summary['methods']['credit_card']['USD']['try_equivalent']->toDecimal());
        $this->assertSame('400.00', $summary['banks']['credit_card']['Yapı Kredi']['USD']['amount']->toDecimal());
        $this->assertSame('50.00', $summary['banks']['credit_card']['İş Bankası']['USD']['amount']->toDecimal());
        $this->assertSame('20.00', $summary['banks']['bank_transfer']['Yapı Kredi']['USD']['amount']->toDecimal());
        $this->assertSame('20000.00', $summary['daily_total']->toDecimal());
        $this->assertSame('19165.40', $summary['receipt_total']->toDecimal());
    }

    #[Test]
    public function yalniz_liste_tutari_odeme_toplamina_eklenmez(): void
    {
        $summary = MonthlyAccountSummary::fromRevisions([$this->revision('3000.00', [])]);

        $this->assertSame('3000.00', $summary['daily_total']->toDecimal());
        $this->assertSame('0.00', $summary['receipt_total']->toDecimal());
    }

    #[Test]
    public function bos_ay_sifir_toplam_verir(): void
    {
        $summary = MonthlyAccountSummary::fromRevisions([]);

        $this->assertSame('0.00', $summary['daily_total']->toDecimal());
        $this->assertSame('0.00', $summary['receipt_total']->toDecimal());
    }
}
