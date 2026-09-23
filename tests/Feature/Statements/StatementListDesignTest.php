<?php

declare(strict_types=1);

namespace Tests\Feature\Statements;

use App\Models\User;
use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Companies\Models\Company;
use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
use App\Modules\Core\Support\TurkishSuffix;
use App\Modules\Ledger\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cari Ekstre firma listesi yeni tasarımı (23 Eylül 2026): hareketi olanlar
 * üstte, durum/son hareket filtreleri, özet kartları ve satır menüsü.
 */
class StatementListDesignTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        Carbon::setTestNow(Carbon::create(2026, 9, 23, 10, 30));
        $this->admin = $this->userWithRole(RoleName::Admin);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function debit(Company $company, string $amount, string $date = '2026-09-01', Currency $currency = Currency::TRY): void
    {
        app(LedgerService::class)->debit(
            companyId: $company->id, amount: Money::fromDecimal($amount), currency: $currency,
            entryDate: $date, description: 'Konaklama', sourceType: 'stay', sourceId: 1, actor: $this->admin,
        );
    }

    private function credit(Company $company, string $amount, string $date = '2026-09-02', Currency $currency = Currency::TRY): void
    {
        app(LedgerService::class)->credit(
            companyId: $company->id, amount: Money::fromDecimal($amount), currency: $currency,
            entryDate: $date, description: 'Tahsilat', sourceType: 'payment', sourceId: 1, actor: $this->admin,
        );
    }

    private function index(array $query = []): TestResponse
    {
        return $this->actingAs($this->admin)->get(route('statements.index', $query));
    }

    /** @return array<int, string> */
    private function names(TestResponse $response): array
    {
        return $response->viewData('companies')->pluck('name')->all();
    }

    /**
     * Borçlu (C), kapalı (D), alacaklı (E) ve hareketsiz (A, B, F) hesaplar.
     *
     * @return array<string, Company>
     */
    private function seedAccounts(): array
    {
        $accounts = [];

        foreach (['A Hareketsiz', 'B Hareketsiz', 'C Borçlu', 'D Kapalı', 'E Alacaklı', 'F Hareketsiz'] as $name) {
            $accounts[$name] = Company::factory()->create(['name' => $name]);
        }

        $this->debit($accounts['C Borçlu'], '500.00', '2026-09-20');
        $this->debit($accounts['D Kapalı'], '300.00', '2026-05-01');
        $this->credit($accounts['D Kapalı'], '300.00', '2026-05-02');
        $this->credit($accounts['E Alacaklı'], '80.00', '2026-08-10');

        return $accounts;
    }

    #[Test]
    public function hareketi_olan_hesaplar_ustte_hareketsizler_altta_listelenir(): void
    {
        $this->seedAccounts();

        $this->assertSame(
            ['C Borçlu', 'D Kapalı', 'E Alacaklı', 'A Hareketsiz', 'B Hareketsiz', 'F Hareketsiz'],
            $this->names($this->index()),
        );

        // Ad Z→A seçilse de hareketi olanlar yine üstte kalır.
        $this->assertSame(
            ['E Alacaklı', 'D Kapalı', 'C Borçlu', 'F Hareketsiz', 'B Hareketsiz', 'A Hareketsiz'],
            $this->names($this->index(['sort' => 'name', 'direction' => 'desc'])),
        );
    }

    #[Test]
    public function son_harekete_gore_siralama_en_yeni_hareketi_uste_alir(): void
    {
        $this->seedAccounts();

        $this->assertSame(
            ['C Borçlu', 'E Alacaklı', 'D Kapalı', 'A Hareketsiz', 'B Hareketsiz', 'F Hareketsiz'],
            $this->names($this->index(['balance_sort' => 'recent'])),
        );
    }

    #[Test]
    public function durum_filtresi_ve_hizli_filtreler_calisir(): void
    {
        $this->seedAccounts();

        $this->assertSame(['C Borçlu'], $this->names($this->index(['durum' => 'borclu'])));
        $this->assertSame(['D Kapalı'], $this->names($this->index(['durum' => 'kapali'])));
        $this->assertSame(['E Alacaklı'], $this->names($this->index(['durum' => 'alacakli'])));
        $this->assertSame(['A Hareketsiz', 'B Hareketsiz', 'F Hareketsiz'], $this->names($this->index(['durum' => 'hareket-yok'])));
        // Tanınmayan durum yok sayılır.
        $this->assertCount(6, $this->names($this->index(['durum' => 'belgeler'])));
    }

    #[Test]
    public function son_hareket_filtresi_son_n_gunu_kapsar(): void
    {
        $this->seedAccounts();

        // Bugün 23.09.2026: son 30 gün 24.08'den beri → yalnız C (20.09).
        $this->assertSame(['C Borçlu'], $this->names($this->index(['son_hareket' => 30])));
        // Son 90 gün 25.06'dan beri → C ve E (10.08); D (02.05) dışarıda.
        $this->assertSame(['C Borçlu', 'E Alacaklı'], $this->names($this->index(['son_hareket' => 90])));
    }

    #[Test]
    public function para_birimi_secilince_durum_yalnizca_o_para_birimine_gore_hesaplanir(): void
    {
        $company = Company::factory()->create(['name' => 'Yalnız EUR']);
        $this->debit($company, '100.00', '2026-09-01', Currency::EUR);

        $response = $this->index(['currency' => 'TRY']);

        // TRY'de hareketi yok: hareketsiz sayılır, EUR bakiyesi TRY görünümüne sızmaz.
        $this->assertSame('hareket-yok', $response->viewData('statuses')[$company->id]);
        $response->assertDontSee('100,00 €');

        $this->assertSame('borclu', $this->index(['currency' => 'EUR'])->viewData('statuses')[$company->id]);
    }

    #[Test]
    public function ozet_kartlari_ve_tasarim_bloklari_gosterilir(): void
    {
        $this->seedAccounts();

        $response = $this->index(['durum' => 'borclu'])->assertOk();

        // Kartlar durum filtresinden bağımsız, arama kapsamının tamamını özetler.
        $this->assertSame(['total' => 6, 'debtors' => 1, 'closed' => 1, 'latest' => '2026-09-20'], $response->viewData('stats'));

        $response
            ->assertSee('Toplam firma')
            ->assertSee('Borçlu firmalar')
            ->assertSee("toplam firmaların %17'si")
            ->assertSee('Kapalı hesaplar')
            ->assertSee('Son hareket tarihi')
            ->assertSee('20.09.2026')
            ->assertSee('23 Eylül 2026')
            ->assertSee('Hızlı filtreler:')
            ->assertSee('Borçlu Firmalar')
            ->assertSee('Son 90 Gün')
            ->assertSee('Firma Listesi')
            ->assertSee('Firma adına göre (A → Z)')
            ->assertSee('data-statement-filters', false)
            ->assertDontSee('class="filter-panel', false);
    }

    #[Test]
    public function yuzde_eki_turkce_okunusa_gore_secilir(): void
    {
        $cases = [0 => 'ı', 1 => 'i', 5 => 'i', 6 => 'sı', 9 => 'u', 10 => 'u', 17 => 'si', 20 => 'si',
            31 => 'i', 40 => 'ı', 47 => 'si', 50 => 'si', 60 => 'ı', 70 => 'i', 80 => 'i', 90 => 'ı', 100 => 'ü'];

        foreach ($cases as $number => $suffix) {
            $this->assertSame($suffix, TurkishSuffix::possessive($number), "%{$number}");
        }
    }

    #[Test]
    public function satir_menusu_ekstre_ve_kisayollari_tasir(): void
    {
        $company = Company::factory()->create(['name' => 'Menü Firması']);
        $this->debit($company, '100.00');

        $this->index()
            ->assertSee('Menü Firması için işlem menüsü')
            ->assertSee('Ekstreyi aç')
            ->assertSee('Ekstreyi yazdır')
            ->assertSee('Cari hesap kartı')
            ->assertSee('Tahsilat al')
            ->assertSee(route('payments.create', ['company_id' => $company->id]), false);
    }

    #[Test]
    public function bakiye_siralamasi_para_birimi_yoksa_devre_disidir(): void
    {
        Company::factory()->create();

        $this->index()->assertSee('Önce bir para birimi seçin.');
        $this->index(['currency' => 'TRY'])->assertDontSee('Önce bir para birimi seçin.');
    }

    #[Test]
    public function yazdirma_gorunumu_ekranla_ayni_sirayi_ve_filtreyi_kullanir(): void
    {
        $this->seedAccounts();

        $this->assertSame(
            ['C Borçlu', 'D Kapalı', 'E Alacaklı', 'A Hareketsiz', 'B Hareketsiz', 'F Hareketsiz'],
            $this->actingAs($this->admin)->get(route('statements.print-index'))->viewData('companies')->pluck('name')->all(),
        );
        $this->assertSame(
            ['C Borçlu'],
            $this->actingAs($this->admin)->get(route('statements.print-index', ['durum' => 'borclu']))->viewData('companies')->pluck('name')->all(),
        );
    }

    #[Test]
    public function sorgu_sayisi_firma_sayisiyla_buyumez(): void
    {
        $this->seedAccounts();

        $measure = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->index(['durum' => 'borclu', 'son_hareket' => 90])->assertOk();
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $count;
        };

        $this->index()->assertOk();
        $before = $measure();

        foreach (Company::factory()->count(8)->create() as $company) {
            $this->debit($company, '50.00', '2026-09-15');
        }

        $this->assertSame($before, $measure());
    }
}
