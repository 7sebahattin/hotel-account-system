<?php

declare(strict_types=1);

namespace Tests\Feature\Trash;

use App\Models\User;
use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Companies\Models\Company;
use App\Modules\Core\Enums\Currency;
use App\Modules\Ledger\Models\LedgerEntry;
use App\Modules\Ledger\Services\LedgerService;
use App\Modules\Stays\Models\Stay;
use App\Modules\Stays\Services\StayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Geçici "Ters Kayıt Temizliği" (15 Kasım 2026): orijinal + ters kayıt çifti
 * yalnızca Admin tarafından, birlikte ve bakiye değişmeden silinir.
 */
class ReversalCleanupTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Company $wrong;

    private Company $right;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->admin = $this->userWithRole(RoleName::Admin);
        $this->wrong = Company::factory()->create();
        $this->right = Company::factory()->create();
    }

    private function stay(Company $company, string $guest = 'Kelsey Anne Grady'): Stay
    {
        return app(StayService::class)->create([
            'company_id' => $company->id,
            'guest_name' => $guest,
            'check_in_date' => '2026-11-05',
            'check_out_date' => '2026-11-06',
            'amount' => '3600.00',
            'currency' => 'TRY',
        ], $this->admin);
    }

    /** Yanlış firmaya işlenip iptal edilen ve kaynak satırı artık olmayan konaklama. */
    private function orphanedCancelledStay(Company $company): void
    {
        $stay = $this->stay($company);
        app(StayService::class)->cancel($stay, $this->admin);
        DB::table('stays')->where('id', $stay->id)->delete();
    }

    private function balance(Company $company): string
    {
        return app(LedgerService::class)->balanceForCompany($company->id, Currency::TRY)->toDecimal();
    }

    #[Test]
    public function kaynagi_silinmis_ciftler_listelenir_ve_silinince_iki_firmada_da_kalmaz(): void
    {
        $this->orphanedCancelledStay($this->wrong);
        $this->orphanedCancelledStay($this->right);
        $kept = $this->stay($this->right, 'Paul Deely'); // dokunulmayacak geçerli borç

        $page = $this->actingAs($this->admin)->get(route('trash.reversals.index'))->assertOk();
        $pairs = $page->viewData('pairs');
        $this->assertCount(2, $pairs);
        $page->assertSee('Kaynak kayıt yok')->assertDontSee('Paul Deely');

        $this->actingAs($this->admin)
            ->delete(route('trash.reversals.destroy'), ['ids' => $pairs->map(fn (array $pair): int => $pair['reversal']->id)->all()])
            ->assertSessionHas('success');

        $this->assertSame(1, LedgerEntry::query()->count(), 'Yalnızca geçerli konaklamanın borcu kalır');
        $this->assertSame(0, LedgerEntry::query()->where('company_id', $this->wrong->id)->count());
        $this->assertSame('0.00', $this->balance($this->wrong));
        $this->assertSame('3600.00', $this->balance($this->right));
        $this->assertNotNull($kept->fresh());
        $this->assertDatabaseHas('audit_logs', ['event' => 'deleted', 'user_id' => $this->admin->id, 'auditable_type' => null]);
    }

    #[Test]
    public function kaynagi_duran_cift_yalnizca_tumu_gorunumunde_cikar_ve_silinebilir(): void
    {
        $stay = $this->stay($this->right);
        app(StayService::class)->cancel($stay, $this->admin);

        $this->assertCount(0, $this->actingAs($this->admin)->get(route('trash.reversals.index'))->viewData('pairs'));

        $pairs = $this->actingAs($this->admin)->get(route('trash.reversals.index', ['goster' => 'tumu']))->viewData('pairs');
        $this->assertCount(1, $pairs);
        $this->assertTrue($pairs[0]['source_exists']);

        $this->actingAs($this->admin)->delete(route('trash.reversals.destroy'), ['ids' => [$pairs[0]['reversal']->id]])
            ->assertSessionHas('success');
        $this->assertSame(0, LedgerEntry::query()->count());
        $this->assertTrue($stay->fresh()->isCancelled());
    }

    #[Test]
    public function ters_kaydi_olmayan_hareket_bu_yoldan_silinemez_ve_hicbir_sey_silinmez(): void
    {
        $this->orphanedCancelledStay($this->wrong);
        $active = $this->stay($this->right);
        $activeEntryId = (int) LedgerEntry::query()->where('source_id', $active->id)->value('id');
        $reversalId = (int) LedgerEntry::query()->where('is_reversal', true)->value('id');

        $this->actingAs($this->admin)
            ->delete(route('trash.reversals.destroy'), ['ids' => [$reversalId, $activeEntryId]])
            ->assertSessionHas('error');

        $this->assertSame(3, LedgerEntry::query()->count(), 'Reddedilen istekte hiçbir satır silinmez');
        $this->assertSame('3600.00', $this->balance($this->right));
    }

    #[Test]
    public function ekran_ve_silme_yalnizca_admine_aciktir(): void
    {
        $this->orphanedCancelledStay($this->wrong);
        $reversalId = (int) LedgerEntry::query()->where('is_reversal', true)->value('id');

        foreach ([RoleName::Patron, RoleName::Personel] as $role) {
            $user = $this->userWithRole($role);
            $this->actingAs($user)->get(route('trash.reversals.index'))->assertForbidden();
            $this->actingAs($user)->delete(route('trash.reversals.destroy'), ['ids' => [$reversalId]])->assertForbidden();
        }

        $this->assertSame(2, LedgerEntry::query()->count());
    }
}
