<?php

declare(strict_types=1);

namespace Tests\Feature\CashBook;

use App\Modules\CashBook\Enums\CashMovementType;
use App\Modules\CashBook\Models\CashMovement;
use App\Modules\CashBook\Models\CashMovementCategory;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;

/**
 * Kasa hareket türü (kategori) yönetimi — v2.1.1-B §2-5.
 *
 * Kural: admin/patron yalnızca SABİT sistem davranışlarından (`selectable()`)
 * birini bir kategoriye eşleyebilir, kendi aritmetiğini/yönünü İCAT EDEMEZ.
 * Kategori asla hard-delete edilmez — yalnızca pasifleştirilir; geçmiş
 * hareketler kategorinin O ANKİ adının anlık görüntüsünü (`category_label`)
 * taşıdığı için sonraki yeniden adlandırma/pasifleştirmeden ETKİLENMEZ.
 */
class CashMovementCategoryTest extends CashBookTestCase
{
    #[Test]
    public function admin_yeni_kategori_olusturabilir(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('cashbook.categories.index'))->assertOk();

        $this->actingAs($admin)->post(route('cashbook.categories.store'), [
            'name' => 'Mini Bar',
            'system_type' => CashMovementType::ExtraIncome->value,
            'sort_order' => 15,
        ])->assertRedirect(route('cashbook.categories.index'));

        $category = CashMovementCategory::query()->where('name', 'Mini Bar')->firstOrFail();

