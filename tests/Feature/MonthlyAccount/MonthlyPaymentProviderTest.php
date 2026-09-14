<?php

declare(strict_types=1);

namespace Tests\Feature\MonthlyAccount;

use App\Modules\Authorization\Enums\RoleName;
use App\Modules\MonthlyAccount\Models\MonthlyPaymentProvider;
use PHPUnit\Framework\Attributes\Test;

class MonthlyPaymentProviderTest extends MonthlyAccountTestCase
{
    #[Test]
    public function admin_kurulus_tanimlarini_yonetim_altinda_gorur_ve_ekler(): void
    {
        $this->actingAs($this->admin)
            ->get(route('monthly-account.providers.index'))
            ->assertOk()
            ->assertSee('Banka / Ödeme Kuruluşları')
            ->assertSee('Yapı Kredi');

        $this->post(route('monthly-account.providers.store'), ['name' => '  Param   POS  '])
            ->assertRedirect(route('monthly-account.providers.index'))
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('monthly_payment_providers', [
            'name' => 'Param POS',
            'is_active' => true,
        ]);
    }

    #[Test]
    public function patron_ve_personel_kurulus_tanimlarini_yonetemez(): void
    {
        foreach ([RoleName::Patron, RoleName::Personel] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)
                ->get(route('monthly-account.providers.index'))
                ->assertForbidden();
            $this->post(route('monthly-account.providers.store'), ['name' => 'Yetkisiz Kuruluş'])
                ->assertForbidden();
        }

        $this->assertDatabaseMissing('monthly_payment_providers', ['name' => 'Yetkisiz Kuruluş']);
    }

    #[Test]
    public function pasif_kurulus_yeni_kayitta_secilemez_ama_tarihi_kayitta_korunabilir(): void
    {
        $provider = MonthlyPaymentProvider::query()->where('name', 'Yapı Kredi')->firstOrFail();
        $day = $this->createDay($this->admin, ['items' => [
            $this->item(['method' => 'credit_card', 'bank_name' => $provider->name]),
        ]]);

        $this->actingAs($this->admin)
            ->patch(route('monthly-account.providers.toggle-active', $provider))
            ->assertRedirect();

        $this->get(route('monthly-account.create'))
            ->assertOk()
            ->assertDontSee('<option value="Yapı Kredi"', false);

        $this->post(route('monthly-account.store'), $this->payload($this->admin, [
            'account_date' => '2026-09-15',
            'items' => [$this->item(['method' => 'credit_card', 'bank_name' => 'Yapı Kredi'])],
        ]))->assertSessionHasErrors('items.0.bank_name');

        $this->put(route('monthly-account.update', $day), $this->payload($this->admin, [
            'version' => 1,
            'items' => [$this->item(['method' => 'credit_card', 'bank_name' => 'Yapı Kredi', 'amount' => '3500'])],
        ]))->assertSessionDoesntHaveErrors();
    }

    #[Test]
    public function tanimsiz_kurulus_elle_gonderilse_bile_reddedilir(): void
    {
        $this->actingAs($this->admin)
            ->post(route('monthly-account.store'), $this->payload($this->admin, [
                'items' => [$this->item(['method' => 'link', 'bank_name' => 'Listede Olmayan Banka'])],
            ]))
            ->assertSessionHasErrors('items.0.bank_name');
    }

    #[Test]
    public function kullanilmayan_tanim_silinebilir_kullanilan_tanim_yalniz_pasiflestirilebilir(): void
    {
        $unused = MonthlyPaymentProvider::query()->create(['name' => 'Silinebilir Kuruluş', 'is_active' => true]);
        $this->actingAs($this->admin)
            ->delete(route('monthly-account.providers.destroy', $unused))
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertModelMissing($unused);

        $used = MonthlyPaymentProvider::query()->where('name', 'İş Bankası')->firstOrFail();
        $this->createDay($this->admin, ['items' => [
            $this->item(['method' => 'bank_transfer', 'bank_name' => $used->name]),
        ]]);

        $this->delete(route('monthly-account.providers.destroy', $used))
            ->assertRedirect()
            ->assertSessionHas('error');
        $this->assertModelExists($used);

        $this->put(route('monthly-account.providers.update', $used), ['name' => 'Yeni İş Bankası'])
            ->assertRedirect()
            ->assertSessionHas('error');
        $this->assertDatabaseHas('monthly_payment_providers', ['id' => $used->id, 'name' => 'İş Bankası']);
    }
}
