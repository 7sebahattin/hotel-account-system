<?php

declare(strict_types=1);

namespace Tests\Feature\CashBook;

use App\Models\User;
use App\Modules\CashBook\Enums\CashMovementType;
use App\Modules\CashBook\Models\CashMovementCategory;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;

/**
 * Ekranlar, sorgu bütçesi, pano entegrasyonu ve mobil gezinme.
 */
class CashBookScreenTest extends CashBookTestCase
{
    // ---------------------------------------------------------- ekranlar

    #[Test]
    public function acik_vardiya_yokken_bos_durum_ve_cta_gosterilir(): void
    {
        $this->actingAs($this->personel())->get(route('cashbook.index'))
            ->assertOk()
            ->assertSee('Henüz açık kasa yok')
            ->assertSee('Kasayı aç');
    }

    #[Test]
    public function hareket_yokken_bos_durum_metni_gosterilir(): void
    {
        $user = $this->personel();
        $this->openSession($user);

        $this->actingAs($user)->get(route('cashbook.index'))
            ->assertOk()
            ->assertSee('Bu vardiyada henüz hareket yok');
    }

    #[Test]
    public function kasa_ekrani_beklenen_tutari_ve_son_hareketleri_gosterir(): void
    {
        $user = $this->personel('Mehmet');
        $session = $this->openSession($user, ['TRY' => '7000.00', 'EUR' => '300.00']);
        $this->record($session, CashMovementType::RoomIncome, '2500.00', $user);

        $this->actingAs($user)->get(route('cashbook.index'))
            ->assertOk()
            ->assertSee('Mehmet')
            ->assertSee('9.500,00 ₺')   // 7000 + 2500
            ->assertSee('300,00 €')
            ->assertSee('Oda Geliri')
            ->assertSee('+ Hareket ekle')
            ->assertSee('Kasayı devret');
    }

    #[Test]
    public function para_birimi_kodu_ekranda_kaybolmaz(): void
    {
        $user = $this->personel();
        $session = $this->openSession($user, ['RUB' => '20000.00', 'GEL' => '450.00']);

        $this->actingAs($user)->get(route('cashbook.index'))
            ->assertOk()
            // Sembolü az tanınan para birimlerinde ISO kodu gösterilir.
            ->assertSee('20.000,00 RUB')
            ->assertSee('450,00 GEL');
    }

    #[Test]
    public function hareket_ekle_ekrani_tum_aktif_kategorileri_sunar(): void
    {
        $user = $this->personel();
        $this->openSession($user);

        $response = $this->actingAs($user)->get(route('cashbook.movements.create'))->assertOk();

        // v2.1.1-B: form artık sabit CashMovementType listesini değil, admin
        // tarafından yönetilen (seeder ile varsayılanları oluşturulmuş)
        // kategori adlarını sunar.
        foreach (CashMovementCategory::query()->where('is_active', true)->get() as $category) {
            $response->assertSee($category->name);
        }

        // Mobil sayı klavyesi.
        $response->assertSee('inputmode="decimal"', false);
    }

    #[Test]
    public function devir_ekrani_ve_kabul_karti_calisir(): void
    {
        $x = $this->personel('Ayşe');
        $y = $this->personel('Zeynep');
        $session = $this->openSession($x, ['TRY' => '5000.00']);

        $this->actingAs($x)->get(route('cashbook.transfers.create'))
            ->assertOk()
            ->assertSee('Zeynep')
            ->assertSee('Teslim edilen tutar');

        $this->actingAs($x)->post(route('cashbook.transfers.store'), [
            'to_user_id' => $y->id,
            'amounts' => ['TRY' => '5.000,00'],
        ])->assertRedirect(route('cashbook.index'));

        // Zeynep'in ekranında kabul kartı görünür.
        $this->actingAs($y)->get(route('cashbook.index'))
            ->assertOk()
            ->assertSee('kasayı size devretmek istiyor', false)
            ->assertSee('Devri kabul et')
            ->assertSee('5.000,00 ₺');
    }

    #[Test]
    public function kapanis_ekrani_beklenen_tutari_gosterir_ve_sayim_kaydedilir(): void
    {
        $user = $this->personel();
        $session = $this->openSession($user, ['TRY' => '1000.00']);

        $this->actingAs($user)->get(route('cashbook.sessions.close', $session))
            ->assertOk()
            ->assertSee('Sayılan tutar')
            ->assertSee('1.000,00 ₺');

        $this->actingAs($user)->patch(route('cashbook.sessions.close.store', $session), [
            'amounts' => ['TRY' => '950,00'],
        ])->assertRedirect(route('cashbook.sessions.show', $session));

        $this->actingAs($user)->get(route('cashbook.sessions.show', $session))
            ->assertOk()
            ->assertSee('Kapanış sayımı')
            ->assertSee('-50,00 ₺')
            ->assertSee('eksik');
    }

