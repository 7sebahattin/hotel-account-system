<?php

declare(strict_types=1);

namespace Tests\Feature\ReceiptBook;

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
            ->assertSee('TAHSİLAT MAKBUZU')
            ->assertSee($entry->receipt_no);
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
}
