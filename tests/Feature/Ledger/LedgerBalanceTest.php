<?php

declare(strict_types=1);

namespace Tests\Feature\Ledger;

use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
use App\Modules\Ledger\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * CLAUDE.md §6/L4, L5 — bakiye = SUM(debit) - SUM(credit), para birimi bazında.
 */
class LedgerBalanceTest extends TestCase
{
    use RefreshDatabase;

    private LedgerService $ledger;

    private Company $company;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledger = app(LedgerService::class);
        $this->company = Company::factory()->create();
        $this->actor = User::factory()->create();
    }

    private function debit(string $amount, Currency $currency = Currency::TRY): void
    {
        $this->ledger->debit(
            $this->company->id, Money::fromDecimal($amount), $currency,
            now(), 'Borç', actor: $this->actor,
        );
    }

    private function credit(string $amount, Currency $currency = Currency::TRY): void
    {
        $this->ledger->credit(
            $this->company->id, Money::fromDecimal($amount), $currency,
            now(), 'Alacak', actor: $this->actor,
        );
    }

    private function balance(Currency $currency = Currency::TRY): string
    {
        return $this->ledger->balanceForCompany($this->company->id, $currency)->toDecimal();
    }

    #[Test]
    public function hareketi_olmayan_firmanin_bakiyesi_sifirdir(): void
    {
        $this->assertSame('0.00', $this->balance());
        $this->assertSame([], $this->ledger->balancesForCompanyByCurrency($this->company->id));
        $this->assertFalse($this->ledger->hasEntriesForCompany($this->company->id));
    }

    #[Test]
    public function borc_bakiyeyi_artirir(): void
    {
        $this->debit('1000.00');

        $this->assertSame('1000.00', $this->balance());
        $this->assertTrue($this->ledger->hasEntriesForCompany($this->company->id));
    }

    #[Test]
    public function alacak_bakiyeyi_azaltir(): void
    {
        $this->debit('1000.00');
        $this->credit('300.00');

        $this->assertSame('700.00', $this->balance());
    }

    #[Test]
    public function bakiye_kurus_hassasiyetinde_dogrudur(): void
    {
        foreach (['0.01', '0.02', '10.33', '99.99'] as $amount) {
            $this->debit($amount);
        }
        $this->credit('0.35');

        // 0.01 + 0.02 + 10.33 + 99.99 - 0.35 = 110.00
        $this->assertSame('110.00', $this->balance());
    }

    #[Test]
    public function alacak_borctan_fazlaysa_bakiye_negatiftir(): void
    {
        $this->debit('100.00');
        $this->credit('250.00');

        $this->assertSame('-150.00', $this->balance());
        $this->assertTrue($this->ledger->balanceForCompany($this->company->id, Currency::TRY)->isNegative());
    }

    #[Test]
    public function para_birimleri_ayri_hesaplanir_ve_toplanmaz(): void
    {
        $this->debit('1000.00', Currency::TRY);
        $this->credit('300.00', Currency::TRY);

        $this->debit('500.00', Currency::EUR);
        $this->credit('200.00', Currency::EUR);

        $this->debit('80.00', Currency::GBP);

        $this->assertSame('700.00', $this->balance(Currency::TRY));
        $this->assertSame('300.00', $this->balance(Currency::EUR));
        $this->assertSame('80.00', $this->balance(Currency::GBP));

        $byCurrency = $this->ledger->balancesForCompanyByCurrency($this->company->id);

        $this->assertSame(['EUR', 'GBP', 'TRY'], array_keys($byCurrency));
        $this->assertSame('700.00', $byCurrency['TRY']->toDecimal());
        $this->assertSame('300.00', $byCurrency['EUR']->toDecimal());
        $this->assertSame('80.00', $byCurrency['GBP']->toDecimal());

        // Tek toplam diye bir kavram YOKTUR: 700 + 300 + 80 = 1080 gösterilemez.
        $this->assertCount(3, $byCurrency);
    }

    #[Test]
    public function bir_para_biriminin_bakiyesi_digerinden_etkilenmez(): void
    {
        $this->debit('1000.00', Currency::TRY);
        $this->debit('5000.00', Currency::EUR);

        $this->assertSame('1000.00', $this->balance(Currency::TRY));
        $this->assertSame('0.00', $this->balance(Currency::GBP));
    }

    #[Test]
    public function bakiye_yalnizca_ilgili_firmayi_kapsar(): void
    {
        $digerFirma = Company::factory()->create();

        $this->debit('1000.00');
        $this->ledger->debit(
            $digerFirma->id, Money::fromDecimal('9999.00'), Currency::TRY,
            now(), 'Başka firma', actor: $this->actor,
        );

        $this->assertSame('1000.00', $this->balance());
        $this->assertSame('9999.00', $this->ledger->balanceForCompany($digerFirma->id, Currency::TRY)->toDecimal());
    }

    #[Test]
    public function bakiye_defterden_hesaplanir_onbellek_kolonu_yoktur(): void
    {
        $this->assertFalse(
            Schema::hasColumn('companies', 'current_balance'),
            'Bakiye companies tablosunda tutulmaz; tek doğruluk kaynağı ledger_entries.',
        );
        $this->assertFalse(Schema::hasColumn('companies', 'balance'));
    }

    #[Test]
    public function buyuk_tutarlarda_toplam_kesindir(): void
    {
        $this->debit('9999999999.99');
        $this->debit('0.01');

        $this->assertSame('10000000000.00', $this->balance());
    }
}
