<?php

declare(strict_types=1);

namespace Tests\Feature\CashBook;

use App\Modules\CashBook\Models\CashMovement;
use PHPUnit\Framework\Attributes\Test;

/**
 * v2.1.1-A §7 — P0 GÜVENLİK DÜZELTMESİ.
 *
 * Denetim bulgusu: kapanış sayımı ve devir formlarındaki tutar alanları
 * sistemin BEKLENEN tutarıyla önceden dolduruluyordu. Personel gerçekte
 * saymadan formu olduğu gibi gönderebilirdi — fiziksel sayım kontrolü
 * sessizce etkisiz kalırdı. Bu test, alanın HİÇBİR ZAMAN gerçek bir `value`
 * taşımadığını, yalnızca ipucu METNİNDE "Beklenen: …" olarak göründüğünü
 * kanıtlar (`_amount-fields.blade.php`).
 */
class CashCountPrefillSafetyTest extends CashBookTestCase
{
    #[Test]
    public function kapanis_sayimi_formu_tutari_beklenenle_onceden_doldurmaz(): void
    {
        $admin = $this->admin();
        $session = $this->openSession($admin, ['TRY' => '7000.00']);

        $response = $this->actingAs($admin)->get(route('cashbook.sessions.close', $session));

        $response->assertOk()
            // Beklenen tutar REFERANS olarak ipucunda görünür (biçimi farklı: 7.000,00 ₺).
            ->assertSee('Beklenen: 7.000,00 ₺', false)
            // Ama giriş alanının GERÇEK değeri (Money::toDecimal biçimi) ASLA olmamalı.
            ->assertDontSee('value="7000.00"', false);
    }

    #[Test]
    public function devir_formu_tutari_beklenenle_onceden_doldurmaz(): void
    {
        $sercan = $this->personel('Sercan');
        $this->personel('Ayşe');
        $this->openSession($sercan, ['TRY' => '7000.00']);

        $response = $this->actingAs($sercan)->get(route('cashbook.transfers.create'));

        $response->assertOk()
            ->assertSee('Beklenen: 7.000,00 ₺', false)
            ->assertDontSee('value="7000.00"', false);
    }

    #[Test]
    public function kasa_acilis_formu_zaten_hep_bostur_regresyon_yoktur(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('cashbook.sessions.create'));

        $response->assertOk()->assertDontSee('value="0.00"', false);
    }

    #[Test]
    public function beklenenden_farkli_sayilan_tutar_dogru_islenir_fark_otomatik_dengelenmez(): void
    {
        $admin = $this->admin();
        $session = $this->openSession($admin, ['TRY' => '7000.00']);

        // Personel formu BOŞ alırdı; burada gerçek kullanıcı davranışını
        // simüle ediyoruz: sistemin önerdiği değeri DEĞİL, fiilen saydığı
        // 6.900,00'ı yazıyor.
        $this->actingAs($admin)
            ->patch(route('cashbook.sessions.close.store', $session), ['amounts' => ['TRY' => '6900,00']])
            ->assertSessionHasNoErrors();

        $count = $session->fresh()->counts()->where('currency', 'TRY')->firstOrFail();

        $this->assertSame('7000.00', $count->expected_amount);
        $this->assertSame('6900.00', $count->counted_amount);
        // Fark saklanmaz, hesaplanır (CLAUDE.md §5.8) — otomatik gelir/gider/
        // dengeleme hareketi ÜRETİLMEZ.
        $this->assertSame(1, CashMovement::query()->count(), 'Yalnızca açılış hareketi vardır');
    }
}
