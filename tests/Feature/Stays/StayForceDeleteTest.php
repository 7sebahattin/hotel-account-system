<?php

declare(strict_types=1);

namespace Tests\Feature\Stays;

use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Companies\Models\Company;
use App\Modules\Ledger\Models\LedgerEntry;
use App\Modules\Payments\Services\PaymentService;
use App\Modules\Stays\Enums\StayStatus;
use App\Modules\Stays\Exceptions\StayHasLinkedAllocations;
use App\Modules\Stays\Exceptions\StayNotCancelled;
use App\Modules\Stays\Models\Stay;
use App\Modules\Stays\Services\StayService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Kalıcı silme (hard delete) — kullanıcı talimatıyla eklenen dar bir yetki
 * (StayPolicy::forceDelete). Yalnızca Admin + zaten İPTAL edilmiş kayıt.
 *
 * Ledger append-only'dir (CLAUDE.md §6/L1): bu testler, hard-delete'in
 * ledger_entries'e HİÇ dokunmadığını (satır sayısı değişmez) ayrıca doğrular.
 */
class StayForceDeleteTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_olmayan_kullanici_yetki_verilse_bile_kalici_silemez(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $patron = $this->userWithRole(RoleName::Patron);
        $patron->givePermissionTo('stays.force_delete');
        $stay = $this->makeStay();
        $this->stays->cancel($stay, $admin);
        $this->actingAs($patron)->delete(route('stays.destroy', $stay))->assertForbidden();
        $this->expectException(AuthorizationException::class);
        $this->stays->forceDelete($stay, $patron);
    }

    #[Test]
    public function bellekte_iptal_gorunen_aktif_kayit_kalici_silinemez(): void
    {
        $stay = $this->makeStay();
        $stay->status = StayStatus::Cancelled;
        $this->expectException(StayNotCancelled::class);
        $this->stays->forceDelete($stay, $this->userWithRole(RoleName::Admin));
    }

    private Company $company;

    private StayService $stays;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->company = Company::factory()->create();
        $this->stays = app(StayService::class);
    }

    private function makeStay(): Stay
    {
        return $this->stays->create([
            'company_id' => $this->company->id,
            'guest_name' => 'John Smith',
            'check_in_date' => '2026-09-07',
            'check_out_date' => '2026-09-10',
            'amount' => '9000.00',
            'currency' => 'TRY',
        ], $this->userWithRole(RoleName::Admin));
    }

    #[Test]
    public function admin_iptal_edilmis_kaydi_kalici_olarak_silebilir(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $stay = $this->makeStay();
        $this->stays->cancel($stay, $admin);
        $ledgerCountBefore = LedgerEntry::query()->count();

        $this->actingAs($admin)
            ->delete(route('stays.destroy', $stay))
            ->assertRedirect(route('stays.index'));

        $this->assertDatabaseMissing('stays', ['id' => $stay->id]);
        $this->assertSame($ledgerCountBefore, LedgerEntry::query()->count(), 'Ledger append-only kalır, hard-delete onu etkilemez');
    }

    #[Test]
    public function aktif_konaklama_kalici_olarak_silinemez(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $stay = $this->makeStay();

        $this->actingAs($admin)
            ->delete(route('stays.destroy', $stay))
            ->assertForbidden();

        $this->assertDatabaseHas('stays', ['id' => $stay->id]);

        $this->assertThrows(
            fn () => $this->stays->forceDelete($stay->fresh(), $admin),
            StayNotCancelled::class,
        );
    }

    #[Test]
    public function patron_ve_personel_kalici_silme_yapamaz(): void
    {
        $stay = $this->makeStay();
        $this->stays->cancel($stay, $this->userWithRole(RoleName::Admin));

        $patron = $this->userWithRole(RoleName::Patron);
        $this->actingAs($patron)->delete(route('stays.destroy', $stay->fresh()))->assertForbidden();

        $personel = $this->userWithRole(RoleName::Personel);
        $this->actingAs($personel)->delete(route('stays.destroy', $stay->fresh()))->assertForbidden();

        $this->assertDatabaseHas('stays', ['id' => $stay->id]);
    }

    /**
     * Kullanıcı talimatı (13 Eylül 2026): "kalıcı olarak sildiğim bir veri yok
     * olsun", "admin tarafından yapılan işlemler sadece audit de görünsün".
     *
     * Ledger satırları APPEND-ONLY olduğu için veritabanında KALIR (§6/L1) —
     * ama neti sıfır oldukları ve kaynak kayıt artık var olmadığı için cari
     * ekstrede GÖSTERİLMEZ. Bakiye ve denetim izi değişmez.
     */
    #[Test]
    public function kalici_silinen_konaklama_cari_ekstrede_gorunmez(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $stay = $this->makeStay();
        $this->stays->cancel($stay, $admin);

        $this->actingAs($admin)
            ->get(route('statements.show', $this->company))
            ->assertOk()
            ->assertSee('John Smith');

        $this->stays->forceDelete($stay->fresh(), $admin);

        // Satırlar deftere yazılı KALIR — yalnızca gösterimden düşer.
        $this->assertSame(2, LedgerEntry::query()->where('source_type', 'stay')->count());

        foreach (['statements.show', 'statements.print'] as $route) {
            $this->actingAs($admin)
                ->get(route($route, $this->company))
                ->assertOk()
                ->assertDontSee('John Smith')
                ->assertDontSee('Kaynak kayıt bulunamadı');
        }
    }

    /**
     * Güvenlik kapısı: gizleme YALNIZCA neti sıfır olan yetim gruplara
     * uygulanır. Neti sıfır olmayan bir yetim kayıt (ör. iptal edilmeden
     * ham SQL ile silinmiş bir satır) gizlenirse ekstrenin kapanış bakiyesi
     * defterin gerçek bakiyesinden (§6/L4) sessizce KOPARDI.
     */
    #[Test]
    public function neti_sifir_olmayan_yetim_kayit_gizlenmez(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $stay = $this->makeStay();

        // İptal EDİLMEDEN (ters kayıt yazılmadan) kaydı ham SQL ile düşür:
        // geriye tek yönlü, neti 9.000 olan bir yetim borç hareketi kalır.
        DB::table('stays')->where('id', $stay->id)->delete();

        $this->actingAs($admin)
            ->get(route('statements.show', $this->company))
            ->assertOk()
            ->assertSee('John Smith')
            ->assertSee('Kaynak kayıt bulunamadı');
    }

    #[Test]
    public function odeme_dagilimi_uygulanmis_kayit_silinemez(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $stay = $this->makeStay();

        // Konaklamayı tam ödeyen bir tahsilat — payment_allocations satırı üretir.
        app(PaymentService::class)->create([
            'company_id' => $this->company->id,
            'payment_date' => '2026-09-08',
            'amount' => '9000.00',
            'currency' => 'TRY',
            'payment_method' => 'bank_transfer',
        ], $admin);

        $this->stays->cancel($stay->fresh(), $admin);
        // Orijinal dağılım + iptal ters dağılımı, ikisi de kalır (append-only).
        $this->assertDatabaseCount('payment_allocations', 2);

        $this->assertThrows(
            fn () => $this->stays->forceDelete($stay->fresh(), $admin),
            StayHasLinkedAllocations::class,
        );

        $this->assertDatabaseHas('stays', ['id' => $stay->id]);

        $this->actingAs($admin)
            ->delete(route('stays.destroy', $stay->fresh()))
            ->assertRedirect();

        // HTTP üzerinden de silme reddedilir, kayıt kalır.
        $this->assertDatabaseHas('stays', ['id' => $stay->id]);
    }

    #[Test]
    public function kalici_silinmis_kayit_listede_gorunmez_ama_denetim_kaydi_kalir(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $stay = $this->makeStay();
        $this->stays->cancel($stay, $admin);
        $stayId = $stay->id;

        $this->stays->forceDelete($stay->fresh(), $admin);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => 'stay',
            'auditable_id' => $stayId,
            'event' => 'deleted',
        ]);

        $this->actingAs($admin)->get(route('stays.index'))->assertOk()->assertDontSee('John Smith');
    }
}
