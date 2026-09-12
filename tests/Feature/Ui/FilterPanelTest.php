<?php

declare(strict_types=1);

namespace Tests\Feature\Ui;

use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Companies\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Çökertilebilir filtre paneli (CLAUDE.md §9, K-106).
 *
 * İş kuralı doğrulamaz; yalnızca panelin her liste ekranında aynı kabuğu
 * kurduğunu ve açık/kapalı kararının SUNUCUDA doğru verildiğini kontrol eder.
 */
class FilterPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    /** @return array<string, array{0: string, 1: array<string, string>}> */
    public static function listeEkranlari(): array
    {
        return [
            'firmalar' => ['companies.index', ['q' => 'ALFA']],
            'konaklamalar' => ['stays.index', ['status' => 'active']],
            'tahsilatlar' => ['payments.index', ['currency' => 'TRY']],
            'cari ekstre' => ['statements.index', ['currency' => 'EUR']],
            'kasa hareketleri' => ['cashbook.movements.index', ['type' => 'expense']],
            'devir geçmişi' => ['cashbook.transfers.index', ['status' => 'pending']],
            'denetim kaydı' => ['audit.index', ['event' => 'login']],
        ];
    }

    /**
     * @param  array<string, string>  $filters
     */
    #[Test]
    #[DataProvider('listeEkranlari')]
    public function panel_filtresizken_kapali_filtreliyken_acik_baslar(string $route, array $filters): void
    {
        $user = $this->userWithRole(RoleName::Admin);

        $kapali = (string) $this->actingAs($user)->get(route($route))->getContent();
        $this->assertStringContainsString('class="filter-panel', $kapali, "Panel render edilmedi: {$route}");
        $this->assertFalse($this->panelAcikMi($kapali), "Filtresiz ekran açık başladı: {$route}");

        $acik = (string) $this->actingAs($user)->get(route($route, $filters))->getContent();
        $this->assertTrue($this->panelAcikMi($acik), "Filtreli ekran kapalı başladı: {$route}");
    }

    /** Cari ekstre DETAYI da aynı kabuğu kullanır (grid kapalı modda). */
    #[Test]
    public function ekstre_detayi_ayni_paneli_kullanir(): void
    {
        $user = $this->userWithRole(RoleName::Admin);
        $company = Company::factory()->create();

        $kapali = (string) $this->actingAs($user)->get(route('statements.show', $company))->getContent();
        $this->assertStringContainsString('class="filter-panel', $kapali);
        $this->assertFalse($this->panelAcikMi($kapali));

        $acik = (string) $this->actingAs($user)
            ->get(route('statements.show', [$company, 'date_from' => '2026-01-01']))
            ->getContent();
        $this->assertTrue($this->panelAcikMi($acik));
    }

    /**
     * Sayfalama ve sıralama filtre DEĞİLDİR: yalnız `per_page` ile gelen bir
     * istek paneli açmaz ve rozet göstermez.
     */
    #[Test]
    public function sayfalama_parametresi_filtre_sayilmaz(): void
    {
        $html = (string) $this->actingAs($this->userWithRole(RoleName::Admin))
            ->get(route('companies.index', ['per_page' => 50, 'sort' => 'name']))
            ->getContent();

        $this->assertFalse($this->panelAcikMi($html));

        // Rozet SUMMARY içinde aranır: sayfa başlığındaki "1 aktif firma"
        // alt metni bu kontrolü yanlışlıkla düşürmemelidir.
        $this->assertStringNotContainsString(' aktif<', $this->ozetCubugu($html));
    }

    /** Filtre dışı query param'lar (Firmalar → `trashed`) gizli input olarak taşınır. */
    #[Test]
    public function silinmisler_gorunumunde_trashed_gizli_input_tasinir(): void
    {
        $html = (string) $this->actingAs($this->userWithRole(RoleName::Admin))
            ->get(route('companies.index', ['trashed' => 1]))
            ->getContent();

        $this->assertStringContainsString('<input type="hidden" name="trashed" value="1">', $html);
    }

    /** Panelin özet çubuğu tek satırdır ve dokunma hedefi ≥ 44px'tir (CLAUDE.md §11.5). */
    #[Test]
    public function ozet_cubugu_dokunma_hedefini_karsilar(): void
    {
        $html = (string) $this->actingAs($this->userWithRole(RoleName::Admin))
            ->get(route('companies.index'))
            ->getContent();

        $this->assertMatchesRegularExpression('/<summary class="[^"]*min-h-11/', $html);
    }

    /** Filtre panelinin <summary> bloğu — rozet kontrolleri yalnız burada yapılır. */
    private function ozetCubugu(string $html): string
    {
        $this->assertMatchesRegularExpression('#<details class="filter-panel.*?</summary>#s', $html);
        preg_match('#<details class="filter-panel.*?</summary>#s', $html, $matches);

        return $matches[0];
    }

    private function panelAcikMi(string $html): bool
    {
        return (bool) preg_match('/<details class="filter-panel[^"]*"\s+open/', $html);
    }
}
