<?php

declare(strict_types=1);

namespace Tests\Feature\CashBook;

use App\Models\User;
use App\Modules\Authorization\Enums\Permission;
use App\Modules\Authorization\Enums\RoleName;
use App\Modules\CashBook\Enums\CashMovementType;
use App\Modules\CashBook\Models\CashCount;
use App\Modules\CashBook\Models\CashMovement;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * Rol × route matrisi ve hesap verebilirlik kuralları.
 *
 * TEMEL İLKE: vardiyayı AÇAN kişi kasadan sorumludur. Başkasının vardiyasına
 * hareket yazmak — admin dahil — hiç kimseye açık değildir.
 */
class CashBookPermissionTest extends CashBookTestCase
{
    // --------------------------------------------------------- rol matrisi

    #[Test]
    #[DataProvider('rolYetkileri')]
    public function rol_matrisi_beklenen_kasa_yetkilerini_verir(
        RoleName $role,
        bool $view,
        bool $operate,
        bool $viewAll,
    ): void {
        $user = $this->userWithRole($role);

        $this->assertSame($view, $user->can(Permission::CashBookView->value), 'cashbook.view');
        $this->assertSame($operate, $user->can(Permission::CashBookOperate->value), 'cashbook.operate');
        $this->assertSame($viewAll, $user->can(Permission::CashBookViewAll->value), 'cashbook.view_all');
    }

    /** @return array<string, array{RoleName, bool, bool, bool}> */
    public static function rolYetkileri(): array
    {
        return [
            'admin' => [RoleName::Admin, true, true, true],
            'patron' => [RoleName::Patron, true, true, true],
            // Personel kendi kasasını görür ve işletir; TÜM vardiyaları göremez.
            'personel' => [RoleName::Personel, true, true, false],
        ];
    }

    #[Test]
    public function personel_gunluk_defteri_goremez(): void
    {
        $this->actingAs($this->personel())->get(route('cashbook.day'))->assertForbidden();
    }

    #[Test]
    public function patron_ve_admin_gunluk_defteri_gorur(): void
    {
        foreach ([$this->patron(), $this->admin()] as $user) {
            $this->actingAs($user)->get(route('cashbook.day'))->assertOk();
        }
    }

    // ------------------------------------------------- hesap verebilirlik

    #[Test]
    public function personel_baskasinin_vardiyasina_hareket_ekleyemez(): void
    {
        $owner = $this->personel('Sahibi');
        $other = $this->personel('Diğeri');
        $session = $this->openSession($owner, ['TRY' => '1000.00']);

        $this->assertTrue($owner->can('create', [CashMovement::class, $session]));
        $this->assertFalse($other->can('create', [CashMovement::class, $session]));

        // HTTP üzerinden de engellenir.
        $this->actingAs($other)->post(route('cashbook.movements.store'), [
            'type' => CashMovementType::RoomIncome->value,
            'amount' => '500,00',
            'currency' => 'TRY',
        ])->assertForbidden();

        $this->assertSame(1, CashMovement::query()->count(), 'Yalnızca açılış hareketi olmalı');
    }

    /**
     * Admin de başkasının vardiyasına hareket yazamaz: kayıt argümanı taşıyan
     * Policy yetenekleri `Gate::before` ile kısa devre YAPILMAZ (CLAUDE.md §7.1).
     */
    #[Test]
    public function admin_bile_baskasinin_vardiyasina_hareket_ekleyemez(): void
    {
        $owner = $this->personel('Sahibi');
        $admin = $this->admin();
        $session = $this->openSession($owner);

        $this->assertFalse($admin->can('create', [CashMovement::class, $session]));
    }

    #[Test]
    public function baskasinin_hareketi_ters_kaydedilemez(): void
    {
        $owner = $this->personel('Sahibi');
        $other = $this->personel('Diğeri');
        $session = $this->openSession($owner);
        $movement = $this->record($session, CashMovementType::RoomIncome, '100.00', $owner);

        $this->assertTrue($owner->can('reverse', $movement));
        $this->assertFalse($other->can('reverse', $movement));
        $this->assertFalse($this->admin()->can('reverse', $movement));

        $this->actingAs($other)
            ->patch(route('cashbook.movements.reverse', $movement))
            ->assertForbidden();
    }

    #[Test]
    public function devri_yalnizca_vardiya_sahibi_baslatabilir(): void
    {
        $owner = $this->personel('Sahibi');
        $other = $this->personel('Diğeri');
        $session = $this->openSession($owner);

        $this->assertTrue($owner->can('handover', $session));
        $this->assertFalse($other->can('handover', $session));
    }

    /** Kapatma, kilitli kasa kalmasın diye yöneticiye de açıktır. */
    #[Test]
    public function vardiyayi_sahibi_ve_yonetici_kapatabilir_baskasi_kapatamaz(): void
    {
        $owner = $this->personel('Sahibi');
        $other = $this->personel('Diğeri');
        $session = $this->openSession($owner);

        $this->assertTrue($owner->can('close', $session));
        $this->assertTrue($this->patron()->can('close', $session));
        $this->assertFalse($other->can('close', $session));
    }

    // ------------------------------------------------------- görünürlük

