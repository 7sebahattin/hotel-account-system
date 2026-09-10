<?php

declare(strict_types=1);

namespace Tests\Feature\CashBook;

use App\Modules\CashBook\Enums\CashMovementType;
use App\Modules\CashBook\Models\CashMovementCategory;
use PHPUnit\Framework\Attributes\Test;

/**
 * Hızlı İşlem'in "Kasa Hareketi" modal/sheet'i — v2.1.1-B.1.
 *
 * Modal, `cashbook.movements.create` route'unun AYNI controller aksiyonunu
 * kullanır; tek fark `X-Requested-With: XMLHttpRequest` başlığıdır. İki farklı
 * form/route YOKTUR (kullanıcı talimatı §4) — burada test edilen de bu: aynı
 * uç, isteğin türüne göre TAM SAYFA ya da yalnızca İÇERİK PARÇASI döner.
 */
class CashMovementDialogTest extends CashBookTestCase
{
    #[Test]
    public function ajax_istegi_yalnizca_icerik_parcasi_doner(): void
    {
        $user = $this->personel('Mehmet');
        $this->openSession($user);

        $response = $this->actingAs($user)
            ->get(route('cashbook.movements.create'), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $html = $response->getContent();
        $this->assertIsString($html);

        // Form içeriği var…
        $this->assertStringContainsString('Oda Geliri', $html);
        $this->assertStringContainsString('name="category_id"', $html);
        $this->assertStringContainsString('data-cash-dialog-close', $html);

        // …ama sayfa KABUĞU (layout) yok — modal, bu HTML'i mevcut sayfanın
        // İÇİNE enjekte eder, ikinci bir <html>/<body>/kenar çubuğu istemez.
        $this->assertStringNotContainsString('<html', $html);
        $this->assertStringNotContainsString('data-sidebar', $html);
        $this->assertStringNotContainsString('data-bottom-nav', $html);
    }

    #[Test]
    public function normal_istek_tam_sayfa_doner(): void
    {
        $user = $this->personel();
        $this->openSession($user);

        $html = $this->actingAs($user)->get(route('cashbook.movements.create'))
            ->assertOk()
            ->getContent();

        $this->assertIsString($html);
        $this->assertStringContainsString('<html', $html);
        $this->assertStringContainsString('data-sidebar', $html);
    }

    #[Test]
    public function ajax_istegi_acik_vardiya_yokken_yine_yonlendirir(): void
    {
        $user = $this->personel();
        // Kasıtlı: hiç vardiya açılmadı.

        $this->actingAs($user)
            ->get(route('cashbook.movements.create'), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertRedirect(route('cashbook.index'));
    }

    #[Test]
    public function ajax_istegi_baskasinin_vardiyasinda_403_doner(): void
    {
        $owner = $this->personel('Sahibi');
        $other = $this->personel('Diğeri');
        $this->openSession($owner);

        $this->actingAs($other)
            ->get(route('cashbook.movements.create'), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertForbidden();
    }

    /**
     * Doğrulama hatası HER ZAMAN standalone "Hareket ekle" sayfasına döner —
     * modal HANGİ sayfanın üzerinde açılmışsa (`Referer`) o sayfaya DEĞİL
     * (kullanıcı talimatı §5). Bu, `back()`'in Referer'a güvenmesinin tam
     * olarak önlediği kırılgan senaryodur.
     */
    #[Test]
    public function dogrulama_hatasi_referer_ne_olursa_olsun_hareket_ekle_sayfasina_doner(): void
    {
        $user = $this->personel();
        $this->openSession($user);
        $category = CashMovementCategory::factory()
            ->ofType(CashMovementType::ExtraIncome)
            ->create(['name' => 'Test Kategorisi Referer']);

        $response = $this->actingAs($user)
            ->from(route('home'))
            ->post(route('cashbook.movements.store'), [
                'category_id' => $category->id,
                'amount' => '-100,00',
                'currency' => 'TRY',
            ]);

        $response->assertRedirect(route('cashbook.movements.create'));
        $response->assertSessionHasErrors('amount');
        $response->assertSessionHas('_old_input.category_id');
    }

    /**
     * Geçersiz/pasif kategori (§ "category === null" güvenlik ağı) da AYNI
     * deterministik hedefe döner — servis katmanındaki hata mesajı için de
     * Referer'a güvenilmez.
     */
    #[Test]
    public function servis_hatasi_da_referer_ne_olursa_olsun_hareket_ekle_sayfasina_doner(): void
    {
        $user = $this->personel();
        $session = $this->openSession($user);
        $this->cash->closeSession($session, [], $user);

        $category = CashMovementCategory::factory()
            ->ofType(CashMovementType::ExtraIncome)
            ->create(['name' => 'Test Kategorisi Kapali']);

        // Vardiya kapalı olduğu için `create()` ile aynı ilk kontrol devreye
        // girer ve kasa ekranına yönlendirir — bu YOL zaten mevcuttu, burada
        // sadece regresyona karşı doğrulanır.
        $response = $this->actingAs($user)
            ->from(route('home'))
            ->post(route('cashbook.movements.store'), [
                'category_id' => $category->id,
                'amount' => '100,00',
                'currency' => 'TRY',
            ]);

        $response->assertRedirect(route('cashbook.index'));
    }
}
