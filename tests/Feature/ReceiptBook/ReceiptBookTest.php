<?php

declare(strict_types=1);

namespace Tests\Feature\ReceiptBook;

use App\Modules\AuditLog\Enums\AuditEvent;
use App\Modules\AuditLog\Models\AuditLog;
use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Ledger\Models\LedgerEntry;
use App\Modules\Payments\Models\Payment;
use App\Modules\ReceiptBook\Models\ReceiptBookEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tahsilat Makbuzu Defteri (v2.1.1-C §4) — mevcut muhasebe Tahsilatları'ndan
 * TAMAMEN AYRI, yazdırma amaçlı bir modül.
 *
 * Pazarlıksız kural: bir makbuzun kaydedilmesi HİÇBİR muhasebe/kasa yan
 * etkisi üretmez (Payment/LedgerEntry/CashMovement oluşmaz).
 */
class ReceiptBookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    /** @param array<string, mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'receipt_date' => '2026-09-08',
            'customer_name' => 'Örnek Müşteri',
            'amount' => '1.500,00',
            'currency' => 'TRY',
            'payment_method' => 'cash',
            'description' => 'Test makbuzu',
        ], $overrides);
    }

    #[Test]
    public function makbuz_kaydedilir(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $this->actingAs($admin)
            ->post(route('receipt-book.store'), $this->payload())
            ->assertSessionDoesntHaveErrors();

        $entry = ReceiptBookEntry::query()->firstOrFail();
        $this->assertSame('Örnek Müşteri', $entry->customer_name);
        $this->assertSame('1500.00', $entry->amount);
        $this->assertNotNull($entry->receipt_no);
    }

    #[Test]
    public function makbuz_no_payment_ledger_ya_da_kasa_hareketi_uretmez(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $this->actingAs($admin)->post(route('receipt-book.store'), $this->payload());

        $this->assertSame(0, Payment::query()->count());
        $this->assertSame(0, LedgerEntry::query()->count());
        $this->assertSame(0, DB::table('cash_movements')->count());
    }

    #[Test]
    public function makbuz_numarasi_kendi_onekini_tasir_ve_benzersizdir(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $this->actingAs($admin)->post(route('receipt-book.store'), $this->payload());
        $this->actingAs($admin)->post(route('receipt-book.store'), $this->payload());

        $entries = ReceiptBookEntry::query()->orderBy('id')->get();

        $this->assertCount(2, $entries);
        $this->assertNotSame($entries[0]->receipt_no, $entries[1]->receipt_no);

        foreach ($entries as $entry) {
            $this->assertMatchesRegularExpression('/^MKB-\d{4}-\d{6}$/', $entry->receipt_no);
            // Gerçek muhasebe makbuzuyla (TM-…) KARIŞTIRILMAZ.
            $this->assertStringStartsNotWith('TM-', $entry->receipt_no);
        }
    }

    #[Test]
    public function yabanci_para_birimli_makbuzda_kur_zorunludur(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $this->actingAs($admin)
            ->post(route('receipt-book.store'), $this->payload(['currency' => 'EUR']))
            ->assertSessionHasErrors('exchange_rate_to_try');

        $this->assertSame(0, ReceiptBookEntry::query()->count());

        $this->actingAs($admin)
            ->post(route('receipt-book.store'), $this->payload(['currency' => 'EUR', 'exchange_rate_to_try' => '51,20']))
            ->assertSessionDoesntHaveErrors();

        $entry = ReceiptBookEntry::query()->firstOrFail();
        $this->assertSame('51.2000', $entry->exchange_rate_to_try);
    }

    #[Test]
    public function yazdirma_rotasi_calisir(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $this->actingAs($admin)->post(route('receipt-book.store'), $this->payload());
        $entry = ReceiptBookEntry::query()->firstOrFail();

        $this->actingAs($admin)
            ->get(route('receipt-book.print', $entry))
            ->assertOk()
            ->assertSee('TAHSİLAT')
            ->assertSee('MAKBUZU')
            ->assertSee('LEMON HOTEL')
            ->assertSee('KURTOĞLU MÜHENDİSLİK')
            ->assertSee('Örnek Müşteri')
            ->assertSee('1.500,00 ₺')
            ->assertSee('Bin Beş Yüz Türk Lirası')
            ->assertSee('Nakit')
            ->assertSee('Diğer')
            ->assertSee('Teslim Alan (Firma Yetkilisi)')
            ->assertSee($admin->name)
            ->assertSee('data-receipt-template="v4"', false)
            ->assertSee($entry->receipt_no);
    }

    #[Test]
    public function yazdirma_kur_bilgisini_ve_odeme_seklini_referans_duzeninde_gosterir(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $this->actingAs($admin)
            ->post(route('receipt-book.store'), $this->payload([
                'currency' => 'EUR',
                'amount' => '100,00',
                'exchange_rate_to_try' => '52,50',
                'payment_method' => 'credit_card',
                'description' => 'Kartlı makbuz',
            ]))
            ->assertSessionDoesntHaveErrors();

        $entry = ReceiptBookEntry::query()->firstOrFail();

        $this->actingAs($admin)
            ->get(route('receipt-book.print', $entry))
            ->assertOk()
            ->assertSee('100,00 €')
            ->assertSee('K. Kartı')
            ->assertSee('Kur:')
            ->assertSee('TL karşılığı:')
            ->assertSee('Kartlı makbuz')
            ->assertSee('data-receipt-method="credit_card"', false)
            ->assertSee('data-checked="true"', false);
    }

    #[Test]
    public function yetkisi_olmayan_kullanici_erisemez(): void
    {
        $userWithoutRole = $this->userWithRole(RoleName::Admin);
        $userWithoutRole->syncRoles([]);

        $this->actingAs($userWithoutRole->fresh())
            ->get(route('receipt-book.index'))
            ->assertForbidden();
    }

    #[Test]
    public function personel_makbuz_defterini_gorebilir_ve_olusturabilir(): void
    {
        $personel = $this->userWithRole(RoleName::Personel);

        $this->actingAs($personel)->get(route('receipt-book.index'))->assertOk();

        $this->actingAs($personel)
            ->post(route('receipt-book.store'), $this->payload())
            ->assertSessionDoesntHaveErrors();
    }

    private function createEntry(): ReceiptBookEntry
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $this->actingAs($admin)->post(route('receipt-book.store'), $this->payload());

        return ReceiptBookEntry::query()->firstOrFail();
    }

    #[Test]
    public function admin_makbuzu_duzenleyebilir_ve_numara_degismez(): void
    {
        $entry = $this->createEntry();
        $receiptNo = $entry->receipt_no;
        $admin = $this->userWithRole(RoleName::Admin);

        $this->actingAs($admin)->get(route('receipt-book.edit', $entry))
            ->assertOk()
            ->assertSee('value="Örnek Müşteri"', false)
            ->assertSee('value="1500,00"', false);

        $this->actingAs($admin)
            ->put(route('receipt-book.update', $entry), $this->payload([
                'customer_name' => 'Düzeltilmiş Müşteri',
                'amount' => '2.750,50',
                'currency' => 'EUR',
                'exchange_rate_to_try' => '50,00',
            ]))
            ->assertRedirect(route('receipt-book.show', $entry))
            ->assertSessionDoesntHaveErrors();

        $entry->refresh();
        $this->assertSame('Düzeltilmiş Müşteri', $entry->customer_name);
        $this->assertSame('2750.50', $entry->amount);
        $this->assertSame('137525.00', $entry->try_equivalent);
        $this->assertSame($receiptNo, $entry->receipt_no);
        $this->assertSame($admin->id, $entry->updated_by);

        // TRY'ye dönünce kur alanları temizlenir (DB CHECK ile uyumlu).
        $this->actingAs($admin)
            ->put(route('receipt-book.update', $entry), $this->payload())
            ->assertSessionDoesntHaveErrors();
        $this->assertNull($entry->fresh()->exchange_rate_to_try);

        $this->assertTrue(AuditLog::query()
            ->where('event', AuditEvent::Updated->value)
            ->where('auditable_type', 'receipt_book_entry')
            ->where('auditable_id', $entry->id)
            ->exists());
    }

    #[Test]
    public function duzenleme_dogrulama_hatasi_duzenleme_sayfasina_doner(): void
    {
        $entry = $this->createEntry();
        $admin = $this->userWithRole(RoleName::Admin);

        $this->actingAs($admin)
            ->put(route('receipt-book.update', $entry), $this->payload(['amount' => '0']))
            ->assertRedirect(route('receipt-book.edit', $entry))
            ->assertSessionHasErrors('amount');

        $this->assertSame('1500.00', $entry->fresh()->amount);
    }

    #[Test]
    public function admin_makbuzu_silebilir_ve_denetim_kaydi_eski_degerleri_tasir(): void
    {
        $entry = $this->createEntry();
        $admin = $this->userWithRole(RoleName::Admin);

        $this->actingAs($admin)
            ->delete(route('receipt-book.destroy', $entry))
            ->assertRedirect(route('receipt-book.index'));

        $this->assertNull(ReceiptBookEntry::query()->find($entry->id));

        $logs = AuditLog::query()
            ->where('event', AuditEvent::Deleted->value)
            ->where('auditable_type', 'receipt_book_entry')
            ->where('auditable_id', $entry->id)
            ->get();

        $this->assertCount(1, $logs);
        $this->assertSame($entry->receipt_no, $logs[0]->old_values['receipt_no']);
        $this->assertSame('Örnek Müşteri', $logs[0]->old_values['customer_name']);
    }

    #[Test]
    public function patron_ve_personel_makbuzu_duzenleyemez_ve_silemez(): void
    {
        $entry = $this->createEntry();

        foreach ([RoleName::Patron, RoleName::Personel] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)->get(route('receipt-book.edit', $entry))->assertForbidden();
            $this->actingAs($user)
                ->put(route('receipt-book.update', $entry), $this->payload(['customer_name' => 'X']))
                ->assertForbidden();
            $this->actingAs($user)->delete(route('receipt-book.destroy', $entry))->assertForbidden();

            $this->actingAs($user)->get(route('receipt-book.show', $entry))
                ->assertOk()
                ->assertDontSee(route('receipt-book.edit', $entry))
                ->assertDontSee('kalıcı olarak silinsin');
        }

        $this->assertSame('Örnek Müşteri', $entry->fresh()->customer_name);
    }

    #[Test]
    public function liste_ve_detay_kayitli_makbuzla_tum_rollerde_acilir(): void
    {
        $entry = $this->createEntry();
        $admin = $this->userWithRole(RoleName::Admin);
        $this->actingAs($admin)->post(route('receipt-book.store'), $this->payload([
            'currency' => 'EUR', 'exchange_rate_to_try' => '50,00', 'payment_method' => null,
        ]));

        $this->actingAs($admin)->get(route('receipt-book.index'))
            ->assertOk()
            ->assertSee($entry->receipt_no)
            ->assertSee(route('receipt-book.edit', $entry), false);

        $this->actingAs($admin)->get(route('receipt-book.show', $entry))
            ->assertOk()
            ->assertSee('kalıcı olarak silinsin');

        foreach ([RoleName::Patron, RoleName::Personel] as $role) {
            $this->actingAs($this->userWithRole($role))->get(route('receipt-book.index'))
                ->assertOk()
                ->assertSee($entry->receipt_no)
                ->assertDontSee(route('receipt-book.edit', $entry), false);
        }
    }
}
