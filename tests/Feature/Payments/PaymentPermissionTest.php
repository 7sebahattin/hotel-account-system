<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Models\User;
use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Companies\Models\Company;
use App\Modules\Ledger\Models\LedgerEntry;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Rol matrisi (CLAUDE.md §7.3).
 *
 * Personel tahsilat görebilir ve girebilir; yalnızca KENDİ kaydının
 * referans/açıklama alanlarını düzeltme penceresi içinde düzeltebilir.
 * Hiçbir tahsilatı iptal edemez, hiçbir finansal alanı değiştiremez.
 */
class PaymentPermissionTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private PaymentService $payments;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        $this->company = Company::factory()->create(['name' => 'Monarch']);
        $this->payments = app(PaymentService::class);
    }

    private function makePayment(User $creator): Payment
    {
        return $this->payments->create([
            'company_id' => $this->company->id,
            'payment_date' => '2026-09-08',
            'amount' => '5000.00',
            'currency' => 'TRY',
            'payment_method' => 'bank_transfer',
            'reference_no' => 'ABC123',
        ], $creator);
    }

    /** @param array<string, mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'company_id' => $this->company->id,
            'payment_date' => '2026-09-08',
            'amount' => '5000,00',
            'currency' => 'TRY',
            'payment_method' => 'cash',
        ], $overrides);
    }

    // -------------------------------------------------------- Admin/Patron

    #[Test]
    #[DataProvider('tamYetkiliRoller')]
    public function tam_yetkili_roller_tum_akislari_kullanabilir(string $role): void
    {
        $user = $this->userWithRole(RoleName::from($role));
        $other = $this->userWithRole(RoleName::Personel);
        $payment = $this->makePayment($other);

        $this->actingAs($user)->get(route('payments.index'))->assertOk();
        $this->actingAs($user)->get(route('payments.create'))->assertOk();
        $this->actingAs($user)->get(route('payments.show', $payment))->assertOk();

        // Başkasının kaydının metadata'sını düzeltebilir.
        $this->actingAs($user)->get(route('payments.edit', $payment))->assertOk();
        $this->actingAs($user)
            ->put(route('payments.update', $payment), ['reference_no' => 'DUZELTILDI'])
            ->assertSessionHasNoErrors();
        $this->assertSame('DUZELTILDI', $payment->fresh()->reference_no);

        // İptal edebilir.
        $this->actingAs($user)
            ->patch(route('payments.cancel', $payment), ['cancellation_reason' => 'Mükerrer'])
            ->assertRedirect();
        $this->assertTrue($payment->fresh()->isCancelled());

        // Yeni tahsilat girebilir.
        $this->actingAs($user)->post(route('payments.store'), $this->payload())->assertSessionHasNoErrors();
    }

    /** @return array<string, array{string}> */
    public static function tamYetkiliRoller(): array
    {
        return ['admin' => ['admin'], 'patron' => ['patron']];
    }

    // ------------------------------------------------------------- Personel

    #[Test]
    public function personel_tahsilat_gorebilir_ve_girebilir(): void
    {
        $personel = $this->userWithRole(RoleName::Personel);

        $this->actingAs($personel)->get(route('payments.index'))->assertOk();
        $this->actingAs($personel)->get(route('payments.create'))->assertOk();

        $this->actingAs($personel)
            ->post(route('payments.store'), $this->payload())
            ->assertSessionHasNoErrors();

        $payment = Payment::query()->firstOrFail();

        $this->assertSame($personel->id, $payment->created_by);
        $this->assertSame('5000.00', LedgerEntry::query()->firstOrFail()->credit);
    }

    #[Test]
    public function personel_kendi_kaydinin_metadatasini_duzeltebilir(): void
    {
        $personel = $this->userWithRole(RoleName::Personel);
        $payment = $this->makePayment($personel);

        $this->actingAs($personel)->get(route('payments.edit', $payment))->assertOk();

        $this->actingAs($personel)
            ->put(route('payments.update', $payment), [
                'reference_no' => 'DUZELTILDI',
                'description' => 'Dekont eklendi',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('DUZELTILDI', $payment->fresh()->reference_no);
    }

    #[Test]
    public function personel_baskasinin_kaydini_duzeltemez(): void
    {
        $personel = $this->userWithRole(RoleName::Personel);
        $baskasi = $this->userWithRole(RoleName::Personel);
        $payment = $this->makePayment($baskasi);

        $this->actingAs($personel)->get(route('payments.edit', $payment))->assertForbidden();
        $this->actingAs($personel)
            ->put(route('payments.update', $payment), ['reference_no' => 'ELE-GECIRILDI'])
            ->assertForbidden();

        $this->assertSame('ABC123', $payment->fresh()->reference_no);
    }

    #[Test]
    public function personel_suresi_dolmus_kendi_kaydini_duzeltemez(): void
    {
        $personel = $this->userWithRole(RoleName::Personel);
        $payment = $this->makePayment($personel);

        $payment->forceFill(['created_at' => now()->subDays(3)])->saveQuietly();

        $this->actingAs($personel)->get(route('payments.edit', $payment))->assertForbidden();
        $this->actingAs($personel)
            ->put(route('payments.update', $payment), ['reference_no' => 'GEC-KALDI'])
            ->assertForbidden();

        $this->assertSame('ABC123', $payment->fresh()->reference_no);
    }

    #[Test]
    public function personel_tahsilat_iptal_edemez(): void
    {
        $personel = $this->userWithRole(RoleName::Personel);
        $payment = $this->makePayment($personel);

        $this->actingAs($personel)
            ->patch(route('payments.cancel', $payment), ['cancellation_reason' => 'Yanlış'])
            ->assertForbidden();

        $this->assertTrue($payment->fresh()->isActive());
        $this->assertSame(1, LedgerEntry::query()->count());
    }

    /**
     * Personel kendi kaydını düzeltirken finansal alan gönderse bile
     * hiçbiri değişmez — UpdatePaymentRequest bu alanları tanımıyor.
     */
    #[Test]
    public function personel_kendi_kaydinin_tutarini_degistiremez(): void
    {
        $personel = $this->userWithRole(RoleName::Personel);
        $payment = $this->makePayment($personel);

        $this->actingAs($personel)
            ->put(route('payments.update', $payment), [
                'reference_no' => 'ABC123',
                'amount' => '1,00',
                'company_id' => 99999,
                'currency' => 'EUR',
            ])
            ->assertSessionHasNoErrors();

        $fresh = $payment->fresh();

        $this->assertSame('5000.00', $fresh->amount);
        $this->assertSame($this->company->id, $fresh->company_id);
        $this->assertSame('5000.00', LedgerEntry::query()->firstOrFail()->credit);
    }

    #[Test]
    public function iptal_butonu_yalnizca_yetkiliye_gosterilir(): void
    {
        $personel = $this->userWithRole(RoleName::Personel);
        $payment = $this->makePayment($personel);

        $this->actingAs($personel)
            ->get(route('payments.show', $payment))
            ->assertOk()
            ->assertDontSee('Tahsilatı iptal et');

        $this->actingAs($this->userWithRole(RoleName::Admin))
            ->get(route('payments.show', $payment))
            ->assertOk()
            ->assertSee('Tahsilatı iptal et');
    }

    #[Test]
    public function menude_tahsilatlar_yetkiye_gore_gorunur(): void
    {
        foreach ([RoleName::Admin, RoleName::Patron, RoleName::Personel] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->get(route('home'))
                ->assertOk()
                ->assertSee(route('payments.index'));
        }
    }

    #[Test]
    public function giris_yapmamis_kullanici_erisemez(): void
    {
        $this->get(route('payments.index'))->assertRedirect(route('login'));
        $this->get(route('payments.create'))->assertRedirect(route('login'));
    }
}