    #[Test]
    public function devir_raporu_yazdirma_ekrani_koyu_temayi_yuklemez(): void
    {
        $user = $this->personel('Mehmet');
        $session = $this->openSession($user, ['TRY' => '1000.00']);
        $this->record($session, CashMovementType::RoomIncome, '500.00', $user);
        $this->cash->closeSession($session, $this->amounts(['TRY' => '1500.00']), $user);

        $this->actingAs($user)->get(route('cashbook.sessions.print', $session))
            ->assertOk()
            ->assertSee('Kasa Devir Raporu')
            ->assertSee('Mehmet')
            ->assertSee('1.500,00 ₺')
            // Yazdırma görünümü kenar çubuğunu ve uygulama CSS'ini taşımaz.
            ->assertDontSee('data-sidebar', false)
            ->assertDontSee('data-bottom-nav', false);
    }

    #[Test]
    public function gunluk_defter_vardiyalari_listeler(): void
    {
        $x = $this->personel('Mehmet');
        $y = $this->personel('Ayşe');

        $xSession = $this->openSession($x, ['TRY' => '1000.00']);
        $transfer = $this->cash->startHandover($xSession, $y, $this->amounts(['TRY' => '1000.00']), $x);
        $this->cash->acceptHandover($transfer, $y);

        $this->actingAs($this->patron())->get(route('cashbook.day'))
            ->assertOk()
            ->assertSee('Mehmet')
            ->assertSee('Ayşe')
            ->assertSee('2 vardiya');
    }

    // ------------------------------------------------------ sorgu bütçesi

    /**
     * Günlük defter sorgu sayısı VARDİYA SAYISIYLA BÜYÜMEZ.
     *
     * Her vardiya için ayrı özet sorgusu atmak klasik N+1'dir; toplu sorgu
     * bunu engeller. İki ölçüm arasında `flushQueryLog()` şarttır:
     * `enableQueryLog()` mevcut günlüğü TEMİZLEMEZ (DashboardTest'te aynı
     * tuzağa düşülmüştü).
     */
    #[Test]
    public function gunluk_defter_sorgu_sayisi_vardiya_sayisiyla_buyumez(): void
    {
        $patron = $this->patron();

        $measure = function () use ($patron): int {
            // Yetki önbelleğini ısıt: ölçüm yalnızca ekran sorgularını görsün.
            $this->actingAs($patron)->get(route('cashbook.day'));

            DB::enableQueryLog();
            DB::flushQueryLog();
            $this->actingAs($patron)->get(route('cashbook.day'))->assertOk();
            $count = count(DB::getRawQueryLog());
            DB::disableQueryLog();

            return $count;
        };

        $this->buildSessions(1);
        $withOne = $measure();

        $this->buildSessions(4);
        $withFive = $measure();

        $this->assertSame(
            $withOne,
            $withFive,
            "Günlük defter 1 vardiyada {$withOne}, 5 vardiyada {$withFive} sorgu çalıştırdı (N+1)."
        );
    }

