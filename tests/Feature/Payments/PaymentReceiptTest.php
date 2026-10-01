<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Models\User;
use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Companies\Models\Company;
use App\Modules\Payments\Exceptions\PaymentFinancialFieldsAreImmutable;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * "Tahsilat Makbuzu" — v2.1.1-B §12/§13.
 *
 * Makbuz numarası `Payment::id` üzerinden TÜRETİLİR (ayrı sıra tablosu yok),
 * bir kez üretilir ve İPTALDE BİLE değişmez. Bu belge fatura/e-Arşiv
 * DEĞİLDİR — operasyonel bir tahsilat kaydıdır.
 */
class PaymentReceiptTest extends TestCase
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

    /** @param array<string, mixed> $overrides */
    private function makePayment(User $actor, array $overrides = []): Payment
    {
        return $this->payments->create(array_merge([
            'company_id' => $this->company->id,
            'payment_date' => '2026-09-09',
            'amount' => '1234.56',
            'currency' => 'TRY',
            'payment_method' => 'bank_transfer',
        ], $overrides), $actor);
    }

    #[Test]
    public function makbuz_numarasi_olusturmada_uretilir_ve_bicimi_dogrudur(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $payment = $this->makePayment($admin);

        $this->assertNotNull($payment->receipt_no);
        $this->assertSame(
            sprintf('TM-%s-%06d', $payment->payment_date->format('Y'), $payment->id),
            $payment->receipt_no,
        );
    }

    #[Test]
    public function ard_arda_olusturulan_tahsilatlarin_makbuz_numaralari_benzersizdir(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $first = $this->makePayment($admin);
        $second = $this->makePayment($admin);
        $third = $this->makePayment($admin);

        $numbers = [$first->receipt_no, $second->receipt_no, $third->receipt_no];

        $this->assertSame($numbers, array_unique($numbers), 'Makbuz numaraları benzersiz olmalı');
    }

    #[Test]
    public function makbuz_numarasi_iptalde_degismez(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $payment = $this->makePayment($admin);
        $original = $payment->receipt_no;

        $this->payments->cancel($payment, 'Yanlış firma', $admin);

        $this->assertSame($original, $payment->fresh()->receipt_no);
    }

    #[Test]
    public function makbuz_numarasi_dogrudan_guncellenemez(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $payment = $this->makePayment($admin);

        $this->expectException(PaymentFinancialFieldsAreImmutable::class);

        $payment->receipt_no = 'TM-2026-999999';
        $payment->save();
    }

    #[Test]
    public function makbuz_ekrani_tahsilat_verisiyle_eslesir(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $payment = $this->makePayment($admin, [
            'amount' => '5000.00',
            'payment_method' => 'bank_transfer',
            'reference_no' => 'DEKONT-42',
        ]);

        $this->actingAs($admin)->get(route('payments.receipt', $payment))
            ->assertOk()
            ->assertSee($payment->receipt_no)
            ->assertSee('Monarch')
            ->assertSee('5.000,00 ₺')
            ->assertSee('Havale/EFT')
            ->assertSee('DEKONT-42')
            ->assertSee('TAHSİLAT')
            ->assertSee('MAKBUZU')
            ->assertSee('LEMON HOTEL')
            ->assertSee('Teslim Eden (Ödeyen)')
            ->assertSee('Teslim Alan (Firma Yetkilisi)')
            ->assertSee($admin->name)
            ->assertSee('Diğer')
            ->assertSee('data-receipt-template="v4"', false)
            ->assertSee('data-brand-variant="on-light"', false)
            ->assertSee('fatura/e-Arşiv fatura değildir');
    }

    #[Test]
    public function makbuz_referans_kur_odeyen_ve_diger_odeme_secenegini_tutarlı_gosterir(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $payment = $this->makePayment($admin, [
            'currency' => 'EUR',
            'amount' => '100.00',
            'exchange_rate_to_try' => '52.50',
            'payment_method' => 'other',
            'reference_no' => 'REF-V4-01',
            'payer_type' => 'individual',
            'payer_name' => 'Ödeyen Yetkili',
            'description' => 'Makbuz açıklaması',
        ]);

        $response = $this->actingAs($admin)->get(route('payments.receipt', $payment));

        $response->assertOk()
            ->assertSee('Monarch')
            ->assertSee('Ödeyen: Ödeyen Yetkili')
            ->assertSee('REF-V4-01')
            ->assertSee('Kur:')
            ->assertSee('TL karşılığı:')
            ->assertSee('Makbuz açıklaması')
            ->assertSee('Diğer')
            ->assertSee('data-receipt-method="other"', false)
            ->assertSee('data-checked="true"', false);
    }

    #[Test]
    public function try_disinda_para_biriminde_yaziyla_tutar_uretilmez(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $payment = $this->makePayment($admin, ['currency' => 'EUR', 'amount' => '100.00', 'payment_method' => 'credit_card']);

        $this->assertNull($payment->amountInWords());

        $this->actingAs($admin)->get(route('payments.receipt', $payment))
            ->assertOk()
            ->assertSee('100,00 €')
            ->assertSee('EUR')
            // Uydurma/yanlış çeviri üretilmez — yalnızca sayısal tutar + ISO kodu.
            ->assertDontSee('avro');
    }

    #[Test]
    public function try_tutarinda_yaziyla_alan_dogru_uretilir(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $payment = $this->makePayment($admin, ['amount' => '1234.56']);

        $this->assertSame('Bin İki Yüz Otuz Dört Türk Lirası, Elli Altı Kuruş', $payment->amountInWords());
    }

    #[Test]
    public function iptal_edilmis_tahsilatin_makbuzu_erisilebilir_ve_iptal_durumu_gorunur(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $payment = $this->makePayment($admin);
        $this->payments->cancel($payment, 'Yanlış firmaya işlendi', $admin);

        $this->actingAs($admin)->get(route('payments.receipt', $payment))
            ->assertOk()
            ->assertSee('İPTAL EDİLDİ')
            ->assertSee('Yanlış firmaya işlendi')
            ->assertSee($payment->receipt_no);
    }

    #[Test]
    public function makbuz_route_goruntuleme_yetkisine_tabidir(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $payment = $this->makePayment($admin);

        // Giriş yapmamış kullanıcı login'e yönlendirilir (actingAs'ten ÖNCE
        // çağrılmalı — aksi hâlde bir önceki oturum kalıntısı yanıltır).
        $this->get(route('payments.receipt', $payment))->assertRedirect(route('login'));

        // Rolü olmayan (payments.view'i olmayan) kullanıcı erişemez.
        $noRole = User::factory()->create();
        $this->actingAs($noRole)->get(route('payments.receipt', $payment))->assertForbidden();

        // payments.view tüm rollerde vardır (CLAUDE.md §7.3) — personel de görebilir.
        $personel = $this->userWithRole(RoleName::Personel);
        $this->actingAs($personel)->get(route('payments.receipt', $payment))->assertOk();
    }

    #[Test]
    public function bu_ozellikten_once_olusturulmus_kayitta_makbuz_numarasi_yoktur_ve_ekran_404_doner(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        // v2.1.1-B'den ÖNCEKİ kayıt simülasyonu: factory doğrudan yazar,
        // receipt_no hiç set edilmez (NULL kalır) — geriye dönük veri kırılmaz.
        $legacy = Payment::factory()->create([
            'company_id' => $this->company->id,
            'created_by' => $admin->id,
        ]);

        $this->assertNull($legacy->receipt_no);

        $this->actingAs($admin)->get(route('payments.receipt', $legacy))->assertNotFound();

        // Ama tahsilatın kendi detay ekranı normal şekilde açılmaya devam eder.
        $this->actingAs($admin)->get(route('payments.show', $legacy))
            ->assertOk()
            ->assertDontSee(route('payments.receipt', $legacy), false);
    }
}
