<?php

declare(strict_types=1);

namespace Tests\Feature\Companies;

use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Companies\Models\Company;
use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
use App\Modules\Ledger\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Firma detayındaki para birimi bazlı bakiye kartları.
 *
 * Bakiye personelden GİZLENMEZ — hiç sorgulanmaz (CLAUDE.md §7.4).
 */
class CompanyBalanceCardsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        $this->company = Company::factory()->create(['name' => 'Monarch']);
        $ledger = app(LedgerService::class);
        $actor = $this->userWithRole(RoleName::Admin);

        $ledger->debit(
            companyId: $this->company->id,
            amount: Money::fromDecimal('9000.00'),
            currency: Currency::TRY,
            entryDate: '2026-09-01',
            description: 'Konaklama',
            sourceType: 'stay',
            sourceId: 1,
            actor: $actor,
        );

        $ledger->debit(
            companyId: $this->company->id,
            amount: Money::fromDecimal('4500.00'),
            currency: Currency::EUR,
            entryDate: '2026-09-02',
            description: 'Konaklama',
            sourceType: 'stay',
            sourceId: 2,
            actor: $actor,
        );
    }

    #[Test]
    public function bakiyeler_para_birimi_bazinda_ayri_kartlarda_gosterilir(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Patron))
            ->get(route('companies.show', $this->company))
            ->assertOk()
            ->assertSee('Cari bakiye')
            ->assertSee('9.000,00 ₺')
            ->assertSee('4.500,00 €')
            ->assertSee('Firma bize borçlu')
            // 13.500 gibi birleşik bir toplam üretilmez (CLAUDE.md §6/L5).
            ->assertDontSee('13.500,00');
    }

    #[Test]
    public function personel_bakiye_kartlarini_gormez_ve_bakiye_sorgulanmaz(): void
    {
        $personel = $this->userWithRole(RoleName::Personel);

        // Yetki önbelleğini ısıt; ölçüm yalnızca ekranın sorgularını görsün.
        $this->actingAs($personel)->get(route('companies.show', $this->company));

        DB::enableQueryLog();
        DB::flushQueryLog();
        $response = $this->actingAs($personel)->get(route('companies.show', $this->company));
        $queries = DB::getRawQueryLog();
        DB::disableQueryLog();

        $response->assertOk()
            ->assertDontSee('Cari bakiye')
            ->assertDontSee('9.000,00 ₺')
            ->assertDontSee('4.500,00 €');

        $ledgerQueries = array_filter(
            $queries,
            static fn (array $query): bool => str_contains((string) $query['raw_query'], 'ledger_entries'),
        );

        $this->assertSame([], $ledgerQueries, 'Personel için cari defter sorgulanmamalı.');
    }

    #[Test]
    public function hareketi_olmayan_firmada_bos_durum_gosterilir(): void
    {
        $bos = Company::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::Patron))
            ->get(route('companies.show', $bos))
            ->assertOk()
            ->assertSee('Bu firmanın cari hareketi yok.');
    }
}
