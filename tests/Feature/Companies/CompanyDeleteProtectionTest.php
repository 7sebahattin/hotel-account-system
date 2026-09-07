<?php

declare(strict_types=1);

namespace Tests\Feature\Companies;

use App\Models\User;
use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Companies\Exceptions\CompanyHasLedgerEntries;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Services\CompanyService;
use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
use App\Modules\Ledger\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * CLAUDE.md §5.2 — cari hareketi olan firma silinemez, Admin dahil.
 */
class CompanyDeleteProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function withEntry(Company $company, User $actor): void
    {
        app(LedgerService::class)->debit(
            $company->id, Money::fromDecimal('100.00'), Currency::TRY,
            now(), 'Hareket', actor: $actor,
        );
    }

    #[Test]
    public function hareketi_olan_firma_servis_katmaninda_silinemez(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $company = Company::factory()->create(['name' => 'Hareketli Firma']);
        $this->withEntry($company, $admin);

        try {
            app(CompanyService::class)->delete($company);
            $this->fail('CompanyHasLedgerEntries bekleniyordu');
        } catch (CompanyHasLedgerEntries $e) {
            $this->assertStringContainsString('Hareketli Firma', $e->getMessage());
            $this->assertStringContainsString('pasif', $e->getMessage());
        }

        $this->assertNotSoftDeleted('companies', ['id' => $company->id]);
    }

    #[Test]
    public function admin_bile_hareketi_olan_firmayi_http_uzerinden_silemez(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $company = Company::factory()->create();
        $this->withEntry($company, $admin);

        $this->actingAs($admin)
            ->delete(route('companies.destroy', $company))
            ->assertForbidden();

        $this->assertNotSoftDeleted('companies', ['id' => $company->id]);
    }

    #[Test]
    public function patron_da_hareketi_olan_firmayi_silemez(): void
    {
        $patron = $this->userWithRole(RoleName::Patron);
        $company = Company::factory()->create();
        $this->withEntry($company, $patron);

        $this->actingAs($patron)
            ->delete(route('companies.destroy', $company))
            ->assertForbidden();

        $this->assertNotSoftDeleted('companies', ['id' => $company->id]);
    }

    #[Test]
    public function hareketi_olmayan_firma_normal_kurallarla_silinebilir(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $company = Company::factory()->create();

        $this->actingAs($admin)
            ->delete(route('companies.destroy', $company))
            ->assertRedirect(route('companies.index'));

        $this->assertSoftDeleted('companies', ['id' => $company->id]);
    }

    #[Test]
    public function ters_kayitla_bakiye_sifirlansa_bile_firma_silinemez(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $company = Company::factory()->create();
        $ledger = app(LedgerService::class);

        $entry = $ledger->debit(
            $company->id, Money::fromDecimal('100.00'), Currency::TRY,
            now(), 'Hareket', actor: $admin,
        );
        $ledger->reverse($entry, actor: $admin);

        $this->assertSame('0.00', $ledger->balanceForCompany($company->id, Currency::TRY)->toDecimal());

        // Bakiye sıfır ama hareket geçmişi var; silme yine yasaktır.
        $this->expectException(CompanyHasLedgerEntries::class);
        app(CompanyService::class)->delete($company);
    }

    #[Test]
    public function silme_butonu_hareketi_olan_firmada_gosterilmez(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $company = Company::factory()->create();

        $this->actingAs($admin)->get(route('companies.show', $company))->assertSee('Sil');

        $this->withEntry($company, $admin);

        $this->actingAs($admin)->get(route('companies.show', $company))->assertDontSee('>Sil<', false);
    }

    #[Test]
    public function hareketi_olan_firma_pasife_alinabilir(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $company = Company::factory()->create(['is_active' => true]);
        $this->withEntry($company, $admin);

        $this->actingAs($admin)->put(route('companies.update', $company), [
            'name' => $company->name,
            'is_active' => '0',
        ])->assertRedirect();

        $this->assertFalse($company->fresh()->is_active);
    }
}
