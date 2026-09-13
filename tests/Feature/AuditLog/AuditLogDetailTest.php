<?php

declare(strict_types=1);

namespace Tests\Feature\AuditLog;

use App\Modules\AuditLog\Models\AuditLog;
use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Companies\Models\Company;
use App\Modules\Stays\Models\Stay;
use App\Modules\Stays\Services\StayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Denetim kaydı detay ekranı (kullanıcı talimatı, 13 Eylül 2026):
 * "listede kayda tıklayıp yapılan işlem görüntülenebilir olsun".
 *
 * Ekran SALT OKUNURDUR — yeni bir yetki AÇILMADI, liste ile aynı
 * `audit.view` yetkisine bağlıdır.
 */
class AuditLogDetailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    #[Test]
    public function liste_her_satir_icin_detay_baglantisi_verir(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $this->makeStayAudit();
        $log = AuditLog::query()->latest('id')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('audit.index'))
            ->assertOk()
            ->assertSee(route('audit.show', $log), escape: false);
    }

    #[Test]
    public function detay_ekrani_eski_ve_yeni_degerleri_gosterir(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $stay = $this->makeStayAudit();

        app(StayService::class)->update($stay->fresh(), [
            'company_id' => $stay->company_id,
            'guest_name' => 'Jane Doe',
            'check_in_date' => '2026-09-07',
            'check_out_date' => '2026-09-10',
            'pricing_mode' => 'total',
            'amount' => '9000.00',
            'currency' => 'TRY',
        ], $admin);

        $log = AuditLog::query()->where('auditable_type', 'stay')->latest('id')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('audit.show', $log))
            ->assertOk()
            ->assertSee('Konaklama')          // morph anahtarı Türkçe etikete çevrilir
            ->assertSee('guest_name')
            ->assertSee('John Smith')          // eski değer
            ->assertSee('Jane Doe');           // yeni değer
    }

    #[Test]
    public function patron_gorebilir_personel_goremez(): void
    {
        $this->makeStayAudit();
        $log = AuditLog::query()->latest('id')->firstOrFail();

        $this->actingAs($this->userWithRole(RoleName::Patron))
            ->get(route('audit.show', $log))
            ->assertOk();

        $this->actingAs($this->userWithRole(RoleName::Personel))
            ->get(route('audit.show', $log))
            ->assertForbidden();
    }

    /** Detay ekranı SALT OKUNURDUR: yazma/silme ucu yoktur. */
    #[Test]
    public function detay_ekraninin_yazma_veya_silme_ucu_yoktur(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $this->makeStayAudit();
        $log = AuditLog::query()->latest('id')->firstOrFail();

        foreach (['put', 'patch', 'delete', 'post'] as $method) {
            $this->actingAs($admin)
                ->call(strtoupper($method), route('audit.show', $log))
                ->assertStatus(405);
        }
    }

    private function makeStayAudit(): Stay
    {
        return app(StayService::class)->create([
            'company_id' => Company::factory()->create()->id,
            'guest_name' => 'John Smith',
            'check_in_date' => '2026-09-07',
            'check_out_date' => '2026-09-10',
            'pricing_mode' => 'total',
            'amount' => '9000.00',
            'currency' => 'TRY',
        ], $this->userWithRole(RoleName::Admin));
    }
}