    #[Test]
    public function personel_baskasinin_vardiya_tutarlarini_goremez(): void
    {
        $owner = $this->personel('Sahibi');
        $other = $this->personel('Diğeri');
        $session = $this->openSession($owner, ['TRY' => '9999.00']);

        $this->assertTrue($owner->can('view', $session));
        $this->assertFalse($other->can('view', $session));

        // Ana ekranda kasanın KİMDE olduğu görünür, TUTAR görünmez.
        $response = $this->actingAs($other)->get(route('cashbook.index'));

        $response->assertOk()
            ->assertSee('Sahibi')
            ->assertDontSee('9.999,00');
    }

    #[Test]
    public function yonetici_tum_vardiyalari_gorur(): void
    {
        $owner = $this->personel('Sahibi');
        $session = $this->openSession($owner, ['TRY' => '9999.00']);

        foreach ([$this->patron(), $this->admin()] as $user) {
            $this->assertTrue($user->can('view', $session));

            $this->actingAs($user)->get(route('cashbook.sessions.show', $session))
                ->assertOk()
                ->assertSee('9.999,00');
        }
    }

    #[Test]
    public function kasa_yetkisi_olmayan_kullanici_hicbir_kasa_ekranini_acamaz(): void
    {
        // Rolü olmayan (dolayısıyla kasa yetkisi de olmayan) kullanıcı.
        $user = User::factory()->create();
        $session = $this->openSession($this->personel());

        $this->actingAs($user)->get(route('cashbook.index'))->assertForbidden();
        $this->actingAs($user)->get(route('cashbook.sessions.create'))->assertForbidden();
        $this->actingAs($user)->get(route('cashbook.sessions.show', $session))->assertForbidden();
        $this->actingAs($user)->get(route('cashbook.day'))->assertForbidden();
        $this->actingAs($user)->get(route('cashbook.movements.create'))->assertForbidden();
        $this->actingAs($user)->get(route('cashbook.transfers.create'))->assertForbidden();
    }

    #[Test]
    public function giris_yapmamis_kullanici_kasaya_erisemez(): void
    {
        $this->get(route('cashbook.index'))->assertRedirect(route('login'));
    }

    #[Test]
    public function kapali_vardiyaya_http_uzerinden_de_hareket_eklenemez(): void
    {
        $owner = $this->personel();
        $session = $this->openSession($owner);
        $this->cash->closeSession($session, [], $owner);

        // Açık vardiya kalmadığı için hareket ekranı kasaya geri yönlendirir.
        $this->actingAs($owner)->post(route('cashbook.movements.store'), [
            'type' => CashMovementType::RoomIncome->value,
            'amount' => '100,00',
            'currency' => 'TRY',
        ])->assertRedirect(route('cashbook.index'));

        $this->assertSame(0, CashMovement::query()->count());
    }

    #[Test]
    public function personel_kendi_vardiyasini_acabilir_ve_isletebilir(): void
    {
        $user = $this->personel();

        $this->actingAs($user)->get(route('cashbook.sessions.create'))->assertOk();

        $this->actingAs($user)->post(route('cashbook.sessions.store'), [
            'amounts' => ['TRY' => '1.000,00'],
        ])->assertRedirect(route('cashbook.index'));

        $session = $this->cash->activeSession();
        $this->assertNotNull($session);
        $this->assertSame('1000.00', $this->expected($session));

        $this->actingAs($user)->post(route('cashbook.movements.store'), [
            'type' => CashMovementType::RoomIncome->value,
            'amount' => '2.500,50',
            'currency' => 'TRY',
            'description' => '204 numaralı oda',
        ])->assertRedirect(route('cashbook.index'));

        $this->assertSame('3500.50', $this->expected($session));
    }

    #[Test]
    public function bilinmeyen_hareket_turu_ve_para_birimi_reddedilir(): void
    {
        $user = $this->personel();
        $this->openSession($user);

        $this->actingAs($user)->post(route('cashbook.movements.store'), [
            'type' => 'bahsis',
            'amount' => '100,00',
            'currency' => 'TRY',
        ])->assertSessionHasErrors('type');

        $this->actingAs($user)->post(route('cashbook.movements.store'), [
            'type' => CashMovementType::RoomIncome->value,
            'amount' => '100,00',
            'currency' => 'CHF',
        ])->assertSessionHasErrors('currency');

        // Açılış tipi kullanıcıdan alınamaz.
        $this->actingAs($user)->post(route('cashbook.movements.store'), [
            'type' => CashMovementType::Opening->value,
            'amount' => '100,00',
            'currency' => 'TRY',
        ])->assertSessionHasErrors('type');

        $this->assertSame(0, CashMovement::query()->count());
    }

    #[Test]
    public function sayim_formuna_beklenen_tutar_gonderilerek_fark_gizlenemez(): void
    {
        $user = $this->personel();
        $session = $this->openSession($user, ['TRY' => '1000.00']);

        // Kullanıcı beklenen tutarı da göndermeye çalışıyor.
        $this->actingAs($user)->patch(route('cashbook.sessions.close.store', $session), [
            'amounts' => ['TRY' => '900,00'],
            'expected' => ['TRY' => '900,00'],
        ])->assertRedirect();

        $count = CashCount::query()
            ->where('cash_session_id', $session->id)
            ->firstOrFail();

        // Beklenen SUNUCUDA hesaplandı; gönderilen değer yok sayıldı.
        $this->assertSame('1000.00', $count->expectedMoney()->toDecimal());
        $this->assertSame('-100.00', $count->differenceMoney()->toDecimal());
    }
}
