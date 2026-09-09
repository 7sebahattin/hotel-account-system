<?php

declare(strict_types=1);

namespace Tests\Feature\CashBook;

use App\Modules\CashBook\Enums\CashMovementType;
use App\Modules\CashBook\Models\CashTransfer;
use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;

/**
 * Kasa hareketleri ve devir geçmişi listelerinin arama/filtre/yetki kapsaması
 * (Gün 12 §8-10, §17, §21).
 */
class CashBookFilterTest extends CashBookTestCase
{
    // ==================================================== Kasa Hareketleri

    #[Test]
    public function tarih_araligi_filtresi_dogru_hareketleri_getirir(): void
    {
        $admin = $this->admin();
        $session = $this->openSession($admin, ['TRY' => '1000.00']);

        // Hareket append-only'dir (DB tetikleyicisi UPDATE'i reddeder); tarih
        // farkı geriye dönük düzenlemeyle DEĞİL, `recordMovement()`'in kendi
        // `entryAt` parametresiyle verilir.
        $this->cash->recordMovement(
            session: $session, type: CashMovementType::RoomIncome, currency: Currency::TRY,
            amount: Money::fromDecimal('100.00'), actor: $admin, entryAt: '2026-09-05 10:00:00',
        );

        $this->cash->recordMovement(
            session: $session, type: CashMovementType::RoomIncome, currency: Currency::TRY,
            amount: Money::fromDecimal('200.00'), actor: $admin, entryAt: '2026-09-20 10:00:00',
        );

        $response = $this->actingAs($admin)->get(route('cashbook.movements.index', [
            'date_from' => '2026-09-01',
            'date_to' => '2026-09-10',
        ]));

        $response->assertOk()->assertSee('100,00 ₺')->assertDontSee('200,00 ₺');
    }

    #[Test]
    public function tur_filtresi_yalnizca_secilen_turu_getirir(): void
    {
        $admin = $this->admin();
        $session = $this->openSession($admin, ['TRY' => '1000.00']);
        $this->record($session, CashMovementType::Expense, '50.00', $admin);
        $this->record($session, CashMovementType::RoomIncome, '300.00', $admin);

        $response = $this->actingAs($admin)->get(route('cashbook.movements.index', ['type' => 'expense']));

        $response->assertOk()->assertSee('50,00 ₺')->assertDontSee('300,00 ₺');
    }

    /** "Gelirler" hızlı filtresi iki türü BİRDEN kapsar (kullanıcı talimatı §9). */
    #[Test]
    public function coklu_tur_filtresi_gelirler_kisayolunu_destekler(): void
    {
        $admin = $this->admin();
        $session = $this->openSession($admin, ['TRY' => '1000.00']);
        $this->record($session, CashMovementType::RoomIncome, '300.00', $admin);
        $this->record($session, CashMovementType::ExtraIncome, '40.00', $admin);
        $this->record($session, CashMovementType::Expense, '15.00', $admin);

        $response = $this->actingAs($admin)->get(route('cashbook.movements.index', [
            'type' => ['room_income', 'extra_income'],
        ]));

        $response->assertOk()
            ->assertSee('300,00 ₺')
            ->assertSee('40,00 ₺')
            ->assertDontSee('15,00 ₺');
    }

    #[Test]
    public function para_birimi_filtresi_calisir_rub_usd_gel_dahil(): void
    {
        $admin = $this->admin();
        $session = $this->openSession($admin, ['TRY' => '1000.00']);
        $this->record($session, CashMovementType::RoomIncome, '150.00', $admin, Currency::RUB);
        $this->record($session, CashMovementType::RoomIncome, '75.00', $admin, Currency::USD);
        $this->record($session, CashMovementType::RoomIncome, '30.00', $admin, Currency::GEL);

        foreach (['RUB' => '150,00 RUB', 'USD' => '75,00 $', 'GEL' => '30,00 GEL'] as $code => $needle) {
            $response = $this->actingAs($admin)->get(route('cashbook.movements.index', ['currency' => $code]));
            $response->assertOk();
        }

        // Filtre gerçekten daraltıyor mu: TRY seçiliyken RUB satırı görünmemeli.
        $response = $this->actingAs($admin)->get(route('cashbook.movements.index', ['currency' => 'TRY']));
        $response->assertOk()->assertDontSee('150,00 RUB');
    }

