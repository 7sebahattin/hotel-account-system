<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Models\User;
use App\Modules\Authorization\Enums\RoleName;
use App\Modules\CashBook\Models\CashMovement;
use App\Modules\CashBook\Services\CashBookService;
use App\Modules\Companies\Models\Company;
use App\Modules\Core\Support\Money;
use App\Modules\Payments\Exceptions\PaymentFinancialFieldsAreImmutable;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * LEMON v2.1.1-A.1 — EXACT SHIFT ATTRIBUTION HARDENING.
 *
 * `payments.cash_session_id`, oluşturma anında AÇIKÇA yazılır ve bir daha
 * ASLA değişmez. Zaman penceresi/kullanıcı çıkarımı YOKTUR — bkz. `docs/
 * kararlar.md` K-095.
 */
class PaymentShiftAttributionTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private PaymentService $payments;

    private CashBookService $cashBook;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        $this->company = Company::factory()->create(['name' => 'Monarch']);
        $this->payments = app(PaymentService::class);
        $this->cashBook = app(CashBookService::class);
    }

    /** @param array<string, mixed> $overrides */
    private function makePayment(User $actor, array $overrides = []): Payment
    {
        return $this->payments->create(array_merge([
            'company_id' => $this->company->id,
            'payment_date' => '2026-09-14',
            'amount' => '1000.00',
            'currency' => 'TRY',
        ], $overrides), $actor);
    }

    #[Test]
    public function kart_tahsilati_acikca_kendi_vardiyasina_aittir(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $session = $this->cashBook->openSession($admin);

        $payment = $this->makePayment($admin, ['payment_method' => 'credit_card']);

        $this->assertSame($session->id, $payment->cash_session_id);
        $this->assertSame($session->id, $payment->cashSession->id);
    }

    #[Test]
    public function banka_tahsilati_acikca_kendi_vardiyasina_aittir(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $session = $this->cashBook->openSession($admin);

        $payment = $this->makePayment($admin, ['payment_method' => 'bank_transfer']);

        $this->assertSame($session->id, $payment->cash_session_id);
    }

    #[Test]
    public function nakit_tahsilat_ve_bagli_kasa_hareketi_ayni_vardiyaya_aittir(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $session = $this->cashBook->openSession($admin);

        $payment = $this->makePayment($admin, ['payment_method' => 'cash']);

        $this->assertSame($session->id, $payment->cash_session_id);

        $movement = CashMovement::query()
            ->where('reference_id', $payment->id)->firstOrFail();

        $this->assertSame($session->id, $movement->cash_session_id);
    }

    #[Test]
    public function devir_sonrasi_eski_kart_ve_banka_kayitlari_eski_vardiyada_kalir(): void
    {
        $sercan = $this->userWithRole(RoleName::Personel, ['name' => 'Sercan']);
        $ayse = $this->userWithRole(RoleName::Personel, ['name' => 'Ayşe']);

        $sercanSession = $this->cashBook->openSession($sercan);
        $card = $this->makePayment($sercan, ['payment_method' => 'credit_card', 'amount' => '14000.00']);
        $bank = $this->makePayment($sercan, ['payment_method' => 'bank_transfer', 'amount' => '5000.00']);

        $transfer = $this->cashBook->startHandover(
            $sercanSession, $ayse, ['TRY' => Money::fromDecimal('0.00')], $sercan,
        );
        $this->cashBook->acceptHandover($transfer, $ayse);

        // Devir kart/banka geçmişini DEĞİŞTİRMEZ — hâlâ Sercan'ın vardiyasında.
        $this->assertSame($sercanSession->id, $card->fresh()->cash_session_id);
        $this->assertSame($sercanSession->id, $bank->fresh()->cash_session_id);
    }

    #[Test]
    public function yeni_vardiya_hic_kart_banka_islemi_olmadan_baslar(): void
    {
        $sercan = $this->userWithRole(RoleName::Personel, ['name' => 'Sercan']);
        $ayse = $this->userWithRole(RoleName::Personel, ['name' => 'Ayşe']);

        $sercanSession = $this->cashBook->openSession($sercan);
        $this->makePayment($sercan, ['payment_method' => 'credit_card', 'amount' => '14000.00']);

        $transfer = $this->cashBook->startHandover(
            $sercanSession, $ayse, ['TRY' => Money::fromDecimal('0.00')], $sercan,
        );
        $ayseSession = $this->cashBook->acceptHandover($transfer, $ayse);

        $summary = $this->payments->shiftCollectionSummary($ayseSession);

        $this->assertSame([], $summary->creditCardCollections, 'Yeni vardiyanın hiç kart işlemi yoktur');
        $this->assertSame([], $summary->bankTransferCollections);
    }

    #[Test]
    public function ayni_kullanici_vardiya_sinirina_yakin_islem_yapsa_bile_yanlis_vardiyaya_atfedilmez(): void
    {
        $sercan = $this->userWithRole(RoleName::Personel, ['name' => 'Sercan']);

        $sessionA = $this->cashBook->openSession($sercan);
        $payment = $this->makePayment($sercan, ['payment_method' => 'credit_card', 'amount' => '500.00']);

        // A vardiyası kapatılır, Sercan yeni bir vardiya (B) açar — AYNI kullanıcı.
        $this->cashBook->closeSession($sessionA, [], $sercan);
        $sessionB = $this->cashBook->openSession($sercan);

        // Ödemenin `created_at`'i, B'nin zaman penceresiyle ÇAKIŞACAK şekilde
        // ileri alınsa bile — eski zaman-penceresi çıkarımı bunu B'ye
        // atfederdi — EXPLICIT FK hâlâ A'yı göstermelidir.
        $payment->forceFill(['created_at' => $sessionB->opened_at->copy()->addMinute()])->saveQuietly();

        $this->assertSame($sessionA->id, $payment->fresh()->cash_session_id, 'FK zamanla değişmemeli');

        $summaryA = $this->payments->shiftCollectionSummary($sessionA);
        $summaryB = $this->payments->shiftCollectionSummary($sessionB);

        $this->assertSame('500.00', $summaryA->creditCardCollections['TRY']->toDecimal());
        $this->assertArrayNotHasKey('TRY', $summaryB->creditCardCollections, 'B vardiyasına sızmamalı');
    }

    #[Test]
    public function acik_vardiya_yokken_kart_tahsilati_yetkiliyse_izin_verilir_vardiyasi_null_olur(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        // Kasıtlı: hiçbir vardiya açık değil.

        $payment = $this->makePayment($admin, ['payment_method' => 'credit_card']);

        $this->assertNull($payment->cash_session_id);
    }

    #[Test]
    public function baskasinin_vardiyasi_acikken_kart_tahsilati_o_vardiyaya_atfedilmez(): void
    {
        $sercan = $this->userWithRole(RoleName::Personel, ['name' => 'Sercan']);
        $admin = $this->userWithRole(RoleName::Admin);

        $this->cashBook->openSession($sercan);

        // Admin kendi vardiyasını YÖNETMİYOR — Sercan'ın açık vardiyası ona
        // ait değil, bu yüzden atıf NULL olmalı, Sercan'ın vardiyasına DEĞİL.
        $payment = $this->makePayment($admin, ['payment_method' => 'credit_card']);

        $this->assertNull($payment->cash_session_id);
    }

    #[Test]
    public function eski_bagsiz_kayit_asla_bir_vardiyaya_tahmin_edilmez(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $session = $this->cashBook->openSession($admin);

        // v2.1.1-A.1'den ÖNCEKİ bir kayıt simülasyonu: factory doğrudan
        // yazar, cash_session_id hiç set edilmez (NULL kalır).
        $legacy = Payment::factory()->create([
            'company_id' => $this->company->id,
            'payment_method' => 'credit_card',
            'amount' => '999.00',
            'currency' => 'TRY',
            'created_by' => $admin->id,
        ]);

        $this->assertNull($legacy->cash_session_id);

        // Açık vardiyanın özeti bu eski kaydı İÇERMEMELİDİR — zaman/kullanıcı
        // eşleşse bile tahmin edilerek dahil edilmez.
        $summary = $this->payments->shiftCollectionSummary($session);
        $this->assertArrayNotHasKey('TRY', $summary->creditCardCollections);
    }

    #[Test]
    public function vardiya_kanal_ozeti_farkli_para_birimlerini_ayri_tutar(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $session = $this->cashBook->openSession($admin);

        $this->makePayment($admin, ['payment_method' => 'credit_card', 'currency' => 'TRY', 'amount' => '1000.00']);
        $this->makePayment($admin, ['payment_method' => 'credit_card', 'currency' => 'EUR', 'amount' => '50.00']);
        $this->makePayment($admin, ['payment_method' => 'bank_transfer', 'currency' => 'USD', 'amount' => '75.00']);

        $summary = $this->payments->shiftCollectionSummary($session);

        $this->assertSame('1000.00', $summary->creditCardCollections['TRY']->toDecimal());
        $this->assertSame('50.00', $summary->creditCardCollections['EUR']->toDecimal());
        $this->assertSame('75.00', $summary->bankTransferCollections['USD']->toDecimal());
        $this->assertArrayNotHasKey('EUR', $summary->bankTransferCollections);
    }

    #[Test]
    public function iptal_kartin_orijinal_vardiya_atfini_degistirmez(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $session = $this->cashBook->openSession($admin);

        $payment = $this->makePayment($admin, ['payment_method' => 'credit_card']);
        $this->payments->cancel($payment, 'Mükerrer kayıt', $admin);

        $this->assertSame($session->id, $payment->fresh()->cash_session_id, 'İptal atfı değiştirmemeli');
    }

    #[Test]
    public function iptal_nakit_tahsilatin_orijinal_vardiya_atfini_degistirmez(): void
    {
        $sercan = $this->userWithRole(RoleName::Personel, ['name' => 'Sercan']);
        $ayse = $this->userWithRole(RoleName::Personel, ['name' => 'Ayşe']);

        $sercanSession = $this->cashBook->openSession($sercan);
        $payment = $this->makePayment($sercan, ['payment_method' => 'cash', 'amount' => '7000.00']);

        $transfer = $this->cashBook->startHandover(
            $sercanSession, $ayse, ['TRY' => Money::fromDecimal('7000.00')], $sercan,
        );
        $this->cashBook->acceptHandover($transfer, $ayse);

        // Ayşe (güncel vardiya sahibi) iptal eder — kasa düzeltmesi GÜNCEL
        // vardiyaya yazılır (K-094) ama Payment'ın KENDİ atfı asla değişmez.
        $this->payments->cancel($payment->fresh(), 'Yanlış tutar', $ayse);

        $this->assertSame(
            $sercanSession->id,
            $payment->fresh()->cash_session_id,
            'Kaynak işlem hangi vardiyada olduysa öyle kalmalı',
        );
    }

    #[Test]
    public function cash_session_id_dogrudan_guncellenemez(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $sessionA = $this->cashBook->openSession($admin);
        $payment = $this->makePayment($admin, ['payment_method' => 'credit_card']);

        $this->cashBook->closeSession($sessionA, [], $admin);
        $sessionB = $this->cashBook->openSession($admin);

        // `cash_session_id` $fillable'da DEĞİLDİR (mass-assignment yolu zaten
        // kapalı) — burada doğrudan özellik ataması + save() ile modelin
        // `updating` olay koruması (son savunma) doğrulanır.
        $this->expectException(PaymentFinancialFieldsAreImmutable::class);

        $payment->cash_session_id = $sessionB->id;
        $payment->save();
    }
}
