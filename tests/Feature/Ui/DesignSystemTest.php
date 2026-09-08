<?php

declare(strict_types=1);

namespace Tests\Feature\Ui;

use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Companies\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tasarım sistemi ve erişilebilirlik iskeletinin render testleri.
 *
 * İş kuralı doğrulamaz; yalnızca her ekranın ortak kabuğu doğru kurduğunu kontrol eder.
 */
class DesignSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    #[Test]
    public function kenar_cubugu_yetkiye_duyarlidir(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $personel = $this->userWithRole(RoleName::Personel);

        $this->actingAs($admin)->get(route('home'))
            ->assertSee('Firmalar')
            ->assertSee('Konaklamalar')
            ->assertSee('Kullanıcılar')
            ->assertSee('Roller / Yetkiler')
            ->assertSee('Denetim Kayıtları');

        // Personel yönetim menülerini HİÇ görmez (gizlenmez, render edilmez).
        $this->actingAs($personel)->get(route('home'))
            ->assertSee('Firmalar')
            ->assertSee('Konaklamalar')
            ->assertDontSee('Kullanıcılar')
            ->assertDontSee('Roller / Yetkiler')
            ->assertDontSee('Denetim Kayıtları');
    }

    #[Test]
    public function patron_kullanici_ve_rol_menusunu_gormez_denetimi_gorur(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Patron))->get(route('home'))
            ->assertDontSee('Kullanıcılar')
            ->assertDontSee('Roller / Yetkiler')
            ->assertSee('Denetim Kayıtları');
    }

    #[Test]
    public function aktif_menu_ogesi_isaretlenir(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Admin))
            ->get(route('companies.index'))
            ->assertSee('aria-current="page"', false);
    }

    #[Test]
    public function kabuk_erisilebilirlik_iskeletini_kurar(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Admin))->get(route('home'))
            ->assertSee('İçeriğe atla')
            ->assertSee('id="icerik"', false)
            ->assertSee('aria-label="Ana menü"', false)
            ->assertSee('aria-label="Menüyü aç"', false)
            ->assertSee('lang="tr"', false);
    }

    #[Test]
    public function form_alanlarinin_etiketi_input_ile_eslesir(): void
    {
        $response = $this->actingAs($this->userWithRole(RoleName::Admin))
            ->get(route('companies.create'));

        foreach (['name', 'tax_number', 'city', 'notes'] as $field) {
            $response->assertSee('for="'.$field.'"', false);
            $response->assertSee('id="'.$field.'"', false);
        }
    }

    #[Test]
    public function dogrulama_hatasi_aria_invalid_isaretler(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $this->actingAs($admin)->post(route('companies.store'), ['name' => '']);

        $this->actingAs($admin)->get(route('companies.create'))
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('id="name-error"', false);
    }

    #[Test]
    public function tutar_kolonlari_tabular_nums_kullanir(): void
    {
        Company::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::Admin))
            ->get(route('stays.index'))
            ->assertSee('tabular-nums', false);
    }

    #[Test]
    public function sayfalama_turkce_ve_erisilebilir(): void
    {
        Company::factory()->count(30)->create();

        $this->actingAs($this->userWithRole(RoleName::Admin))
            ->get(route('companies.index'))
            ->assertSee('aria-label="Sayfalama"', false)
            ->assertSee('Sonraki')
            ->assertSee('kayıt');
    }

    #[Test]
    public function giris_ekrani_kabugu_dogru(): void
    {
        $this->get(route('login'))
            ->assertSee('for="email"', false)
            ->assertSee('for="password"', false)
            ->assertSee('theme-color', false)
            ->assertDontSee('aria-label="Ana menü"', false);
    }

    #[Test]
    public function tema_tektir_ve_degistirilemez(): void
    {
        $response = $this->actingAs($this->userWithRole(RoleName::Admin))->get(route('home'));

        // Tema değiştirici, koyu tema veya kullanıcı tercihi yoktur.
        $response->assertDontSee('theme-switch', false);
        $response->assertDontSee('prefers-color-scheme', false);

        // Mobil tarayıcı çubuğu kenar çubuğuyla aynı yüzeyi kullanır.
        $response->assertSee('content="#172033"', false);
    }

    #[Test]
    public function blade_icinde_ham_hex_rengi_yazilmaz(): void
    {
        // Tek renk kaynağı resources/css/app.css içindeki @theme bloğudur
        // (CLAUDE.md §11.2). Bileşenlerde ham hex, token'ları anlamsızlaştırır.
        $offenders = [];

        foreach (['resources/views', 'app/Modules'] as $root) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(base_path($root))
            );

            foreach ($files as $file) {
                if (! str_ends_with((string) $file, '.blade.php')) {
                    continue;
                }

                // Yazdırma görünümü bilinçli olarak kendi kendine yeten sade CSS
                // taşır ve Vite çıktısını yüklemez (CLAUDE.md §8 / Ledger).
                if (str_contains((string) $file, 'print.blade.php')) {
                    continue;
                }

                // `<meta name="theme-color">` CSS token'ı kabul etmez; tarayıcı
                // çubuğu rengi HTML'de düz değer olmak zorundadır. Tek muafiyet budur.
                $contents = (string) preg_replace('/^.*theme-color.*$/m', '', (string) file_get_contents((string) $file));

                if (preg_match('/#[0-9a-fA-F]{3,8}\b/', $contents) === 1) {
                    $offenders[] = str_replace(base_path().'/', '', (string) $file);
                }
            }
        }

        $this->assertSame([], $offenders, 'Blade dosyalarında ham hex rengi bulundu.');
    }
}
