<?php

declare(strict_types=1);

namespace Tests\Feature\Stays;

use App\Models\User;
use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Companies\Models\Company;
use App\Modules\Core\Enums\Currency;
use App\Modules\Ledger\Models\LedgerEntry;
use App\Modules\Ledger\Services\LedgerService;
use App\Modules\Stays\Models\Stay;
use App\Modules\Stays\Services\StayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * CLAUDE.md §7.3 / §7.4 — konaklama yetki davranışları.
 */
class StayPermissionTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->company = Company::factory()->create();
    }

    private function stayBy(User $user, array $overrides = []): Stay
    {
        return app(StayService::class)->create(array_merge([
            'company_id' => $this->company->id,
            'guest_name' => 'John Smith',
            'check_in_date' => '2026-09-07',
            'check_out_date' => '2026-09-10',
            'amount' => '9000.00',
            'currency' => 'TRY',
        ], $overrides), $user);
    }

    /** @return array<string, mixed> */
    private function updatePayload(array $overrides = []): array
    {
        return array_merge([
            'company_id' => $this->company->id,
            'guest_name' => 'John Smith',
            'check_in_date' => '2026-09-07',
            'check_out_date' => '2026-09-10',
            'amount' => '9000.00',
            'currency' => 'TRY',
        ], $overrides);
    }

    // ----------------------------------------------------------------- Admin

    #[Test]
    public function admin_tum_islemleri_yapabilir(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $stay = $this->stayBy($this->userWithRole(RoleName::Personel));

        $this->actingAs($admin)->get(route('stays.index'))->assertOk();
        $this->actingAs($admin)->get(route('stays.create'))->assertOk();
        $this->actingAs($admin)->get(route('stays.show', $stay))->assertOk();
        $this->actingAs($admin)->get(route('stays.edit', $stay))->assertOk();
        $this->actingAs($admin)->put(route('stays.update', $stay), $this->updatePayload(['guest_name' => 'Admin Düzenledi']))->assertRedirect();
        $this->actingAs($admin)->patch(route('stays.cancel', $stay->fresh()))->assertRedirect();
    }

    // ---------------------------------------------------------------- Patron

    #[Test]
    public function patron_tum_islemleri_yapabilir(): void
    {
        $patron = $this->userWithRole(RoleName::Patron);
        $stay = $this->stayBy($this->userWithRole(RoleName::Personel));

        $this->actingAs($patron)->get(route('stays.index'))->assertOk();
        $this->actingAs($patron)->get(route('stays.create'))->assertOk();
        $this->actingAs($patron)->put(route('stays.update', $stay), $this->updatePayload(['guest_name' => 'Patron Düzenledi']))->assertRedirect();

        $this->assertSame('Patron Düzenledi', $stay->fresh()->guest_name);

        $this->actingAs($patron)->patch(route('stays.cancel', $stay->fresh()))->assertRedirect();
    }

    // -------------------------------------------------------------- Personel

    #[Test]
    public function personel_konaklama_olusturabilir_ve_gorebilir(): void
    {
        $personel = $this->userWithRole(RoleName::Personel);

        $this->actingAs($personel)->get(route('stays.index'))->assertOk();
        $this->actingAs($personel)->get(route('stays.create'))->assertOk();

        $this->actingAs($personel)
            ->post(route('stays.store'), $this->updatePayload(['guest_name' => 'Personel Kaydı']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('stays', ['guest_name' => 'Personel Kaydı', 'created_by' => $personel->id]);
        $this->assertSame(1, LedgerEntry::query()->count(), 'Personel kaydı da cari hareketi üretir');
    }

    #[Test]
    public function personel_kendi_kaydini_sure_icinde_duzenleyebilir(): void
    {
        $personel = $this->userWithRole(RoleName::Personel);
        $stay = $this->stayBy($personel);

        $this->actingAs($personel)->get(route('stays.edit', $stay))->assertOk();

        $this->actingAs($personel)
            ->put(route('stays.update', $stay), $this->updatePayload(['guest_name' => 'Kendi Kaydım']))
            ->assertRedirect();

        $this->assertSame('Kendi Kaydım', $stay->fresh()->guest_name);
    }

    /**
     * Personel kendi kaydının FİNANSAL alanlarını da değiştirebilir; bu
     * değişiklik ters kayıt + yeni hareket üretir. Kısıt frontend'de değil
     * servis/policy katmanındadır.
     */
    #[Test]
    public function personel_kendi_kaydinin_finansal_alanlarini_degistirebilir_ve_ters_kayit_olusur(): void
    {
        $personel = $this->userWithRole(RoleName::Personel);
        $stay = $this->stayBy($personel);

        $this->actingAs($personel)
            ->put(route('stays.update', $stay), $this->updatePayload(['amount' => '9500,00']))
            ->assertRedirect();

        $this->assertSame('9500.00', $stay->fresh()->amount);
        $this->assertSame(3, LedgerEntry::query()->count());
        $this->assertSame(
            '9500.00',
            app(LedgerService::class)->balanceForCompany($this->company->id, Currency::TRY)->toDecimal(),
        );
    }

    #[Test]
    public function personel_baskasinin_kaydini_duzenleyemez(): void
    {
        $personel = $this->userWithRole(RoleName::Personel);
        $stay = $this->stayBy($this->userWithRole(RoleName::Personel), ['guest_name' => 'Başkasının Kaydı']);

        $this->actingAs($personel)->get(route('stays.edit', $stay))->assertForbidden();

        $this->actingAs($personel)
            ->put(route('stays.update', $stay), $this->updatePayload(['guest_name' => 'Ele Geçirildi', 'amount' => '1,00']))
            ->assertForbidden();

        $this->assertSame('Başkasının Kaydı', $stay->fresh()->guest_name);
        $this->assertSame('9000.00', $stay->fresh()->amount);
        $this->assertSame(1, LedgerEntry::query()->count());
    }

    #[Test]
    public function personel_sure_penceresi_dolmus_kendi_kaydini_duzenleyemez(): void
    {
        $personel = $this->userWithRole(RoleName::Personel);
        $stay = $this->stayBy($personel);
        $saat = (int) config('hotel.personnel_edit_window_hours');

        $stay->forceFill(['created_at' => now()->subHours($saat + 1)])->saveQuietly();

        $this->actingAs($personel)
            ->put(route('stays.update', $stay->fresh()), $this->updatePayload(['amount' => '9500,00']))
            ->assertForbidden();

        $this->assertSame('9000.00', $stay->fresh()->amount);
    }

    #[Test]
    public function personel_konaklama_iptal_edemez(): void
    {
        $personel = $this->userWithRole(RoleName::Personel);
        $stay = $this->stayBy($personel);

        $this->actingAs($personel)
            ->patch(route('stays.cancel', $stay))
            ->assertForbidden();

        $this->assertTrue($stay->fresh()->isActive());
        $this->assertSame(1, LedgerEntry::query()->count());
    }

    #[Test]
    public function personel_baskasinin_kaydini_goruntuleyebilir(): void
    {
        $personel = $this->userWithRole(RoleName::Personel);
        $stay = $this->stayBy($this->userWithRole(RoleName::Patron), ['guest_name' => 'Ortak Kayıt']);

        $this->actingAs($personel)->get(route('stays.show', $stay))->assertOk()->assertSee('Ortak Kayıt');
    }

    // ------------------------------------------------------------- Yetkisiz

    #[Test]
    public function yetkisiz_kullanici_hicbir_konaklama_ucuna_erisemez(): void
    {
        $user = $this->userWithRole(RoleName::Personel);
        $stay = $this->stayBy($user);
        $user->syncRoles([]);

        $this->actingAs($user->fresh())->get(route('stays.index'))->assertForbidden();
        $this->actingAs($user->fresh())->get(route('stays.create'))->assertForbidden();
        $this->actingAs($user->fresh())->get(route('stays.show', $stay))->assertForbidden();
        $this->actingAs($user->fresh())->patch(route('stays.cancel', $stay))->assertForbidden();
    }

    #[Test]
    public function misafir_kullanici_giris_e_yonlendirilir(): void
    {
        $stay = $this->stayBy($this->userWithRole(RoleName::Admin));

        $this->get(route('stays.index'))->assertRedirect(route('login'));
        $this->get(route('stays.show', $stay))->assertRedirect(route('login'));
    }
}
