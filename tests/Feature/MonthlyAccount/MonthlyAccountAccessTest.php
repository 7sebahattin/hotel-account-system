<?php

declare(strict_types=1);

namespace Tests\Feature\MonthlyAccount;

use App\Models\User;
use App\Modules\Authorization\Enums\Permission;
use App\Modules\Authorization\Enums\RoleName;
use App\Modules\MonthlyAccount\Services\MonthlyAccountService;
use Illuminate\Auth\Access\AuthorizationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class MonthlyAccountAccessTest extends MonthlyAccountTestCase
{
    /** @return array<string, array{string, bool, bool}> */
    public static function roles(): array
    {
        return [
            'admin' => ['admin', true, true],
            'personel' => ['personel', true, false],
            'patron' => ['patron', false, false],
        ];
    }

    #[Test]
    #[DataProvider('roles')]
    public function her_rolun_kendi_alanindaki_kritik_http_yetkileri_dogrulanir(string $role, bool $canCreate, bool $canDelete): void
    {
        $owner = $this->userWithRole(RoleName::from($role));
        $day = $this->createDay($owner);
        $this->actingAs($owner);

        $this->get(route('monthly-account.index', ['month' => '2026-09']))->assertOk();
        $this->get(route('monthly-account.show', $day))->assertOk();
        $this->get(route('monthly-account.edit', $day))->assertOk();
        $this->get(route('monthly-account.create'))->assertStatus($canCreate ? 200 : 403);

        $response = $this->post(route('monthly-account.store'), $this->payload($owner, ['account_date' => '2026-09-15']));
        if ($canCreate) {
            $response->assertRedirect()->assertSessionDoesntHaveErrors();
            $this->assertDatabaseHas('monthly_account_days', ['owner_id' => $owner->id, 'account_date' => '2026-09-15']);
        } else {
            $response->assertForbidden();
            $this->assertDatabaseMissing('monthly_account_days', ['owner_id' => $owner->id, 'account_date' => '2026-09-15']);
        }

        $this->put(route('monthly-account.update', $day), $this->payload($owner, ['version' => 1, 'daily_total' => '3500']))
            ->assertRedirect()->assertSessionDoesntHaveErrors();
        $this->assertSame(2, $day->fresh()->current_version);

        $response = $this->delete(route('monthly-account.destroy', $day), ['version' => 2]);
        if ($canDelete) {
            $response->assertRedirect()->assertSessionDoesntHaveErrors();
        } else {
            $response->assertForbidden();
        }
        $this->assertDatabaseHas('monthly_account_days', ['id' => $day->id, 'status' => $canDelete ? 'deleted' : 'active']);
    }

    #[Test]
    #[DataProvider('restrictedRoles')]
    public function personel_ve_patron_baska_kullanicinin_alanina_url_ve_form_ile_erisemez(string $role): void
    {
        $owner = $this->userWithRole(RoleName::Personel);
        $intruder = $this->userWithRole(RoleName::from($role));
        $day = $this->createDay($owner);
        $this->actingAs($intruder);

        $this->get(route('monthly-account.index', ['owner_id' => $owner->id, 'month' => '2026-09']))->assertForbidden();
        $this->get(route('monthly-account.create', ['owner_id' => $owner->id]))->assertForbidden();
        $this->get(route('monthly-account.show', $day))->assertForbidden();
        $this->get(route('monthly-account.edit', $day))->assertForbidden();
        $this->put(route('monthly-account.update', $day), $this->payload($intruder, ['version' => 1, 'daily_total' => '999']))->assertForbidden();
        $this->delete(route('monthly-account.destroy', $day), ['version' => 1])->assertForbidden();
        $this->post(route('monthly-account.store'), $this->payload($owner, ['account_date' => '2026-09-15']))->assertForbidden();

        $this->assertDatabaseCount('monthly_account_days', 1);
        $this->assertDatabaseCount('monthly_account_revisions', 1);
        $this->assertSame(1, $day->fresh()->current_version);
    }

    /** @return array<string, array{string}> */
    public static function restrictedRoles(): array
    {
        return ['personel' => ['personel'], 'patron' => ['patron']];
    }

    /** @return array<string, array{string}> */
    public static function supervisors(): array
    {
        return ['admin' => ['admin']];
    }

    #[Test]
    #[DataProvider('supervisors')]
    public function admin_personelin_alanini_secerek_gorur_ve_duzenler(string $role): void
    {
        $owner = $this->userWithRole(RoleName::Personel);
        $supervisor = $this->userWithRole(RoleName::from($role));
        $day = $this->createDay($owner);
        $this->actingAs($supervisor);

        $this->get(route('monthly-account.index', ['owner_id' => $owner->id, 'month' => '2026-09']))
            ->assertOk()->assertViewHas('owner', fn (User $selected) => $selected->is($owner));
        $this->get(route('monthly-account.show', $day))->assertOk();
        $this->get(route('monthly-account.edit', $day))->assertOk();
        $this->put(route('monthly-account.update', $day), $this->payload($owner, ['version' => 1, 'note' => 'Yönetici düzeltmesi']))
            ->assertRedirect()->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('monthly_account_revisions', ['day_id' => $day->id, 'version' => 2, 'created_by' => $supervisor->id]);
        $this->assertSame($owner->id, $day->fresh()->owner_id);

        $deletion = $this->delete(route('monthly-account.destroy', $day), ['version' => 2]);
        if ($role === 'admin') {
            $deletion->assertRedirect()->assertSessionDoesntHaveErrors();
        } else {
            $deletion->assertForbidden();
        }
        $this->assertDatabaseHas('monthly_account_days', ['id' => $day->id, 'status' => $role === 'admin' ? 'deleted' : 'active']);
    }

    #[Test]
    public function yetkisiz_kullanici_tum_http_uclarindan_engellenir(): void
    {
        $day = $this->createDay($this->admin);
        $unprivileged = User::factory()->create();
        $this->actingAs($unprivileged);

        foreach (['index', 'create'] as $action) {
            $this->get(route('monthly-account.'.$action))->assertForbidden();
        }
        foreach (['show', 'edit'] as $action) {
            $this->get(route('monthly-account.'.$action, $day))->assertForbidden();
        }
        $this->post(route('monthly-account.store'), $this->payload($unprivileged))->assertForbidden();
        $this->put(route('monthly-account.update', $day), $this->payload($unprivileged, ['version' => 1]))->assertForbidden();
        $this->delete(route('monthly-account.destroy', $day), ['version' => 1])->assertForbidden();
        $this->assertSame(1, $day->fresh()->current_version);
    }

    #[Test]
    public function goruntuleme_yetkisi_yazma_yetkisi_vermez(): void
    {
        $owner = User::factory()->create();
        $owner->givePermissionTo(Permission::MonthlyAccountView->value);
        $day = $this->createDay($owner);
        $this->actingAs($owner);

        $this->get(route('monthly-account.index'))->assertOk();
        $this->get(route('monthly-account.show', $day))->assertOk();
        $this->get(route('monthly-account.edit', $day))->assertForbidden();
        $this->post(route('monthly-account.store'), $this->payload($owner, ['account_date' => '2026-09-15']))->assertForbidden();
        $this->put(route('monthly-account.update', $day), $this->payload($owner, ['version' => 1]))->assertForbidden();
        $this->delete(route('monthly-account.destroy', $day), ['version' => 1])->assertForbidden();
    }

    #[Test]
    #[DataProvider('restrictedRoles')]
    public function servis_http_disindan_da_personelin_baska_kayda_yazmasini_engeller(string $role): void
    {
        $owner = $this->userWithRole(RoleName::Personel);
        $intruder = $this->userWithRole(RoleName::from($role));
        $day = $this->createDay($owner);

        try {
            app(MonthlyAccountService::class)->update($day, $this->payload($owner, ['version' => 1]), $intruder);
            $this->fail('Servis sahiplik denetimi HTTP dışında da çalışmalıdır.');
        } catch (AuthorizationException) {
            $this->assertSame(1, $day->fresh()->current_version);
            $this->assertDatabaseCount('monthly_account_revisions', 1);
        }
    }

    #[Test]
    public function personel_gecmis_ayda_kendi_gununu_duzenleyebilir(): void
    {
        $owner = $this->userWithRole(RoleName::Personel);
        $day = $this->createDay($owner, ['account_date' => '2026-01-02']);

        $this->actingAs($owner)->put(route('monthly-account.update', $day), $this->payload($owner, [
            'account_date' => '2026-01-02', 'version' => 1, 'daily_total' => '3200',
        ]))->assertRedirect()->assertSessionDoesntHaveErrors();

        $this->assertSame(2, $day->fresh()->current_version);
    }

    #[Test]
    public function oturum_acmadan_korumali_sayfalar_ve_yazma_uclari_kullanilamaz(): void
    {
        $day = $this->createDay($this->admin);
        auth()->logout();

        foreach (['index', 'create'] as $action) {
            $this->get(route('monthly-account.'.$action))->assertRedirect(route('login'));
        }
        foreach (['show', 'edit'] as $action) {
            $this->get(route('monthly-account.'.$action, $day))->assertRedirect(route('login'));
        }
        $this->post(route('monthly-account.store'), $this->payload($this->admin))->assertRedirect(route('login'));
        $this->put(route('monthly-account.update', $day), $this->payload($this->admin, ['version' => 1]))->assertRedirect(route('login'));
        $this->delete(route('monthly-account.destroy', $day), ['version' => 1])->assertRedirect(route('login'));
        $this->assertSame(1, $day->fresh()->current_version);
    }
}
