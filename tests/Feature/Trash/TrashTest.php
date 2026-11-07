<?php

declare(strict_types=1);

namespace Tests\Feature\Trash;

use App\Models\User;
use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Companies\Models\Company;
use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Trash\FinancialPurgeGate;
use App\Modules\Ledger\Models\LedgerEntry;
use App\Modules\Ledger\Services\LedgerService;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Services\PaymentService;
use App\Modules\ReceiptBook\Models\ReceiptBookEntry;
use App\Modules\Rooms\Models\Room;
use App\Modules\Stays\Models\Stay;
use App\Modules\Stays\Services\StayService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Ortak Çöp Kutusu (6 Kasım 2026): her tür kendi grubunda; geri alma; kalıcı silme
 * yalnızca Admin'de ve tahsilat/konaklamada bağlı defter + dağılım satırlarıyla KOMPLE.
 */
class TrashTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->admin = $this->userWithRole(RoleName::Admin);
        $this->company = Company::factory()->create();
    }

    private function stay(string $amount = '9000.00', string $guest = 'John Smith'): Stay
    {
        return app(StayService::class)->create([
            'company_id' => $this->company->id,
            'guest_name' => $guest,
            'check_in_date' => '2026-09-07',
            'check_out_date' => '2026-09-10',
            'amount' => $amount,
            'currency' => 'TRY',
        ], $this->admin);
    }

    private function payment(string $amount = '5000.00'): Payment
    {
        return app(PaymentService::class)->create([
            'company_id' => $this->company->id,
            'payment_date' => '2026-09-08',
            'amount' => $amount,
            'currency' => 'TRY',
            'payment_method' => 'bank_transfer',
        ], $this->admin);
    }

    private function balance(): string
    {
        return app(LedgerService::class)->balanceForCompany($this->company->id, Currency::TRY)->toDecimal();
    }

    // ---------------------------------------------------------------- tahsilat

    #[Test]
    public function aktif_tahsilat_silinince_once_iptal_edilir_sonra_cope_girer_ve_herkes_silebilir(): void
    {
        foreach ([RoleName::Admin, RoleName::Patron, RoleName::Personel] as $role) {
            $payment = $this->payment();

            $this->actingAs($this->userWithRole($role))
                ->patch(route('payments.move-to-trash', $payment))
                ->assertRedirect(route('payments.index'));

            $fresh = $payment->fresh();
            $this->assertTrue($fresh->isTrashed());
            $this->assertTrue($fresh->isCancelled(), 'Aktif tahsilat önce iptal edilmeli');
        }

        $this->assertSame('0.00', $this->balance(), 'İptal + çöp bakiyeyi bozmaz');
        $this->assertSame(0, Payment::query()->whereNull('trashed_at')->count());
    }

    #[Test]
    public function coptaki_tahsilat_listeden_kalkar_ve_cop_kutusunda_kendi_grubunda_gorunur(): void
    {
        $payment = $this->payment();
        app(PaymentService::class)->trash($payment, $this->admin);

        $index = $this->actingAs($this->admin)->get(route('payments.index'));
        $this->assertCount(0, $index->viewData('payments')->items());

        $trash = $this->actingAs($this->admin)->get(route('trash.index', ['tur' => 'payments']))->assertOk();
        $this->assertSame('payments', $trash->viewData('selected'));
        $this->assertCount(1, $trash->viewData('items'));
        $trash->assertSee('Tahsilatlar')->assertSee('Kalıcı sil');
    }

    #[Test]
    public function tahsilat_komple_silinir_defter_ve_dagilim_dahil_bakiye_degismez(): void
    {
        $this->stay('9000.00');
        $payment = $this->payment('9000.00'); // konaklamaya dağıtılır → payment_allocations satırı
        app(PaymentService::class)->trash($payment, $this->admin);

        $balanceBefore = $this->balance();
        $this->assertDatabaseCount('payment_allocations', 2); // dağılım + ters dağılım
        $ledgerBefore = LedgerEntry::query()->count();

        $this->actingAs($this->admin)->delete(route('trash.destroy', ['payments', $payment->id]))
            ->assertRedirect(route('trash.index', ['tur' => 'payments']))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('payments', ['id' => $payment->id]);
        $this->assertDatabaseCount('payment_allocations', 0);
        $this->assertSame($ledgerBefore - 2, LedgerEntry::query()->count(), 'Tahsilatın orijinal + ters defter satırı silinir');
        $this->assertSame(0, LedgerEntry::query()->where('source_type', 'payment')->count());
        $this->assertSame($balanceBefore, $this->balance(), 'Net sıfır olduğundan bakiye aynı kalır');
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => 'payment', 'auditable_id' => $payment->id, 'event' => 'deleted']);
    }

    #[Test]
    public function kalici_silme_yalnizca_adminde_patron_ve_personel_403(): void
    {
        $payment = $this->payment();
        app(PaymentService::class)->trash($payment, $this->admin);

        foreach ([RoleName::Patron, RoleName::Personel] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->delete(route('trash.destroy', ['payments', $payment->id]))->assertForbidden();
        }
        $this->assertDatabaseHas('payments', ['id' => $payment->id]);
    }

    #[Test]
    public function cope_atilmamis_tahsilat_kalici_silinemez(): void
    {
        $payment = $this->payment();

        $this->actingAs($this->admin)->delete(route('trash.destroy', ['payments', $payment->id]))
            ->assertRedirect()->assertSessionHas('error');
        $this->assertDatabaseHas('payments', ['id' => $payment->id]);
    }

    #[Test]
    public function herkes_geri_alabilir_geri_alinan_iptal_olarak_kalir(): void
    {
        $payment = $this->payment();
        app(PaymentService::class)->trash($payment, $this->admin);

        $this->actingAs($this->userWithRole(RoleName::Personel))
            ->patch(route('trash.restore', ['payments', $payment->id]))->assertRedirect();

        $fresh = $payment->fresh();
        $this->assertFalse($fresh->isTrashed());
        $this->assertTrue($fresh->isCancelled(), 'İptal geri alınmaz');
    }

    // ------------------------------------------------------------- konaklama

    #[Test]
    public function aktif_konaklama_silinince_iptal_edilir_cope_girer_ve_listeden_kalkar(): void
    {
        $stay = $this->stay();

        $this->actingAs($this->admin)->patch(route('stays.trash', $stay))->assertRedirect(route('stays.index'));

        $this->assertSoftDeleted('stays', ['id' => $stay->id]);
        $this->assertSame('0.00', $this->balance(), 'İptal ters kaydı bakiyeyi sıfırlar');

        $list = $this->actingAs($this->admin)->get(route('stays.index'));
        $this->assertCount(0, $list->viewData('stays')->items());

        $trash = $this->actingAs($this->admin)->get(route('trash.index', ['tur' => 'stays']))->assertOk();
        $this->assertCount(1, $trash->viewData('items'));
    }

    #[Test]
    public function konaklama_komple_silinir_defter_ve_dagilim_dahil_odeme_kalir(): void
    {
        $stay = $this->stay('9000.00');
        $payment = $this->payment('9000.00');
        app(StayService::class)->trash($stay, $this->admin);

        $balanceBefore = $this->balance();
        $ledgerBefore = LedgerEntry::query()->count();
        $this->assertGreaterThan(0, DB::table('payment_allocations')->where('stay_id', $stay->id)->count());

        $this->actingAs($this->admin)->delete(route('trash.destroy', ['stays', $stay->id]))
            ->assertRedirect(route('trash.index', ['tur' => 'stays']))->assertSessionHas('success');

        $this->assertDatabaseMissing('stays', ['id' => $stay->id]);
        $this->assertSame(0, DB::table('payment_allocations')->where('stay_id', $stay->id)->count());
        $this->assertSame(0, LedgerEntry::query()->where('source_type', 'stay')->count());
        $this->assertLessThan($ledgerBefore, LedgerEntry::query()->count());
        $this->assertSame($balanceBefore, $this->balance(), 'Net sıfır olduğundan bakiye aynı kalır');
        $this->assertDatabaseHas('payments', ['id' => $payment->id]);
    }

    #[Test]
    public function konaklamayi_yalnizca_stays_delete_yetkisi_cope_atar_personel_atamaz(): void
    {
        $stay = $this->stay();

        $this->actingAs($this->userWithRole(RoleName::Personel))
            ->patch(route('stays.trash', $stay))->assertForbidden();
        $this->assertNotSoftDeleted('stays', ['id' => $stay->id]);
    }

    // ------------------------------------------------- veritabanı kapısı

    #[Test]
    public function kapi_olmadan_defter_satiri_silinemez_ve_kapi_yalnizca_sayilan_satirlari_acar(): void
    {
        $this->stay('1000.00', 'Ayşe A');
        $this->stay('2000.00', 'Burak B');
        $ids = LedgerEntry::query()->orderBy('id')->pluck('id')->all();
        $this->assertCount(2, $ids);

        // Kapı kapalı: reddedilir.
        $this->assertThrows(fn () => DB::table('ledger_entries')->where('id', $ids[0])->delete(), QueryException::class);

        // Kapı yalnızca ids[0] için açık: ids[1] yine reddedilir.
        $this->assertThrows(
            fn () => app(FinancialPurgeGate::class)->run($this->admin, [$ids[0]], [], fn () => DB::table('ledger_entries')->where('id', $ids[1])->delete()),
            QueryException::class,
        );

        // Sayılan satır silinebilir.
        app(FinancialPurgeGate::class)->run($this->admin, [$ids[0]], [], fn () => DB::table('ledger_entries')->where('id', $ids[0])->delete());
        $this->assertDatabaseMissing('ledger_entries', ['id' => $ids[0]]);

        // Kapı işlem bitince kapanır.
        $this->assertThrows(fn () => DB::table('ledger_entries')->where('id', $ids[1])->delete(), QueryException::class);
        $this->assertDatabaseHas('ledger_entries', ['id' => $ids[1]]);
    }

    #[Test]
    public function admin_olmayan_aktor_icin_kapi_acilmaz(): void
    {
        $this->stay('1000.00');
        $id = (int) LedgerEntry::query()->value('id');

        $this->assertThrows(
            fn () => app(FinancialPurgeGate::class)->run($this->userWithRole(RoleName::Patron), [$id], [], fn () => null),
            AuthorizationException::class,
        );
        $this->assertDatabaseHas('ledger_entries', ['id' => $id]);
    }

    #[Test]
    public function net_sifir_olmayan_kaynak_komple_silinmez(): void
    {
        // Çöpte olamaması gereken ama güvenlik için doğrulanan durum: aktif konaklamanın defteri net sıfır değildir.
        $stay = $this->stay('1000.00');
        $this->assertFalse(app(LedgerService::class)->entriesNetToZero(
            app(LedgerService::class)->entryIdsForSource('stay', $stay->id),
        ));
    }

    // ------------------------------------------------------ diğer türler

    #[Test]
    public function makbuz_cope_gider_geri_gelir_ve_yalnizca_admin_kalici_siler(): void
    {
        $entry = ReceiptBookEntry::factory()->create(['created_by' => $this->admin->id, 'updated_by' => $this->admin->id]);

        $this->actingAs($this->admin)->delete(route('receipt-book.destroy', $entry))->assertRedirect();
        $this->assertSoftDeleted('receipt_book_entries', ['id' => $entry->id]);

        $trash = $this->actingAs($this->admin)->get(route('trash.index', ['tur' => 'receipt-book']))->assertOk();
        $this->assertCount(1, $trash->viewData('items'));

        $this->actingAs($this->admin)->patch(route('trash.restore', ['receipt-book', $entry->id]))->assertRedirect();
        $this->assertNotSoftDeleted('receipt_book_entries', ['id' => $entry->id]);

        $this->actingAs($this->admin)->delete(route('receipt-book.destroy', $entry));
        $this->actingAs($this->userWithRole(RoleName::Patron))
            ->delete(route('trash.destroy', ['receipt-book', $entry->id]))->assertForbidden();

        $this->actingAs($this->admin)->delete(route('trash.destroy', ['receipt-book', $entry->id]))->assertSessionHas('success');
        $this->assertDatabaseMissing('receipt_book_entries', ['id' => $entry->id]);
    }

    #[Test]
    public function oda_cope_gider_geri_gelir_ve_kalici_silinir(): void
    {
        $room = Room::factory()->create();

        $this->actingAs($this->admin)->delete(route('rooms.destroy', $room))->assertRedirect();
        $this->assertSoftDeleted('rooms', ['id' => $room->id]);

        $this->actingAs($this->admin)->patch(route('trash.restore', ['rooms', $room->id]))->assertRedirect();
        $this->assertNotSoftDeleted('rooms', ['id' => $room->id]);

        $this->actingAs($this->admin)->delete(route('rooms.destroy', $room));
        $this->actingAs($this->admin)->delete(route('trash.destroy', ['rooms', $room->id]))->assertSessionHas('success');
        $this->assertDatabaseMissing('rooms', ['id' => $room->id]);
    }

    #[Test]
    public function cari_hareketi_olmayan_firma_cope_gider_geri_gelir_ve_kalici_silinir(): void
    {
        $company = Company::factory()->create();

        $this->actingAs($this->admin)->delete(route('companies.destroy', $company))->assertRedirect();
        $this->assertSoftDeleted('companies', ['id' => $company->id]);

        $this->actingAs($this->admin)->patch(route('trash.restore', ['companies', $company->id]))->assertRedirect();
        $this->assertNotSoftDeleted('companies', ['id' => $company->id]);

        $this->actingAs($this->admin)->delete(route('companies.destroy', $company));
        $this->actingAs($this->admin)->delete(route('trash.destroy', ['companies', $company->id]))->assertSessionHas('success');
        $this->assertDatabaseMissing('companies', ['id' => $company->id]);
    }

    #[Test]
    public function kullanici_cope_gider_ve_geri_gelir(): void
    {
        $other = $this->userWithRole(RoleName::Personel);

        $this->actingAs($this->admin)->delete(route('users.destroy', $other))->assertRedirect();
        $this->assertSoftDeleted('users', ['id' => $other->id]);

        $trash = $this->actingAs($this->admin)->get(route('trash.index', ['tur' => 'users']))->assertOk();
        $this->assertCount(1, $trash->viewData('items'));

        $this->actingAs($this->admin)->patch(route('trash.restore', ['users', $other->id]))->assertRedirect();
        $this->assertNotSoftDeleted('users', ['id' => $other->id]);
    }

    // -------------------------------------------------------- görünürlük

    #[Test]
    public function personel_yalnizca_gorebildigi_gruplari_gorur_ve_menude_cop_kutusu_vardir(): void
    {
        $personel = $this->userWithRole(RoleName::Personel);

        $response = $this->actingAs($personel)->get(route('trash.index'))->assertOk()->assertSee('Çöp Kutusu');
        $keys = array_keys($response->viewData('groups'));

        $this->assertContains('payments', $keys);
        $this->assertContains('stays', $keys);
        $this->assertContains('companies', $keys);
        $this->assertNotContains('users', $keys);
        $this->assertNotContains('rooms', $keys);

        $this->actingAs($personel)->patch(route('trash.restore', ['users', 1]))->assertNotFound();
    }

    #[Test]
    public function misafir_cop_kutusuna_giremez(): void
    {
        $this->get(route('trash.index'))->assertRedirect(route('login'));
    }
}
