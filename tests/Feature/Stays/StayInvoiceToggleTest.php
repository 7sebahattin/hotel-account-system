<?php

declare(strict_types=1);

namespace Tests\Feature\Stays;

use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Companies\Models\Company;
use App\Modules\Ledger\Models\LedgerEntry;
use App\Modules\Stays\Models\Stay;
use App\Modules\Stays\Services\StayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Fatura kesildi / kesilmedi takip işareti (4 Kasım 2026). */
class StayInvoiceToggleTest extends TestCase
{
    use RefreshDatabase;

    private function stay(): Stay
    {
        $this->seedRoles();

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

    #[Test]
    public function varsayilan_fatura_kesilmedi_ve_her_rol_degistirip_geri_alabilir(): void
    {
        $stay = $this->stay();
        $this->assertFalse($stay->isInvoiced());
        $ledgerCount = LedgerEntry::count();

        foreach ([RoleName::Admin, RoleName::Patron, RoleName::Personel] as $role) {
            $user = $this->userWithRole($role);
            $this->actingAs($user)->patch(route('stays.invoice', $stay), ['invoiced' => 1])->assertRedirect();
            $this->assertTrue($stay->fresh()->isInvoiced());
            $this->assertSame($user->id, $stay->fresh()->invoiced_by);

            $this->actingAs($user)->patch(route('stays.invoice', $stay), ['invoiced' => 0])->assertRedirect();
            $this->assertFalse($stay->fresh()->isInvoiced());
        }

        $this->assertSame($ledgerCount, LedgerEntry::count(), 'Cari hareketi değişmemeli.');
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => 'stay', 'auditable_id' => $stay->id, 'event' => 'updated']);
    }

    #[Test]
    public function iptal_edilmis_konaklamada_degismez(): void
    {
        $stay = $this->stay();
        app(StayService::class)->cancel($stay, $this->userWithRole(RoleName::Admin));

        $this->actingAs($this->userWithRole(RoleName::Admin))
            ->patch(route('stays.invoice', $stay), ['invoiced' => 1])->assertForbidden();
    }

    #[Test]
    public function liste_anahtari_ve_etiketi_gosterir(): void
    {
        $stay = $this->stay();
        $admin = $this->userWithRole(RoleName::Admin);

        $this->actingAs($admin)->get(route('stays.index'))
            ->assertSee('Fatura kesilmedi')->assertSee('role="switch"', false);

        app(StayService::class)->setInvoiced($stay, true, $admin);

        $this->actingAs($admin)->get(route('stays.index'))->assertSee('Fatura kesildi');
    }
}
