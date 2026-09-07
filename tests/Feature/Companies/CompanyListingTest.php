<?php

declare(strict_types=1);

namespace Tests\Feature\Companies;

use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Companies\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Arama, filtre, sıralama, sayfalama (CLAUDE.md §9) ve Türkçe collation davranışı.
 */
class CompanyListingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function names(TestResponse $response): array
    {
        return $response->viewData('companies')->pluck('name')->all();
    }

    #[Test]
    public function unvana_gore_arama_yapilir(): void
    {
        Company::factory()->create(['name' => 'Ilgaz Turizm']);
        Company::factory()->create(['name' => 'Bergama Seyahat']);

        $response = $this->actingAs($this->userWithRole(RoleName::Admin))
            ->get(route('companies.index', ['q' => 'ilgaz']));

        $this->assertSame(['Ilgaz Turizm'], $this->names($response));
    }

    #[Test]
    public function arama_vergi_no_telefon_yetkili_ve_sehirde_de_calisir(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        // Aranabilir alanların tamamı açıkça verilir: factory'nin rastgele değerleri
        // (fake()->city() Türkçe şehir listesinden seçer) aranan terimle çakışabiliyordu.
        $base = ['tax_number' => '1000000000', 'phone' => '0500 000 00 00', 'contact_person' => 'Ali Veli', 'city' => 'Ankara', 'tax_office' => 'Çankaya'];

        Company::factory()->create(['name' => 'A Firma', ...$base, 'tax_number' => '9876543210']);
        Company::factory()->create(['name' => 'B Firma', ...$base, 'phone' => '0212 111 22 33']);
        Company::factory()->create(['name' => 'C Firma', ...$base, 'contact_person' => 'Mehmet Öz']);
        Company::factory()->create(['name' => 'D Firma', ...$base, 'city' => 'Trabzon']);

        foreach ([
            '9876543210' => 'A Firma',
            '111 22 33' => 'B Firma',
            'Mehmet Öz' => 'C Firma',
            'Trabzon' => 'D Firma',
        ] as $term => $expected) {
            $response = $this->actingAs($admin)->get(route('companies.index', ['q' => $term]));

            $this->assertSame([$expected], $this->names($response), "Arama terimi: {$term}");
        }
    }

    /**
     * Türkçe'de büyük harfe çevirim çift yönlüdür (i↔İ, ı↔I) ve firma verisi
     * çoğunlukla tümü büyük harfle girilir. Arama her iki yönde de çalışmalıdır.
     */
    #[Test]
    public function arama_noktali_ve_noktasiz_i_yazimlarinin_tumunu_bulur(): void
    {
        $yazimlar = ['ILGAZ TURİZM', 'Ilgaz Turizm', 'ilgaz turizm', 'İLGAZ TURİZM', 'ılgaz turizm'];

        foreach ($yazimlar as $name) {
            Company::factory()->create(['name' => $name]);
        }

        foreach (['ilgaz', 'ILGAZ', 'Ilgaz', 'İLGAZ', 'ılgaz', 'İlgaz'] as $term) {
            $response = $this->actingAs($this->userWithRole(RoleName::Admin))
                ->get(route('companies.index', ['q' => $term]));

            $this->assertCount(
                count($yazimlar),
                $this->names($response),
                "'{$term}' araması tüm yazımları bulmalı",
            );
        }
    }

    /**
     * Ters yön: veri ALL CAPS ('ÇINAR' içinde noktasız I), kullanıcı küçük
     * harfle 'çınar' yazıyor. utf8mb4_unicode_ci tek başına bunu bulamaz.
     */
    #[Test]
    public function all_caps_kaydedilmis_firma_kucuk_harfle_bulunur(): void
    {
        Company::factory()->create(['name' => 'ÇINAR TURİZM']);
        Company::factory()->create(['name' => 'Çınar Turizm']);
        Company::factory()->create(['name' => 'Bergama Seyahat']);

        foreach (['çınar', 'ÇINAR', 'Çınar', 'çinar'] as $term) {
            $response = $this->actingAs($this->userWithRole(RoleName::Admin))
                ->get(route('companies.index', ['q' => $term]));

            $this->assertCount(2, $this->names($response), "arama terimi: {$term}");
        }
    }

    /**
     * Sıralama kolonun kendi utf8mb4_turkish_ci collation'ını kullanır:
     * Ç>C, ğ>g, ı<i, Ö>O, Ş>S, Ü>U.
     */
    #[Test]
    public function siralama_turkce_alfabeye_gore_yapilir(): void
    {
        foreach (['Zeytin', 'Çınar', 'Ilgaz', 'İstanbul', 'Şahin', 'Ören', 'Ünal', 'Güneş'] as $name) {
            Company::factory()->create(['name' => $name]);
        }

        $response = $this->actingAs($this->userWithRole(RoleName::Admin))
            ->get(route('companies.index', ['sort' => 'name', 'direction' => 'asc']));

        $this->assertSame(
            ['Çınar', 'Güneş', 'Ilgaz', 'İstanbul', 'Ören', 'Şahin', 'Ünal', 'Zeytin'],
            $this->names($response),
        );
    }

    #[Test]
    public function sehir_ve_durum_filtreleri_calisir(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        Company::factory()->create(['name' => 'Ankara Aktif', 'city' => 'Ankara', 'is_active' => true]);
        Company::factory()->create(['name' => 'Ankara Pasif', 'city' => 'Ankara', 'is_active' => false]);
        Company::factory()->create(['name' => 'İzmir Aktif', 'city' => 'İzmir', 'is_active' => true]);

        $this->assertCount(2, $this->names(
            $this->actingAs($admin)->get(route('companies.index', ['city' => 'Ankara']))
        ));

        $this->assertSame(['Ankara Pasif'], $this->names(
            $this->actingAs($admin)->get(route('companies.index', ['city' => 'Ankara', 'is_active' => '0']))
        ));

        $this->assertCount(3, $this->names(
            $this->actingAs($admin)->get(route('companies.index', ['is_active' => '']))
        ));
    }

    #[Test]
    public function sayfalama_varsayilan_25_kayittir(): void
    {
        Company::factory()->count(30)->create();

        $response = $this->actingAs($this->userWithRole(RoleName::Admin))->get(route('companies.index'));

        $paginator = $response->viewData('companies');

        $this->assertSame(25, $paginator->perPage());
        $this->assertCount(25, $paginator->items());
        $this->assertSame(30, $paginator->total());
        $this->assertSame(2, $paginator->lastPage());
    }

    #[Test]
    public function sayfa_boyutu_yalnizca_izinli_degerleri_kabul_eder(): void
    {
        Company::factory()->count(5)->create();
        $admin = $this->userWithRole(RoleName::Admin);

        foreach ([50 => 50, 100 => 100, 25 => 25, 999 => 25, 7 => 25] as $requested => $expected) {
            $response = $this->actingAs($admin)->get(route('companies.index', ['per_page' => $requested]));

            $this->assertSame($expected, $response->viewData('companies')->perPage(), "istek: {$requested}");
        }
    }

    #[Test]
    public function siralama_beyaz_liste_disindaki_kolonu_reddeder(): void
    {
        Company::factory()->create(['name' => 'Bir']);
        Company::factory()->create(['name' => 'Aki']);

        // Beyaz listede olmayan kolon varsayılana (name asc) düşer.
        $response = $this->actingAs($this->userWithRole(RoleName::Admin))
            ->get(route('companies.index', ['sort' => 'notes) --', 'direction' => 'asc']));

        $response->assertOk();
        $this->assertSame(['Aki', 'Bir'], $this->names($response));
    }

    #[Test]
    public function arama_joker_karakterleri_metin_olarak_ele_alir(): void
    {
        Company::factory()->create(['name' => 'Yüzde %100 Turizm']);
        Company::factory()->create(['name' => 'Başka Firma']);

        $response = $this->actingAs($this->userWithRole(RoleName::Admin))
            ->get(route('companies.index', ['q' => '%100']));

        $this->assertSame(['Yüzde %100 Turizm'], $this->names($response));
    }

    #[Test]
    public function filtreler_sayfalama_baglantilarinda_korunur(): void
    {
        Company::factory()->count(30)->create(['city' => 'Bursa']);

        $response = $this->actingAs($this->userWithRole(RoleName::Admin))
            ->get(route('companies.index', ['city' => 'Bursa', 'q' => '']));

        $this->assertStringContainsString('city=Bursa', (string) $response->viewData('companies')->nextPageUrl());
    }
}
