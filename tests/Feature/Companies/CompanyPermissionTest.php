<?php

declare(strict_types=1);

namespace Tests\Feature\Companies;

use App\Models\User;
use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Companies\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * CLAUDE.md §7.3 / §7.4 — firma yetki davranışları.
 */
class CompanyPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function companyBy(User $user, array $attributes = []): Company
    {
        return Company::factory()->create(array_merge([
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ], $attributes));
    }

    // ---------------------------------------------------------------- Patron

    #[Test]
    public function patron_firma_olusturabilir_duzenleyebilir_silebilir(): void
    {
        $patron = $this->userWithRole(RoleName::Patron);
        $baskasinin = $this->companyBy($this->userWithRole(RoleName::Personel));

        $this->actingAs($patron)->get(route('companies.create'))->assertOk();

        $this->actingAs($patron)
            ->post(route('companies.store'), ['name' => 'Patron Firması'])
            ->assertSessionHasNoErrors();

        // Patron başkasının kaydını da düzenleyebilir; süre kısıtı ona uygulanmaz.
        $this->actingAs($patron)
            ->put(route('companies.update', $baskasinin), ['name' => 'Patron Düzenledi'])
            ->assertRedirect();

        $this->assertSame('Patron Düzenledi', $baskasinin->fresh()->name);

        $this->actingAs($patron)
            ->delete(route('companies.destroy', $baskasinin))
            ->assertRedirect();

        $this->assertSoftDeleted('companies', ['id' => $baskasinin->id]);
    }

    #[Test]
    public function patron_geri_alma_yetkisine_sahip_degildir(): void
    {
        $patron = $this->userWithRole(RoleName::Patron);
        $company = $this->companyBy($patron);
        $company->delete();

        $this->actingAs($patron)
            ->patch(route('companies.restore', $company))
            ->assertForbidden();

        $this->assertSoftDeleted('companies', ['id' => $company->id]);
    }

    #[Test]
    public function patron_silinmis_firma_listesini_goremez(): void
    {
        $patron = $this->userWithRole(RoleName::Patron);
        $this->companyBy($patron, ['name' => 'Silinmiş Kayıt'])->delete();

        $this->actingAs($patron)
            ->get(route('companies.index', ['trashed' => 1]))
            ->assertOk()
            ->assertDontSee('Silinmiş Kayıt');
    }

    // -------------------------------------------------------------- Personel

    #[Test]
    public function personel_firma_olusturabilir(): void
    {
        $personel = $this->userWithRole(RoleName::Personel);

        $this->actingAs($personel)->get(route('companies.create'))->assertOk();

        $this->actingAs($personel)
            ->post(route('companies.store'), ['name' => 'Personel Firması'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('companies', [
            'name' => 'Personel Firması',
            'created_by' => $personel->id,
        ]);
    }

    #[Test]
    public function personel_kendi_kaydini_duzenleyebilir(): void
    {
        $personel = $this->userWithRole(RoleName::Personel);
        $company = $this->companyBy($personel);

        $this->actingAs($personel)->get(route('companies.edit', $company))->assertOk();

        $this->actingAs($personel)
            ->put(route('companies.update', $company), ['name' => 'Kendi Kaydım'])
            ->assertRedirect();

        $this->assertSame('Kendi Kaydım', $company->fresh()->name);
    }

    #[Test]
    public function personel_baskasinin_kaydini_duzenleyemez(): void
    {
        $personel = $this->userWithRole(RoleName::Personel);
        $baskasi = $this->userWithRole(RoleName::Personel);
        $company = $this->companyBy($baskasi, ['name' => 'Başkasının Firması']);

        $this->actingAs($personel)->get(route('companies.edit', $company))->assertForbidden();

        $this->actingAs($personel)
            ->put(route('companies.update', $company), ['name' => 'Ele Geçirildi'])
            ->assertForbidden();

        $this->assertSame('Başkasının Firması', $company->fresh()->name);
    }

    #[Test]
    public function personel_sure_penceresi_dolmus_kendi_kaydini_duzenleyemez(): void
    {
        $personel = $this->userWithRole(RoleName::Personel);
        $saat = (int) config('hotel.personnel_edit_window_hours');

        $company = $this->companyBy($personel);
        $company->forceFill(['created_at' => now()->subHours($saat + 1)])->saveQuietly();

        $this->actingAs($personel)
            ->put(route('companies.update', $company->fresh()), ['name' => 'Geç Kalan Düzeltme'])
            ->assertForbidden();
    }

    #[Test]
    public function personel_sure_penceresi_icinde_kendi_kaydini_duzenleyebilir(): void
    {
        $personel = $this->userWithRole(RoleName::Personel);
        $saat = (int) config('hotel.personnel_edit_window_hours');

        $company = $this->companyBy($personel);
        $company->forceFill(['created_at' => now()->subHours($saat - 1)])->saveQuietly();

        $this->actingAs($personel)
            ->put(route('companies.update', $company->fresh()), ['name' => 'Zamanında Düzeltme'])
            ->assertRedirect();
    }

    #[Test]
    public function personel_firma_silemez(): void
    {
        $personel = $this->userWithRole(RoleName::Personel);
        $company = $this->companyBy($personel);

        $this->actingAs($personel)
            ->delete(route('companies.destroy', $company))
            ->assertForbidden();

        $this->assertNotSoftDeleted('companies', ['id' => $company->id]);
    }

    #[Test]
    public function personel_silinmis_firmayi_goremez(): void
    {
        $personel = $this->userWithRole(RoleName::Personel);
        $company = $this->companyBy($personel, ['name' => 'Gizli Silinmiş']);
        $company->delete();

        $this->actingAs($personel)->get(route('companies.show', $company))->assertForbidden();

        $this->actingAs($personel)
            ->get(route('companies.index', ['trashed' => 1]))
            ->assertOk()
            ->assertDontSee('Gizli Silinmiş');
    }

    #[Test]
    public function personel_geri_alma_yapamaz(): void
    {
        $personel = $this->userWithRole(RoleName::Personel);
        $company = $this->companyBy($personel);
        $company->delete();

        $this->actingAs($personel)
            ->patch(route('companies.restore', $company))
            ->assertForbidden();
    }

    #[Test]
    public function personel_baskasinin_firmasini_goruntuleyebilir(): void
    {
        $personel = $this->userWithRole(RoleName::Personel);
        $company = $this->companyBy($this->userWithRole(RoleName::Patron), ['name' => 'Ortak Firma']);

        $this->actingAs($personel)->get(route('companies.show', $company))->assertOk()->assertSee('Ortak Firma');
    }

    // ------------------------------------------------------------- Yetkisiz

    #[Test]
    public function yetkisiz_kullanici_hicbir_firma_ucuna_erisemez(): void
    {
        $user = $this->userWithRole(RoleName::Personel);
        $user->syncRoles([]);
        $company = Company::factory()->create();

        foreach (['index', 'create'] as $route) {
            $this->actingAs($user->fresh())->get(route("companies.{$route}"))->assertForbidden();
        }

        $this->actingAs($user->fresh())->get(route('companies.show', $company))->assertForbidden();
        $this->actingAs($user->fresh())->delete(route('companies.destroy', $company))->assertForbidden();
    }

    #[Test]
    public function misafir_kullanici_giris_e_yonlendirilir(): void
    {
        $company = Company::factory()->create();

        $this->get(route('companies.index'))->assertRedirect(route('login'));
        $this->get(route('companies.show', $company))->assertRedirect(route('login'));
    }
}
