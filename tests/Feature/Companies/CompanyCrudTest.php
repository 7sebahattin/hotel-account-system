<?php

declare(strict_types=1);

namespace Tests\Feature\Companies;

use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Companies\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CompanyCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ilgaz Turizm A.Ş.',
            'tax_office' => 'Kadıköy',
            'tax_number' => '1234567890',
            'phone' => '0216 555 44 33',
            'email' => 'info@ilgaz.example',
            'address' => 'Bağdat Caddesi No:1',
            'city' => 'İstanbul',
            'contact_person' => 'Ayşe Yılmaz',
            'notes' => 'Acente anlaşması var.',
            'is_active' => '1',
        ], $overrides);
    }

    #[Test]
    public function admin_firma_olusturur(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $this->actingAs($admin)
            ->post(route('companies.store'), $this->payload())
            ->assertRedirect();

        $company = Company::query()->firstOrFail();

        $this->assertSame('Ilgaz Turizm A.Ş.', $company->name);
        $this->assertSame('İstanbul', $company->city);
        $this->assertTrue($company->is_active);
        $this->assertSame($admin->id, $company->created_by);
        $this->assertSame($admin->id, $company->updated_by);
    }

    #[Test]
    public function unvan_zorunludur_diger_alanlar_serbesttir(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $this->actingAs($admin)
            ->post(route('companies.store'), ['name' => ''])
            ->assertSessionHasErrors('name');

        $this->actingAs($admin)
            ->post(route('companies.store'), ['name' => 'Yalnızca Ünvan'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('companies', ['name' => 'Yalnızca Ünvan', 'tax_number' => null]);
    }

    #[Test]
    public function gecersiz_eposta_reddedilir(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $this->actingAs($admin)
            ->post(route('companies.store'), $this->payload(['email' => 'gecersiz']))
            ->assertSessionHasErrors('email');
    }

    #[Test]
    public function admin_firmayi_gunceller(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $company = Company::factory()->create(['name' => 'Eski Ünvan']);

        $this->actingAs($admin)
            ->put(route('companies.update', $company), $this->payload(['name' => 'Yeni Ünvan']))
            ->assertRedirect(route('companies.show', $company));

        $this->assertSame('Yeni Ünvan', $company->fresh()->name);
        $this->assertSame($admin->id, $company->fresh()->updated_by);
    }

    #[Test]
    public function pasif_firma_kaydedilebilir_ve_listede_isaretlenir(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $this->actingAs($admin)->post(route('companies.store'), $this->payload(['is_active' => '0']));

        $company = Company::query()->firstOrFail();
        $this->assertFalse($company->is_active);

        $this->actingAs($admin)->get(route('companies.index'))->assertOk()->assertSee('Pasif');
    }

    #[Test]
    public function silme_soft_delete_uygular_hard_delete_yapmaz(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $company = Company::factory()->create();

        $this->actingAs($admin)
            ->delete(route('companies.destroy', $company))
            ->assertRedirect(route('companies.index'));

        $this->assertSoftDeleted('companies', ['id' => $company->id]);
        $this->assertSame(1, Company::withTrashed()->count(), 'Kayıt fiziksel olarak silinmemeli');
    }

    #[Test]
    public function admin_silinmis_firmayi_geri_alir(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $company = Company::factory()->create();
        $company->delete();

        $this->actingAs($admin)
            ->patch(route('companies.restore', $company))
            ->assertRedirect(route('companies.show', $company));

        $this->assertNotSoftDeleted('companies', ['id' => $company->id]);
    }

    #[Test]
    public function silinmis_firma_varsayilan_listede_gorunmez(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        Company::factory()->create(['name' => 'Görünen Firma']);
        Company::factory()->create(['name' => 'Silinen Firma'])->delete();

        $this->actingAs($admin)->get(route('companies.index'))
            ->assertSee('Görünen Firma')
            ->assertDontSee('Silinen Firma');

        $this->actingAs($admin)->get(route('companies.index', ['trashed' => 1]))
            ->assertSee('Silinen Firma')
            ->assertDontSee('Görünen Firma');
    }

    #[Test]
    public function silinmis_firma_duzenlenemez(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $company = Company::factory()->create();
        $company->delete();

        $this->actingAs($admin)->get(route('companies.edit', $company))->assertNotFound();
    }
}
