<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Companies\Models\Company;
use App\Modules\Ledger\Models\LedgerEntry;
use App\Modules\Payments\Enums\PaymentMethod;
use App\Modules\Payments\Enums\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Services\PaymentService;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentCrudTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        $this->company = Company::factory()->create(['name' => 'Monarch']);
    }

    /** @param array<string, mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'company_id' => $this->company->id,
            'payment_date' => '2026-09-08',
            'amount' => '5000,00',
            'currency' => 'TRY',
            'payment_method' => 'bank_transfer',
            'reference_no' => 'ABC123',
            'description' => 'Eylül ayı ödemesi',
        ], $overrides);
    }

    #[Test]
    public function tahsilat_olusturulur(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $this->actingAs($admin)
            ->post(route('payments.store'), $this->payload())
            ->assertSessionHasNoErrors();

        $payment = Payment::query()->firstOrFail();

        $this->assertSame($this->company->id, $payment->company_id);
        $this->assertSame('5000.00', $payment->amount);
        $this->assertSame(PaymentMethod::BankTransfer, $payment->payment_method);
        $this->assertSame(PaymentStatus::Active, $payment->status);
        $this->assertSame('ABC123', $payment->reference_no);
        $this->assertSame($admin->id, $payment->created_by);
        $this->assertNull($payment->cancelled_at);
    }

    #[Test]
    public function odeme_yontemi_bos_birakilabilir(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Admin))
            ->post(route('payments.store'), $this->payload(['payment_method' => null]))
            ->assertSessionHasNoErrors();

        $this->assertNull(Payment::query()->firstOrFail()->payment_method);
    }

    #[Test]
    #[DataProvider('gecersizTutarlar')]
    public function gecersiz_tutar_reddedilir(?string $amount): void
    {
        $this->actingAs($this->userWithRole(RoleName::Admin))
            ->post(route('payments.store'), $this->payload(['amount' => $amount]))
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, Payment::query()->count());
        $this->assertSame(0, LedgerEntry::query()->count());
    }

    /** @return array<string, array{string|null}> */
    public static function gecersizTutarlar(): array
    {
        return [
            'sıfır' => ['0'],
            'sıfır kuruşlu' => ['0,00'],
            'negatif' => ['-100,00'],
            'metin' => ['bin lira'],
            'boş' => [null],
            'üç ondalık' => ['100,123'],
        ];
    }

    #[Test]
    public function desteklenmeyen_para_birimi_reddedilir(): void
    {
        // 'USD' Gün 9.6'dan itibaren desteklenir (Currency enum); reddedilmesi
        // gereken kod, enum kümesinin dışında kalan gerçek bir örnek olmalı.
        $this->actingAs($this->userWithRole(RoleName::Admin))
            ->post(route('payments.store'), $this->payload(['currency' => 'CHF']))
            ->assertSessionHasErrors('currency');

        $this->assertSame(0, Payment::query()->count());
    }

    #[Test]
    public function desteklenmeyen_odeme_yontemi_reddedilir(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Admin))
            ->post(route('payments.store'), $this->payload(['payment_method' => 'bitcoin']))
            ->assertSessionHasErrors('payment_method');

        $this->assertSame(0, Payment::query()->count());
    }

    #[Test]
    public function var_olmayan_firma_reddedilir(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Admin))
            ->post(route('payments.store'), $this->payload(['company_id' => 99999]))
            ->assertSessionHasErrors('company_id');
    }

    #[Test]
    public function silinmis_firmaya_tahsilat_girilemez(): void
    {
        $this->company->delete();

        $this->actingAs($this->userWithRole(RoleName::Admin))
            ->post(route('payments.store'), $this->payload())
            ->assertSessionHasErrors('company_id');
    }

    #[Test]
    public function tarih_zorunludur(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Admin))
            ->post(route('payments.store'), $this->payload(['payment_date' => null]))
            ->assertSessionHasErrors('payment_date');
    }

    /**
     * REGRESYON (Gün 5'te yakalandı): gerçek bir HTML formu HER alanı METİN
     * gönderir; Laravel'in test istemcisi ise diziye konan PHP tiplerini korur.
     * Bu fark yüzünden company_id'nin string gelmesi testlerden kaçmıştı ve
     * tarayıcıdan gelen gerçek istekte TypeError üretiyordu.
     */
    #[Test]
    public function form_alanlari_metin_olarak_gelse_de_cari_hareketi_dogar(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $this->actingAs($admin)->post(route('payments.store'), [
            'company_id' => (string) $this->company->id,
            'payment_date' => '2026-09-08',
            'amount' => '5000.00',
            'currency' => 'TRY',
            'payment_method' => 'cash',
            'reference_no' => 'STR-1',
            'description' => 'Metin gövdeli istek',
        ])->assertSessionHasNoErrors();

        $payment = Payment::query()->firstOrFail();
        $entry = LedgerEntry::query()->firstOrFail();

        // assertSame KATI karşılaştırır: cast olmasaydı company_id "7" (string)
        // kalır ve bu satır da, LedgerService::credit(int $companyId) çağrısı da patlardı.
        $this->assertSame($this->company->id, $payment->company_id);
        $this->assertSame($this->company->id, $entry->company_id);
        $this->assertSame($payment->id, $entry->source_id);
        $this->assertSame('5000.00', $entry->credit);
        $this->assertSame(PaymentMethod::Cash, $payment->payment_method);
    }

    /** Türkçe klavye virgülü: '5.000,50' → 5000.50 */
    #[Test]
    public function virgullu_tutar_dogru_ayristirilir(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Admin))
            ->post(route('payments.store'), $this->payload(['amount' => '5.000,50']))
            ->assertSessionHasNoErrors();

        $this->assertSame('5000.50', Payment::query()->firstOrFail()->amount);
        $this->assertSame('5000.50', LedgerEntry::query()->firstOrFail()->credit);
    }

    #[Test]
    public function negatif_tutar_veritabani_duzeyinde_de_reddedilir(): void
    {
        $this->expectException(QueryException::class);

        DB::table('payments')->insert([
            'company_id' => $this->company->id,
            'payment_date' => '2026-09-08',
            'amount' => '-100.00',
            'currency' => 'TRY',
            'status' => 'active',
            'created_by' => $this->userWithRole(RoleName::Admin)->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    #[Test]
    public function iptal_alanlari_tutarsizsa_veritabani_reddeder(): void
    {
        $this->expectException(QueryException::class);

        // status = cancelled ama cancelled_at/cancelled_by boş.
        DB::table('payments')->insert([
            'company_id' => $this->company->id,
            'payment_date' => '2026-09-08',
            'amount' => '100.00',
            'currency' => 'TRY',
            'status' => 'cancelled',
            'created_by' => $this->userWithRole(RoleName::Admin)->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Olağan silme/geri alma akışı yoktur: iptal geri alınamaz, soft delete kolonu yoktur.
     * Kalıcı silme YALNIZCA çöp kutusu + Admin istisnasıdır (PaymentTrashTest, 4 Kasım 2026).
     */
    #[Test]
    public function silme_ve_geri_alma_akisi_yoktur(): void
    {
        $this->assertFalse(Route::has('payments.destroy'));
        $this->assertFalse(Route::has('payments.restore'));
        $this->assertFalse(method_exists(PaymentService::class, 'delete'));
        $this->assertFalse(method_exists(PaymentService::class, 'restore'));
        $this->assertFalse(
            Schema::hasColumn('payments', 'deleted_at'),
            'payments tablosunda soft delete kolonu OLMAMALI',
        );
        $this->assertNotContains(
            SoftDeletes::class,
            class_uses_recursive(Payment::class),
        );
    }
}