        $this->assertSame(CashMovementType::ExtraIncome, $category->system_type);
        $this->assertTrue($category->is_active);
        $this->assertFalse($category->requires_recipient);
    }

    #[Test]
    public function patron_da_kategori_yonetebilir(): void
    {
        $this->actingAs($this->patron())->get(route('cashbook.categories.index'))->assertOk();
    }

    #[Test]
    public function personel_kategori_ekranlarina_erisemez(): void
    {
        $personel = $this->personel();

        $this->actingAs($personel)->get(route('cashbook.categories.index'))->assertForbidden();
        $this->actingAs($personel)->get(route('cashbook.categories.create'))->assertForbidden();
        $this->actingAs($personel)->post(route('cashbook.categories.store'), [
            'name' => 'Personel Denemesi',
            'system_type' => CashMovementType::ExtraIncome->value,
        ])->assertForbidden();
    }

    /**
     * Admin yalnızca SABİT davranışlardan (`selectable()`) birini seçebilir —
     * `opening`/`company_collection` sistem üretimlidir, admin tarafından asla
     * atanamaz (kullanıcı talimatı §2).
     */
    #[Test]
    public function sistem_uretimi_davranislar_kategoriye_atanamaz(): void
    {
        $admin = $this->admin();

        foreach (['opening', 'company_collection', 'bahsis'] as $invalid) {
            $this->actingAs($admin)->post(route('cashbook.categories.store'), [
                'name' => 'Geçersiz '.$invalid,
                'system_type' => $invalid,
            ])->assertSessionHasErrors('system_type');
        }

        $this->assertSame(0, CashMovementCategory::query()->where('name', 'like', 'Geçersiz %')->count());
    }

    #[Test]
    public function ayni_adla_ikinci_kategori_reddedilir(): void
    {
        CashMovementCategory::factory()->create(['name' => 'Personel Yemek']);

        $this->actingAs($this->admin())->post(route('cashbook.categories.store'), [
            'name' => 'Personel Yemek',
            'system_type' => CashMovementType::Expense->value,
        ])->assertSessionHasErrors('name');
    }

    /** Silme UCU hiç yoktur — kategori yalnızca pasifleştirilir. */
    #[Test]
    public function kategori_silme_route_yoktur(): void
    {
        $category = CashMovementCategory::factory()->create(['name' => 'Silinecek Değil']);

        $this->assertFalse(
            Route::has('cashbook.categories.destroy'),
            'Kategoriler için bir silme route\'u tanımlanmamalı.',
        );

        // DELETE fiili tanımlı olmadığından bu istek 405 (yöntem izinli değil) döner.
        $this->actingAs($this->admin())
            ->delete('/cash/categories/'.$category->id)
            ->assertStatus(405);

        $this->assertNotNull($category->fresh());
    }

    #[Test]
    public function admin_kategoriyi_pasiflestirebilir_ve_yeniden_adlandirabilir(): void
    {
        $admin = $this->admin();
        $category = CashMovementCategory::factory()->create(['name' => 'Eski Ad', 'is_active' => true]);

        $this->actingAs($admin)->put(route('cashbook.categories.update', $category), [
            'name' => 'Yeni Ad',
            'is_active' => '0',
        ])->assertRedirect(route('cashbook.categories.index'));

        $category->refresh();
        $this->assertSame('Yeni Ad', $category->name);
        $this->assertFalse($category->is_active);
    }

    /** `system_type` düzenleme isteğinde hiç TANIMLI değildir — gönderilse bile değişmez. */
    #[Test]
    public function duzenlemede_sistem_davranisi_degistirilemez(): void
    {
        $admin = $this->admin();
        $category = CashMovementCategory::factory()->create(['system_type' => CashMovementType::Expense]);

        $this->actingAs($admin)->put(route('cashbook.categories.update', $category), [
            'name' => $category->name,
            'system_type' => CashMovementType::CashIn->value,
        ])->assertRedirect(route('cashbook.categories.index'));

        $this->assertSame(CashMovementType::Expense, $category->fresh()->system_type);
    }

    #[Test]
    public function pasif_kategori_yeni_hareket_icin_secilemez(): void
    {
        $user = $this->personel();
        $this->openSession($user);
        $inactive = CashMovementCategory::factory()->inactive()->create(['name' => 'Pasif Kategori']);

        $this->actingAs($user)->post(route('cashbook.movements.store'), [
            'category_id' => $inactive->id,
            'amount' => '100,00',
            'currency' => 'TRY',
        ])->assertSessionHasErrors('category_id');

        $this->assertSame(0, CashMovement::query()->where('category_id', $inactive->id)->count());
    }

    #[Test]
    public function teslim_alan_gerektiren_kategori_bos_birakilamaz(): void
    {
        $user = $this->personel();
        $this->openSession($user);
        $category = CashMovementCategory::factory()->create([
            'name' => 'Ara Kasa Teslimi',
            'system_type' => CashMovementType::CashOut,
            'requires_recipient' => true,
        ]);

        $this->actingAs($user)->post(route('cashbook.movements.store'), [
            'category_id' => $category->id,
            'amount' => '100,00',
            'currency' => 'TRY',
        ])->assertSessionHasErrors('counterparty');

        $this->actingAs($user)->post(route('cashbook.movements.store'), [
            'category_id' => $category->id,
            'amount' => '100,00',
            'currency' => 'TRY',
            'counterparty' => 'Patron',
        ])->assertRedirect(route('cashbook.index'));

        $this->assertSame(1, CashMovement::query()->where('category_id', $category->id)->count());
    }

    /**
     * Kategori sonradan yeniden adlandırılsa/pasifleştirilse bile GEÇMİŞ
     * hareketin etiketi, hareketin YAZILDIĞI andaki kategori adını taşımaya
     * devam eder (`category_label` anlık görüntüsü — kullanıcı talimatı §3).
     */
    #[Test]
    public function kategori_yeniden_adlandirilinca_gecmis_hareketin_etiketi_degismez(): void
    {
        $user = $this->personel();
        $session = $this->openSession($user);
        $category = CashMovementCategory::factory()->create([
            'name' => 'Mini Bar', 'system_type' => CashMovementType::ExtraIncome,
        ]);

        $this->actingAs($user)->post(route('cashbook.movements.store'), [
            'category_id' => $category->id,
            'amount' => '250,00',
            'currency' => 'TRY',
        ])->assertRedirect(route('cashbook.index'));

        $movement = CashMovement::query()->where('category_id', $category->id)->firstOrFail();
        $this->assertSame('Mini Bar', $movement->label());

        // Admin kategoriyi hem yeniden adlandırır hem pasifleştirir.
        $this->actingAs($this->admin())->put(route('cashbook.categories.update', $category), [
            'name' => 'Market',
            'is_active' => '0',
        ])->assertRedirect(route('cashbook.categories.index'));

        // Geçmiş hareket hâlâ ESKİ adı gösterir — canlı join değil, anlık görüntü.
        $movement->refresh();
        $this->assertSame('Mini Bar', $movement->label());

        // Ve bu hareket, çapraz-vardiya listesinde de görünmeye devam eder.
        $this->actingAs($this->admin())->get(route('cashbook.movements.index'))
            ->assertOk()
            ->assertSee('Mini Bar');
    }
}