    /** Ardışık devirlerle kapalı vardiya zinciri üretir. */
    private function buildSessions(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $current = $this->cash->activeSession();

            if ($current === null) {
                $from = $this->personel('Zincir '.$i.'a');
                $current = $this->openSession($from, ['TRY' => '100.00']);
            } else {
                $from = User::findOrFail($current->opened_by);
            }

            $to = $this->personel('Zincir '.$i.'b');
            $transfer = $this->cash->startHandover($current, $to, $this->amounts(['TRY' => '100.00']), $from);
            $this->cash->acceptHandover($transfer, $to);
        }
    }

    // ---------------------------------------------------- pano entegrasyonu

    #[Test]
    public function panoda_kasada_su_an_karti_gercek_veriyi_gosterir(): void
    {
        $personel = $this->personel('Mehmet');
        $session = $this->openSession($personel, ['TRY' => '7000.00', 'EUR' => '300.00']);
        $this->record($session, CashMovementType::RoomIncome, '1000.00', $personel);

        // Gün 11: kasa kartı Operasyon görünümündedir (?view=operations —
        // Ana Sayfa'nın varsayılanı artık Finans'tır, kullanıcı talimatı).
        $this->actingAs($this->patron())->get(route('home', ['view' => 'operations']))
            ->assertOk()
            ->assertSee('Kasada')
            ->assertSee('Mehmet')
            ->assertSee('8.000,00 ₺')
            ->assertSee('300,00 €')
            // 2 açılış hareketi (TRY + EUR) + 1 oda geliri
            ->assertSee('3 hareket');
    }

    #[Test]
    public function acik_vardiya_yokken_pano_karti_hic_cizilmez(): void
    {
        $this->actingAs($this->patron())->get(route('home', ['view' => 'operations']))
            ->assertOk()
            ->assertSee('Kasa Kapalı')
            ->assertDontSee('Kasada');
    }

    /**
     * Gün 11: personel panosu artık "Vardiyam / Kasam" mantığıyla çalışır —
     * personel kendi ELİNDEKİ kasanın tutarını görür (CLAUDE.md §8/Kasa
     * Defteri: "buradaki tutar personelin ELİNDEKİ paradır ve saymak
     * zorundadır"). Bu, §7.4'teki "personel firma cari bakiyesini görmez"
     * kuralıyla ÇELİŞMEZ: farklı bir para kategorisidir. Yasak olan,
     * BAŞKASININ vardiyasının tutarını görmektir (ayrı testle korunur).
     */
    #[Test]
    public function personel_panosunda_kendi_kasasinin_tutari_gosterilir(): void
    {
        $personel = $this->personel('Mehmet');
        $this->openSession($personel, ['TRY' => '7000.00']);

        $this->actingAs($personel)->get(route('home'))
            ->assertOk()
            ->assertSee('Kasam')
            ->assertSee('7.000,00');
    }

    // ---------------------------------------------------------- gezinme

    #[Test]
    public function mobil_alt_gezinme_kasayi_gosterir_konaklamalar_daha_fazlaya_tasinir(): void
    {
        $response = $this->actingAs($this->admin())->get(route('home'))->assertOk();

        $html = $response->getContent();
        $this->assertIsString($html);

        preg_match('/<nav data-bottom-nav.*?<\/nav>/s', $html, $matches);
        $this->assertNotEmpty($matches, 'Alt gezinme çubuğu bulunamadı');

        $bottomNav = $matches[0];

        // Gün 10 sırası: Ana Sayfa | Kasa | Firmalar | Tahsilatlar | Daha Fazla
        $this->assertStringContainsString('Kasa', $bottomNav);
        $this->assertStringNotContainsString('Konaklamalar', $bottomNav);

        // Konaklamalar "Daha Fazla" çekmecesinde (kenar çubuğunda) durmaya devam eder.
        $response->assertSee('Konaklamalar');
        $response->assertSee('Kasa Defteri');
    }

    #[Test]
    public function konaklama_ekranindayken_daha_fazla_sekmesi_aktiftir(): void
    {
        $html = $this->actingAs($this->admin())->get(route('stays.index'))->getContent();
        $this->assertIsString($html);

        preg_match('/<nav data-bottom-nav.*?<\/nav>/s', $html, $matches);
        $this->assertNotEmpty($matches);

        // Konaklamalar artık çekmecede olduğu için aktiflik "Daha Fazla"dadır.
        $this->assertMatchesRegularExpression(
            '/aria-current="page"[^>]*>.*?Daha Fazla/s',
            preg_replace('/\s+/', ' ', $matches[0]) ?? '',
        );
    }

    #[Test]
    public function kasa_ekranindayken_kasa_sekmesi_aktiftir(): void
    {
        $this->openSession($this->personel());

        $html = $this->actingAs($this->admin())->get(route('cashbook.index'))->getContent();
        $this->assertIsString($html);

        preg_match('/<nav data-bottom-nav.*?<\/nav>/s', $html, $matches);
        $this->assertNotEmpty($matches);

        $this->assertMatchesRegularExpression(
            '/aria-current="page"[^>]*>.*?Kasa/s',
            preg_replace('/\s+/', ' ', $matches[0]) ?? '',
        );
    }

    #[Test]
    public function personel_gunluk_defter_menusunu_gormez(): void
    {
        $this->actingAs($this->personel())->get(route('home'))
            ->assertOk()
            ->assertSee('Kasa Defteri')
            ->assertDontSee('Günlük Defter');
    }
}
