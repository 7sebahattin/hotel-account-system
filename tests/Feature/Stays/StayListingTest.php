<?php

declare(strict_types=1);

namespace Tests\Feature\Stays;

use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Companies\Models\Company;
use App\Modules\Stays\Models\Stay;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StayListingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    /** @return array<int, string> */
    private function guests(TestResponse $response): array
    {
        return $response->viewData('stays')->pluck('guest_name')->all();
    }

    private function index(array $query = []): TestResponse
    {
        return $this->actingAs($this->userWithRole(RoleName::Admin))
            ->get(route('stays.index', $query));
    }

    #[Test]
    public function misafir_adi_ve_oda_uzerinden_arama(): void
    {
        Stay::factory()->create(['guest_name' => 'Ahmet Yılmaz', 'room_no' => '101']);
        Stay::factory()->create(['guest_name' => 'Mehmet Demir', 'room_no' => '202']);

        $this->assertSame(['Ahmet Yılmaz'], $this->guests($this->index(['q' => 'ahmet'])));
        $this->assertSame(['Mehmet Demir'], $this->guests($this->index(['q' => '202'])));
    }

    /**
     * Gün 12 §4: arama firma adını da kapsar. Firma ayrı bir tabloda olduğu
     * için `StayFilter::applySearch()` bunu `whereHas` ile ekler.
     */
    #[Test]
    public function firma_adi_uzerinden_arama(): void
    {
        $ilgaz = Company::factory()->create(['name' => 'Ilgaz Turizm']);
        $bergama = Company::factory()->create(['name' => 'Bergama Seyahat']);

        Stay::factory()->create(['company_id' => $ilgaz->id, 'guest_name' => 'Ilgaz Misafiri']);
        Stay::factory()->create(['company_id' => $bergama->id, 'guest_name' => 'Bergama Misafiri']);

        $this->assertSame(['Ilgaz Misafiri'], $this->guests($this->index(['q' => 'ilgaz'])));
    }

    #[Test]
    public function arama_turkce_i_farkini_gozetmez(): void
    {
        Stay::factory()->create(['guest_name' => 'IŞIL KAYA']);
        Stay::factory()->create(['guest_name' => 'Başka Misafir']);

        foreach (['ışıl', 'IŞIL', 'Işıl'] as $term) {
            $this->assertSame(['IŞIL KAYA'], $this->guests($this->index(['q' => $term])), "terim: {$term}");
        }
    }

    #[Test]
    public function firma_filtresi(): void
    {
        $a = Company::factory()->create();
        $b = Company::factory()->create();

        Stay::factory()->create(['company_id' => $a->id, 'guest_name' => 'A Misafiri']);
        Stay::factory()->create(['company_id' => $b->id, 'guest_name' => 'B Misafiri']);

        $this->assertSame(['A Misafiri'], $this->guests($this->index(['company_id' => $a->id])));
    }

    #[Test]
    public function tarih_araligi_filtresi(): void
    {
        Stay::factory()->create(['guest_name' => 'Ocak', 'check_in_date' => '2026-01-15', 'check_out_date' => '2026-01-18']);
        Stay::factory()->create(['guest_name' => 'Mart', 'check_in_date' => '2026-03-10', 'check_out_date' => '2026-03-12']);
        Stay::factory()->create(['guest_name' => 'Haziran', 'check_in_date' => '2026-06-01', 'check_out_date' => '2026-06-05']);

        $this->assertSame(['Mart'], $this->guests($this->index([
            'date_from' => '2026-02-01', 'date_to' => '2026-04-01',
        ])));

        $this->assertSame(
            ['Haziran', 'Mart'],
            $this->guests($this->index(['date_from' => '2026-02-01'])),
        );
    }

    #[Test]
    public function para_birimi_filtresi(): void
    {
        Stay::factory()->create(['guest_name' => 'TRY Misafiri', 'currency' => 'TRY']);
        Stay::factory()->create(['guest_name' => 'EUR Misafiri', 'currency' => 'EUR']);

        $this->assertSame(['EUR Misafiri'], $this->guests($this->index(['currency' => 'EUR'])));
        // 'USD' Gün 9.6'dan itibaren desteklenir; "geçersiz kod" örneği artık
        // enum kümesinin gerçekten dışında kalan bir değer olmalı.
        $this->assertCount(2, $this->guests($this->index(['currency' => 'CHF'])), 'Geçersiz para birimi filtre uygulamaz');
    }

    #[Test]
    public function durum_filtresi(): void
    {
        Stay::factory()->create(['guest_name' => 'Aktif Kayıt']);
        Stay::factory()->cancelled()->create(['guest_name' => 'İptal Kayıt']);

        $this->assertSame(['Aktif Kayıt'], $this->guests($this->index(['status' => 'active'])));
        $this->assertSame(['İptal Kayıt'], $this->guests($this->index(['status' => 'cancelled'])));
        $this->assertCount(2, $this->guests($this->index()));
    }

    #[Test]
    public function varsayilan_siralama_en_yeni_giris_ustte(): void
    {
        Stay::factory()->create(['guest_name' => 'Eski', 'check_in_date' => '2026-01-01', 'check_out_date' => '2026-01-03']);
        Stay::factory()->create(['guest_name' => 'Yeni', 'check_in_date' => '2026-06-01', 'check_out_date' => '2026-06-03']);

        $this->assertSame(['Yeni', 'Eski'], $this->guests($this->index()));
    }

    #[Test]
    public function siralama_beyaz_liste_disini_reddeder(): void
    {
        Stay::factory()->count(2)->create();

        $this->index(['sort' => 'amount); DROP TABLE stays --'])->assertOk();

        $this->assertSame(2, Stay::query()->count());
    }

    #[Test]
    public function sayfalama_varsayilan_25(): void
    {
        Stay::factory()->count(30)->create();

        $paginator = $this->index()->viewData('stays');

        $this->assertSame(25, $paginator->perPage());
        $this->assertCount(25, $paginator->items());
        $this->assertSame(30, $paginator->total());
    }

    #[Test]
    public function sayfa_boyutu_izinli_degerlerle_sinirli(): void
    {
        Stay::factory()->count(5)->create();

        foreach ([50 => 50, 100 => 100, 25 => 25, 7 => 25, 999 => 25] as $requested => $expected) {
            $this->assertSame($expected, $this->index(['per_page' => $requested])->viewData('stays')->perPage());
        }
    }

    #[Test]
    public function filtreler_sayfalama_baglantilarinda_korunur(): void
    {
        $company = Company::factory()->create();
        Stay::factory()->count(30)->create(['company_id' => $company->id]);

        $next = (string) $this->index(['company_id' => $company->id])->viewData('stays')->nextPageUrl();

        $this->assertStringContainsString('company_id='.$company->id, $next);
    }

    #[Test]
    public function firma_detayinda_konaklamalar_bolumu_gorunur(): void
    {
        $company = Company::factory()->create();
        Stay::factory()->create(['company_id' => $company->id, 'guest_name' => 'Firma Misafiri']);

        $this->actingAs($this->userWithRole(RoleName::Admin))
            ->get(route('companies.show', $company))
            ->assertOk()
            ->assertSee('Konaklamalar')
            ->assertSee('Firma Misafiri');
    }

    #[Test]
    public function masaustu_satiri_erisilebilir_tiklanabilir_ve_islem_zamanini_gosterir(): void
    {
        $creator = $this->userWithRole(RoleName::Admin);
        $creator->update(['name' => 'Sercan Çakmak']);
        $stay = Stay::factory()->create([
            'guest_name' => 'Örnek Misafir',
            'created_by' => $creator->id,
            'created_at' => '2026-09-08 15:42:00',
        ]);

        $response = $this->index()
            ->assertOk()
            ->assertDontSee('Görüntüle')
            ->assertSee('Sercan Çakmak')
            ->assertSee('08.09.2026 · 15:42');

        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.(string) $response->getContent());
        $xpath = new DOMXPath($dom);
        $this->assertSame(0, $xpath->query('//table//thead//th[normalize-space(.)="İşlemler"]')->length);
        $rows = $xpath->query('//table//tbody/tr[@data-stay-row-url]');

        $this->assertNotFalse($rows);
        $this->assertSame(1, $rows->length);
        $row = $rows->item(0);
        $this->assertInstanceOf(DOMElement::class, $row);
        $this->assertSame(route('stays.show', $stay), $row->getAttribute('data-stay-row-url'));
        $this->assertSame('0', $row->getAttribute('tabindex'));
        $this->assertSame('link', $row->getAttribute('role'));
        $this->assertSame(9, $xpath->query('//table//thead/tr/*')->length);
        // 23 Eylül 2026 tasarımı: satırın tamamı ayrıntıya gider; satırdaki
        // TEK etkileşimli öğe ⋮ menüsüdür ve bağlantıların hepsi onun içindedir.
        $rowLinks = $xpath->query('//table//tbody/tr[@data-stay-row-url]//a')->length;
        $this->assertSame($rowLinks, $xpath->query('//table//tbody/tr[@data-stay-row-url]//details[@data-stay-row-menu]//a')->length);
        // Fatura kesildi/kesilmedi anahtarı (4 Kasım 2026, kullanıcı talimatı) tek istisnadır.
        $this->assertSame(0, $xpath->query('//table//tbody/tr[@data-stay-row-url]//button[not(@role="switch")]')->length);
        $this->assertSame(1, $xpath->query('//div[contains(@class, "xl:hidden")]//a[@href="'.route('stays.show', $stay).'"]')->length);
    }
}
