<?php

declare(strict_types=1);

namespace Tests\Feature\Stays;

use App\Models\User;
use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Companies\Models\Company;
use App\Modules\Core\Support\TurkishDate;
use App\Modules\Payments\Services\PaymentService;
use App\Modules\Stays\Models\Stay;
use App\Modules\Stays\Services\StayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Konaklama listesi yeni tasarımı (23 Eylül 2026): hızlı filtreler, özet
 * kartları, ödeme durumu rozeti, satır menüsü ve her zaman açık filtre kartı.
 */
class StayListDesignTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        Carbon::setTestNow(Carbon::create(2026, 9, 23, 10, 30));
        $this->admin = $this->userWithRole(RoleName::Admin);
        $this->company = Company::factory()->create(['name' => 'Liste Firması']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function stay(string $guest, string $in, string $out, string $amount = '1000.00', ?Company $company = null): Stay
    {
        return app(StayService::class)->create([
            'company_id' => ($company ?? $this->company)->id,
            'guest_name' => $guest,
            'check_in_date' => $in,
            'check_out_date' => $out,
            'pricing_mode' => 'total',
            'amount' => $amount,
            'currency' => 'TRY',
        ], $this->admin);
    }

    private function index(array $query = [], ?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->admin)->get(route('stays.index', $query));
    }

    /** @return array<int, string> */
    private function guests(TestResponse $response): array
    {
        $guests = $response->viewData('stays')->pluck('guest_name')->all();
        sort($guests);

        return $guests;
    }

    // --------------------------------------------------------- hızlı filtreler

    #[Test]
    public function tarih_hizli_filtreleri_giris_ve_cikis_gunune_gore_calisir(): void
    {
        $this->stay('Bu Ay', '2026-09-05', '2026-09-07');
        $this->stay('Geçen Ay', '2026-08-30', '2026-09-02');
        $this->stay('Bugün Giren', '2026-09-23', '2026-09-25');
        $this->stay('Bugün Çıkan', '2026-09-21', '2026-09-23');
        $this->stay('Otelde', '2026-09-22', '2026-09-24');

        $this->assertSame(['Bu Ay', 'Bugün Giren', 'Bugün Çıkan', 'Otelde'], $this->guests($this->index(['hizli' => 'bu-ay'])));
        $this->assertSame(['Geçen Ay'], $this->guests($this->index(['hizli' => 'gecen-ay'])));
        $this->assertSame(['Bugün Giren'], $this->guests($this->index(['hizli' => 'bugun-giris'])));
        $this->assertSame(['Bugün Çıkan'], $this->guests($this->index(['hizli' => 'bugun-cikis'])));
        // Bu gece otelde olanlar: çıkış günü gelen dahil değildir.
        $this->assertSame(['Bugün Giren', 'Otelde'], $this->guests($this->index(['hizli' => 'aktif'])));
    }

    #[Test]
    public function aktif_hizli_filtresi_iptal_edilen_konaklamayi_gostermez(): void
    {
        $stay = $this->stay('İptal Misafir', '2026-09-22', '2026-09-25');
        app(StayService::class)->cancel($stay, $this->admin);

        $this->assertSame([], $this->guests($this->index(['hizli' => 'aktif'])));
    }

    #[Test]
    public function odenmeyenler_kalan_tutari_olan_aktif_konaklamalardir(): void
    {
        $paid = $this->stay('Ödenmiş', '2026-09-01', '2026-09-02', '1000.00');
        $this->stay('Kısmi', '2026-09-03', '2026-09-04', '1000.00');
        $this->stay('Ödenmemiş', '2026-09-05', '2026-09-06', '1000.00');
        $cancelled = $this->stay('İptal', '2026-09-07', '2026-09-08', '1000.00');
        app(StayService::class)->cancel($cancelled, $this->admin);

        // En eskiden başlayarak dağıtılır: 1000 + 400.
        $payment = app(PaymentService::class)->create([
            'company_id' => $this->company->id,
            'payment_date' => '2026-09-10',
            'amount' => '1400.00',
            'currency' => 'TRY',
        ], $this->admin);

        $this->assertSame(['Kısmi', 'Ödenmemiş'], $this->guests($this->index(['hizli' => 'odenmeyen'])));
        $this->assertNotContains($paid->guest_name, $this->guests($this->index(['hizli' => 'odenmeyen'])));

        // Tahsilat iptal edilince dağılım ters kaydedilir; konaklama yeniden ödenmemiş olur.
        app(PaymentService::class)->cancel($payment, 'Yanlış giriş', $this->admin);

        $this->assertSame(['Kısmi', 'Ödenmemiş', 'Ödenmiş'], $this->guests($this->index(['hizli' => 'odenmeyen'])));
    }

    #[Test]
    public function hizli_filtre_diger_filtrelerle_birlikte_uygulanir_ve_gecersiz_deger_yok_sayilir(): void
    {
        $other = Company::factory()->create();
        $this->stay('Bizim', '2026-09-05', '2026-09-07');
        $this->stay('Diğer', '2026-09-05', '2026-09-07', '1000.00', $other);
        $this->stay('Eski', '2026-07-05', '2026-07-07');

        $this->assertSame(['Bizim'], $this->guests($this->index(['hizli' => 'bu-ay', 'company_id' => $this->company->id])));
        $this->assertCount(3, $this->guests($this->index(['hizli' => 'belgeler'])));
    }

    // -------------------------------------------------------------- ekran

    #[Test]
    public function liste_ozet_kartlarini_filtre_kartini_ve_odeme_rozetini_gosterir(): void
    {
        $this->stay('Rozet Misafir', '2026-09-01', '2026-09-04', '1500.00'); // 3 gece
        $this->stay('İkinci Misafir', '2026-09-10', '2026-09-11', '500.00'); // 1 gece

        $this->index()
            ->assertOk()
            ->assertSee('Toplam 2 konaklama kaydı')
            ->assertSee('Toplam konaklama')
            ->assertSee('TRY toplamı')
            ->assertSee('2.000,00 ₺')
            ->assertSee('Ortalama gece')
            ->assertSee('2,0')
            ->assertSee('23 Eylül 2026')
            ->assertSee('Çarşamba, 10:30')
            ->assertSee('Hızlı filtreler:')
            ->assertSee('Ödenmeyenler')
            ->assertSee('Konaklama Listesi')
            ->assertSee('Giriş tarihine göre (Yeni → Eski)')
            ->assertSee('Ödenmedi')
            ->assertSee('Kalan: 1.500,00 ₺')
            // Filtreler artık çökertilebilir kabukta değil, her zaman açık kartta.
            ->assertSee('data-stay-filters', false)
            ->assertDontSee('class="filter-panel', false);
    }

    #[Test]
    public function daha_fazla_filtre_kapali_baslar_ve_secili_deger_sayisini_gosterir(): void
    {
        $this->stay('Misafir', '2026-09-01', '2026-09-02');

        $plain = (string) $this->index()->getContent();
        $filtered = (string) $this->index(['currency' => 'TRY', 'per_page' => 50])->getContent();

        // Açık panel listeyi örterdi: iki durumda da kapalı başlar.
        $this->assertDoesNotMatchRegularExpression('/data-more-filters\s+open/', $plain);
        $this->assertDoesNotMatchRegularExpression('/data-more-filters\s+open/', $filtered);
        $this->assertMatchesRegularExpression('/Daha fazla filtre\s*<span[^>]*>2<\/span>/', $filtered);
        $this->assertDoesNotMatchRegularExpression('/Daha fazla filtre\s*<span/', $plain);
    }

    #[Test]
    public function satir_menusu_yetkiye_gore_ogeleri_gosterir(): void
    {
        $this->stay('Menü Misafiri', '2026-09-01', '2026-09-02');

        $this->index()
            ->assertSee('Menü Misafiri için işlem menüsü')
            ->assertSee('Düzenle')
            ->assertSee('Tahsilat al')
            ->assertSee('Cari ekstre');

        // Personel başkasının kaydını düzenleyemez ve ekstre göremez; tahsilat girebilir.
        $this->index([], $this->userWithRole(RoleName::Personel))
            ->assertSee('Tahsilat al')
            ->assertDontSee('Cari ekstre')
            ->assertDontSee('>Düzenle<', false);
    }

    #[Test]
    public function iptal_edilen_konaklamada_tahsilat_kisayolu_yoktur(): void
    {
        $stay = $this->stay('İptal Menü', '2026-09-01', '2026-09-02');
        app(StayService::class)->cancel($stay, $this->admin);

        $this->index()
            ->assertSee('İptal')
            ->assertDontSee('Tahsilat al');
    }

    #[Test]
    public function turkce_gun_ve_ay_adlari_yerel_ayardan_bagimsizdir(): void
    {
        $date = Carbon::create(2026, 9, 23);

        $this->assertSame('Çarşamba', TurkishDate::weekday($date));
        $this->assertSame('Çar', TurkishDate::shortWeekday($date));
        $this->assertSame('Paz', TurkishDate::shortWeekday(Carbon::create(2026, 9, 27)));
        $this->assertSame('23 Eylül 2026', TurkishDate::long($date));
        $this->assertSame('Eyl', TurkishDate::shortMonth($date));
    }
}