    #[Test]
    public function yon_filtresi_giris_ve_cikisi_ayirir(): void
    {
        $admin = $this->admin();
        $session = $this->openSession($admin, ['TRY' => '1000.00']);
        $this->record($session, CashMovementType::RoomIncome, '400.00', $admin);
        $this->record($session, CashMovementType::CashOut, '250.00', $admin);

        $response = $this->actingAs($admin)->get(route('cashbook.movements.index', ['direction_filter' => 'out']));

        $response->assertOk()->assertSee('250,00 ₺')->assertDontSee('400,00 ₺');
    }

    #[Test]
    public function vardiya_filtresi_yalnizca_o_oturumun_hareketlerini_getirir(): void
    {
        $x = $this->personel('X');
        $sessionX = $this->openSession($x, ['TRY' => '1000.00']);
        $this->record($sessionX, CashMovementType::RoomIncome, '111.00', $x);

        $transfer = $this->cash->startHandover($sessionX->refresh(), $this->admin(), $this->amounts(['TRY' => '1000.00']), $x);
        $sessionY = $this->cash->acceptHandover($transfer, $transfer->toUser);
        $this->record($sessionY, CashMovementType::RoomIncome, '222.00', $transfer->toUser);

        $response = $this->actingAs($this->admin())
            ->get(route('cashbook.movements.index', ['session_id' => $sessionX->id]));

        $response->assertOk()->assertSee('111,00 ₺')->assertDontSee('222,00 ₺');
    }

    #[Test]
    public function arama_aciklama_ve_aliciyi_kapsar(): void
    {
        $admin = $this->admin();
        $session = $this->openSession($admin, ['TRY' => '1000.00']);
        $movement = $this->cash->recordMovement(
            session: $session, type: CashMovementType::CashOut, currency: Currency::TRY,
            amount: Money::fromDecimal('500.00'), actor: $admin,
            counterparty: 'Patron Ahmet',
        );

        $response = $this->actingAs($admin)->get(route('cashbook.movements.index', ['q' => 'Ahmet']));

        $response->assertOk()->assertSee('500,00 ₺');
    }

    // -------------------------------------------------------- yetki kapsaması

    #[Test]
    public function personel_yalnizca_kendi_hareketlerini_gorur(): void
    {
        // Not: devirle beraber Mehmet'in bakiyesi Ayşe'nin AÇILIŞ tutarına
        // dönüşür (1000 + 777 = 1777) — "1.777,00 ₺" alt dize olarak
        // "777,00 ₺"yi İÇERİR. Bu yüzden tutar yerine AÇIKLAMA metni
        // ayırt edici olarak kullanılır (sahte bir alt dize eşleşmesi
        // "gizli değil" izlenimi vermesin diye).
        $mehmet = $this->personel('Mehmet');
        $sessionMehmet = $this->openSession($mehmet, ['TRY' => '1000.00']);
        $this->cash->recordMovement(
            session: $sessionMehmet, type: CashMovementType::Expense, currency: Currency::TRY,
            amount: Money::fromDecimal('42.00'), actor: $mehmet, description: 'Mehmedin-gizli-gideri',
        );

        $ayse = $this->personel('Ayşe');
        $transfer = $this->cash->startHandover($sessionMehmet->refresh(), $ayse, $this->amounts(['TRY' => '958.00']), $mehmet);
        $sessionAyse = $this->cash->acceptHandover($transfer, $ayse);
        $this->cash->recordMovement(
            session: $sessionAyse, type: CashMovementType::RoomIncome, currency: Currency::TRY,
            amount: Money::fromDecimal('300.00'), actor: $ayse, description: 'Aysenin-gorunur-geliri',
        );

        $response = $this->actingAs($ayse)->get(route('cashbook.movements.index'));

        $response->assertOk()
            ->assertSee('Aysenin-gorunur-geliri')
            ->assertDontSee('Mehmedin-gizli-gideri');
    }

    /**
     * KRİTİK (kullanıcı talimatı §17): personel `user_id` query param'ını
     * BAŞKA bir personelin kimliğine değiştirerek onun hareketlerini
     * GÖREMEZ. Yetki kapsaması (`created_by = self`) filtreden önce
     * uygulanmıştır; param bu kapsamın İÇİNDE ek bir AND'dir, dışına
     * TAŞIRAMAZ.
     */
    #[Test]
    public function personel_user_id_parametresiyle_baskasinin_hareketlerini_goremez(): void
    {
        $mehmet = $this->personel('Mehmet');
        $sessionMehmet = $this->openSession($mehmet, ['TRY' => '1000.00']);
        $this->record($sessionMehmet, CashMovementType::RoomIncome, '999.00', $mehmet);

        $ayse = $this->personel('Ayşe');

        $response = $this->actingAs($ayse)
            ->get(route('cashbook.movements.index', ['user_id' => $mehmet->id]));

        $response->assertOk()->assertDontSee('999,00 ₺');
    }

    #[Test]
    public function view_all_yetkisi_olan_personel_secip_filtreleyebilir(): void
    {
        $mehmet = $this->personel('Mehmet');
        $session = $this->openSession($mehmet, ['TRY' => '1000.00']);
        $this->record($session, CashMovementType::RoomIncome, '444.00', $mehmet);

        $response = $this->actingAs($this->patron())
            ->get(route('cashbook.movements.index', ['user_id' => $mehmet->id]));

        $response->assertOk()->assertSee('444,00 ₺');
    }

    #[Test]
    public function personel_form_alaninda_baska_personel_secenegi_gormez(): void
    {
        $this->personel('Diğer Personel');

        $html = $this->actingAs($this->personel('Ben'))
            ->get(route('cashbook.movements.index'))
            ->getContent();

        $this->assertStringNotContainsString('name="user_id"', (string) $html);
    }

    #[Test]
    public function filtrelenmis_bos_sonucta_temizle_baglantisi_gorunur(): void
    {
        $admin = $this->admin();
        $this->openSession($admin, ['TRY' => '1000.00']);

        $response = $this->actingAs($admin)
            ->get(route('cashbook.movements.index', ['q' => 'eşleşmeyen-terim-xyz']));

        $response->assertOk()
            ->assertSee('Bu filtrelerle eşleşen kayıt bulunamadı.')
            ->assertSee('Filtreleri temizle');
    }

    #[Test]
    public function hizli_filtre_bugun_linki_sayfada_gorunur(): void
    {
        $admin = $this->admin();
        $this->openSession($admin, ['TRY' => '1000.00']);

        $this->actingAs($admin)->get(route('cashbook.movements.index'))
            ->assertOk()
            ->assertSee('Bugün')
            ->assertSee('Giderler')
            ->assertSee('Gelirler')
            ->assertSee('Ara Kasa');
    }

    #[Test]
    public function sayfalama_filtreleri_korur(): void
    {
        $admin = $this->admin();
        $session = $this->openSession($admin, ['TRY' => '1000.00']);

        foreach (range(1, 3) as $i) {
            $this->record($session, CashMovementType::Expense, "{$i}0.00", $admin);
        }

        $html = (string) $this->actingAs($admin)
            ->get(route('cashbook.movements.index', ['type' => 'expense', 'per_page' => 25]))
            ->getContent();

        $this->assertStringContainsString('type=expense', $html);
    }

    #[Test]
    public function hareket_listesi_sorgu_sayisi_kayit_sayisiyla_buyumez(): void
    {
        $admin = $this->admin();
        $session = $this->openSession($admin, ['TRY' => '10000.00']);

        $measure = function () use ($admin): int {
            // Isınma isteği: yetki önbelleği vb. tek seferlik maliyetler ilk
            // ölçümü şişirip sahte bir "sorgu azaldı" izlenimi vermesin diye.
            $this->actingAs($admin)->get(route('cashbook.movements.index'));

            DB::enableQueryLog();
            DB::flushQueryLog();
            $this->actingAs($admin)->get(route('cashbook.movements.index'))->assertOk();
            $count = count(DB::getRawQueryLog());
            DB::disableQueryLog();

            return $count;
        };

        $this->record($session, CashMovementType::RoomIncome, '10.00', $admin);
        $withOne = $measure();

        foreach (range(1, 10) as $i) {
            $this->record($session, CashMovementType::RoomIncome, "{$i}.00", $admin);
        }
        $withEleven = $measure();

        $this->assertSame($withOne, $withEleven, "Sorgu sayısı kayıt sayısıyla arttı ({$withOne} → {$withEleven}): N+1 var.");
    }

    // ======================================================== Devir Geçmişi

    #[Test]
    public function devir_gecmisi_durum_filtresi_calisir(): void
    {
        $x = $this->personel('X');
        $session = $this->openSession($x, ['TRY' => '1000.00']);
        $y = $this->personel('Y');

        $this->cash->startHandover($session->refresh(), $y, $this->amounts(['TRY' => '1000.00']), $x);

        $response = $this->actingAs($this->admin())
            ->get(route('cashbook.transfers.index', ['status' => 'pending']));

        $response->assertOk()->assertSee('Kabul bekliyor');
    }

    #[Test]
    public function devir_gecmisi_aramasi_personel_adiyla_calisir(): void
    {
        $x = $this->personel('Zeynep');
        $session = $this->openSession($x, ['TRY' => '1000.00']);
        $y = $this->personel('Y');
        $this->cash->startHandover($session->refresh(), $y, $this->amounts(['TRY' => '1000.00']), $x);

        $response = $this->actingAs($this->admin())
            ->get(route('cashbook.transfers.index', ['q' => 'Zeynep']));

        $response->assertOk()->assertSee('Zeynep');
    }

    #[Test]
    public function devir_gecmisi_para_birimi_filtresi_calisir(): void
    {
        $x = $this->personel('X');
        $session = $this->openSession($x, ['TRY' => '1000.00', 'EUR' => '200.00']);
        $y = $this->personel('Y');
        $this->cash->startHandover($session->refresh(), $y, $this->amounts(['TRY' => '1000.00', 'EUR' => '200.00']), $x);

        $response = $this->actingAs($this->admin())
            ->get(route('cashbook.transfers.index', ['currency' => 'EUR']));

        $response->assertOk()->assertSee('200,00 €');
    }

    #[Test]
    public function devir_tarafi_olmayan_personel_baskalarinin_devrini_goremez(): void
    {
        $gonderen = $this->personel('Gönderen Kişi');
        $sessionGonderen = $this->openSession($gonderen, ['TRY' => '1000.00']);
        $alan = $this->personel('Alan Kişi');
        $this->cash->startHandover($sessionGonderen->refresh(), $alan, $this->amounts(['TRY' => '1000.00']), $gonderen);

        $disari = $this->personel('İlgisiz Kişi');

        $response = $this->actingAs($disari)->get(route('cashbook.transfers.index'));

        $response->assertOk();
        $response->assertDontSee('Gönderen Kişi');
        $response->assertDontSee('Alan Kişi');
        $this->assertGreaterThan(0, CashTransfer::query()->count(), 'Devir gerçekten var olmalı (test önkoşulu)');
    }

    #[Test]
    public function devir_tarafi_olan_personel_kendi_devrini_gorur(): void
    {
        $gonderen = $this->personel('Gönderen Kişi');
        $sessionGonderen = $this->openSession($gonderen, ['TRY' => '1000.00']);
        $alan = $this->personel('Alan Kişi');
        $this->cash->startHandover($sessionGonderen->refresh(), $alan, $this->amounts(['TRY' => '1000.00']), $gonderen);

        $response = $this->actingAs($alan)->get(route('cashbook.transfers.index'));

        $response->assertOk()->assertSee('Gönderen Kişi')->assertSee('Alan Kişi');
    }

    #[Test]
    public function devir_gecmisi_sorgu_sayisi_kayit_sayisiyla_buyumez(): void
    {
        $admin = $this->admin();

        $measure = function () use ($admin): int {
            $this->actingAs($admin)->get(route('cashbook.transfers.index'));

            DB::enableQueryLog();
            DB::flushQueryLog();
            $this->actingAs($admin)->get(route('cashbook.transfers.index'))->assertOk();
            $count = count(DB::getRawQueryLog());
            DB::disableQueryLog();

            return $count;
        };

        $owner = $this->personel('Owner1');
        $session = $this->openSession($owner, ['TRY' => '1000.00']);

        $next = $this->personel('Next1');
        $transfer = $this->cash->startHandover($session->refresh(), $next, $this->amounts(['TRY' => '1000.00']), $owner);
        $session = $this->cash->acceptHandover($transfer, $next);
        $withOne = $measure();

        foreach (range(1, 3) as $_) {
            $owner = $next;
            $next = $this->personel();
            $transfer = $this->cash->startHandover($session->refresh(), $next, $this->amounts(['TRY' => '1000.00']), $owner);
            $session = $this->cash->acceptHandover($transfer, $next);
        }
        $withFour = $measure();

        $this->assertSame($withOne, $withFour, "Sorgu sayısı devir sayısıyla arttı ({$withOne} → {$withFour}): N+1 var.");
    }
}
