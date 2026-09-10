<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Models\User;
use App\Modules\Authorization\Enums\RoleName;
use App\Modules\CashBook\Enums\CashMovementType;
use App\Modules\CashBook\Exceptions\CannotReverseMovement;
use App\Modules\CashBook\Exceptions\NoOpenCashSession;
use App\Modules\CashBook\Models\CashMovement;
use App\Modules\CashBook\Models\CashSession;
use App\Modules\CashBook\Services\CashBookService;
use App\Modules\Companies\Models\Company;
use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
use App\Modules\Ledger\Models\LedgerEntry;
use App\Modules\Ledger\Services\LedgerService;
use App\Modules\Payments\Exceptions\CashSessionNotOwnedByActor;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * LEMON v2.1.1-A — kanal mimarisi: nakit tahsilat fiziksel kasaya bağlanır,
 * kart/banka bağlanmaz. Bkz. `docs/kararlar.md` K-094.
 */
class PaymentChannelArchitectureTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private PaymentService $payments;

    private LedgerService $ledger;

    private CashBookService $cashBook;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        $this->company = Company::factory()->create(['name' => 'Monarch']);
        $this->payments = app(PaymentService::class);
        $this->ledger = app(LedgerService::class);
        $this->cashBook = app(CashBookService::class);
    }

    private function balance(Currency $currency = Currency::TRY): string
    {
        return $this->ledger->balanceForCompany($this->company->id, $currency)->toDecimal();
    }

    private function expectedCash(CashSession $session, Currency $currency = Currency::TRY): string
    {
        $expected = $this->cashBook->expectedFor($session->refresh());

        return ($expected[$currency->value] ?? Money::zero())->toDecimal();
    }

    /** @param array<string, mixed> $overrides */
    private function makePayment(User $actor, array $overrides = []): Payment
    {
        return $this->payments->create(array_merge([
            'company_id' => $this->company->id,
            'payment_date' => '2026-09-09',
            'amount' => '5000.00',
            'currency' => 'TRY',
        ], $overrides), $actor);
    }

    // ------------------------------------------------------- 1) nakit kanal

    #[Test]
    public function nakit_tahsilat_hem_cariyi_hem_fiziksel_kasayi_tam_bir_kez_degistirir(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $session = $this->cashBook->openSession($admin);

        $payment = $this->makePayment($admin, ['payment_method' => 'cash']);

        $this->assertSame('-5000.00', $this->balance());
        $this->assertSame('5000.00', $this->expectedCash($session));

        $movement = CashMovement::query()->where('reference_type', Payment::LEDGER_SOURCE_TYPE)
            ->where('reference_id', $payment->id)->firstOrFail();

        $this->assertSame(CashMovementType::CompanyCollection, $movement->type);
        $this->assertSame('5000.00', $movement->amount);
        $this->assertSame($session->id, $movement->cash_session_id);
        $this->assertSame(1, CashMovement::query()->count(), 'Tam olarak bir kasa hareketi — iki kez üretilmez');
    }

    // -------------------------------------------------- 2-3) kart / banka

    #[Test]
    public function kredi_karti_tahsilati_cariyi_degistirir_fiziksel_kasaya_dokunmaz(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $session = $this->cashBook->openSession($admin, ['TRY' => Money::fromDecimal('1000.00')]);

        $this->makePayment($admin, ['payment_method' => 'credit_card']);

        $this->assertSame('-5000.00', $this->balance());
        $this->assertSame('1000.00', $this->expectedCash($session), 'Kart tahsilatı kasayı değiştirmemeli');
        $this->assertSame(0, CashMovement::query()->where('type', CashMovementType::CompanyCollection)->count());
    }

    #[Test]
    public function havale_tahsilati_cariyi_degistirir_fiziksel_kasaya_dokunmaz(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $session = $this->cashBook->openSession($admin, ['TRY' => Money::fromDecimal('1000.00')]);

        $this->makePayment($admin, ['payment_method' => 'bank_transfer']);

        $this->assertSame('-5000.00', $this->balance());
        $this->assertSame('1000.00', $this->expectedCash($session));
        $this->assertSame(0, CashMovement::query()->where('type', CashMovementType::CompanyCollection)->count());
    }

    #[Test]
    public function kart_veya_banka_tahsilati_acik_vardiya_gerektirmez(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        // Kasıtlı: hiçbir vardiya açılmadı.

        $this->makePayment($admin, ['payment_method' => 'credit_card']);

        $this->assertSame('-5000.00', $this->balance());
        $this->assertSame(1, Payment::query()->count());
    }

    // ---------------------------------------------------- 4) çoklu para birimi

    #[Test]
    public function farkli_para_birimleri_nakit_kasada_ayri_kalir(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $session = $this->cashBook->openSession($admin);

        $this->makePayment($admin, ['payment_method' => 'cash', 'currency' => 'TRY', 'amount' => '7000.00']);
        $this->makePayment($admin, ['payment_method' => 'cash', 'currency' => 'EUR', 'amount' => '300.00']);
        $this->makePayment($admin, ['payment_method' => 'cash', 'currency' => 'RUB', 'amount' => '1500.00']);
        $this->makePayment($admin, ['payment_method' => 'cash', 'currency' => 'USD', 'amount' => '250.00']);
        $this->makePayment($admin, ['payment_method' => 'cash', 'currency' => 'GEL', 'amount' => '80.00']);

        $this->assertSame('7000.00', $this->expectedCash($session, Currency::TRY));
        $this->assertSame('300.00', $this->expectedCash($session, Currency::EUR));
        $this->assertSame('1500.00', $this->expectedCash($session, Currency::RUB));
        $this->assertSame('250.00', $this->expectedCash($session, Currency::USD));
        $this->assertSame('80.00', $this->expectedCash($session, Currency::GEL));
    }

    // ---------------------------------------------- 7-8) devir sonrası kanal

    #[Test]
    public function kart_ve_banka_tahsilatlari_devirle_sifirlanmaz(): void
    {
        $sercan = $this->userWithRole(RoleName::Personel, ['name' => 'Sercan']);
        $ayse = $this->userWithRole(RoleName::Personel, ['name' => 'Ayşe']);

        $sercanSession = $this->cashBook->openSession($sercan);
        $this->makePayment($sercan, ['payment_method' => 'credit_card', 'amount' => '14000.00']);
        $this->makePayment($sercan, ['payment_method' => 'bank_transfer', 'amount' => '5000.00']);
        $this->makePayment($sercan, ['payment_method' => 'cash', 'amount' => '7000.00']);

        $transfer = $this->cashBook->startHandover($sercanSession, $ayse, ['TRY' => Money::fromDecimal('7000.00')], $sercan);
        $ayseSession = $this->cashBook->acceptHandover($transfer, $ayse);

        // Ayşe'nin yeni vardiyası SADECE sayılan nakitle açılır.
        $this->assertSame('7000.00', $this->expectedCash($ayseSession));

        // Sercan'ın kart/banka tahsilatları KALICI, TARİHSEL kayıtlardır —
        // devir bunları silmez/sıfırlamaz.
        $sercanSummary = $this->payments->shiftCollectionSummary($sercanSession);
        $this->assertSame('14000.00', $sercanSummary->creditCardCollections['TRY']->toDecimal());
        $this->assertSame('5000.00', $sercanSummary->bankTransferCollections['TRY']->toDecimal());
    }

    #[Test]
    public function yeni_vardiyanin_kart_ve_banka_toplami_bosla_baslar(): void
    {
        $ayse = $this->userWithRole(RoleName::Personel, ['name' => 'Ayşe']);
        $session = $this->cashBook->openSession($ayse);

        $summary = $this->payments->shiftCollectionSummary($session);

        $this->assertSame([], $summary->creditCardCollections);
        $this->assertSame([], $summary->bankTransferCollections);
        $this->assertSame([], $summary->cashCollections);
    }

    #[Test]
    public function vardiya_kanal_ozeti_sadece_o_vardiya_sahibinin_kayitlarini_sayar(): void
    {
        $sercan = $this->userWithRole(RoleName::Personel, ['name' => 'Sercan']);
        $admin = $this->userWithRole(RoleName::Admin);

        $session = $this->cashBook->openSession($sercan);

        $this->makePayment($sercan, ['payment_method' => 'credit_card', 'amount' => '1000.00']);
        // Admin'in kart tahsilatı Sercan'ın vardiya özetine KARIŞMAMALI.
        $this->makePayment($admin, ['payment_method' => 'credit_card', 'amount' => '9000.00']);

        $summary = $this->payments->shiftCollectionSummary($session);

        $this->assertSame('1000.00', $summary->creditCardCollections['TRY']->toDecimal());
    }

    // ---------------------------------------------------------- 10) iptal

    #[Test]
    public function nakit_tahsilat_iptali_hem_cariyi_hem_kasayi_bir_kez_sifirlar(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $session = $this->cashBook->openSession($admin);

        $payment = $this->makePayment($admin, ['payment_method' => 'cash']);
        $this->assertSame('5000.00', $this->expectedCash($session));

        $this->payments->cancel($payment, 'Mükerrer kayıt', $admin);

        $this->assertSame('0.00', $this->balance());
        $this->assertSame('0.00', $this->expectedCash($session));
        $this->assertSame(2, CashMovement::query()->count(), 'Orijinal + ters kayıt, silinen yok');
    }

    #[Test]
    public function kart_tahsilat_iptali_yalnizca_cariyi_sifirlar_kasaya_dokunmaz(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $session = $this->cashBook->openSession($admin, ['TRY' => Money::fromDecimal('1000.00')]);

        $payment = $this->makePayment($admin, ['payment_method' => 'credit_card']);
        $this->payments->cancel($payment, 'Mükerrer kayıt', $admin);

        $this->assertSame('0.00', $this->balance());
        $this->assertSame('1000.00', $this->expectedCash($session));
        $this->assertSame(0, CashMovement::query()->where('type', CashMovementType::CompanyCollection)->count());
    }

    #[Test]
    public function nakit_iptali_vardiya_degismis_olsa_bile_guncel_vardiyaya_yazilir(): void
    {
        $sercan = $this->userWithRole(RoleName::Personel, ['name' => 'Sercan']);
        $ayse = $this->userWithRole(RoleName::Personel, ['name' => 'Ayşe']);

        $sercanSession = $this->cashBook->openSession($sercan);
        $payment = $this->makePayment($sercan, ['payment_method' => 'cash', 'amount' => '7000.00']);

        // Sercan devreder, Ayşe kabul eder — Sercan'ın vardiyası artık KAPALI.
        $transfer = $this->cashBook->startHandover(
            $sercanSession, $ayse, ['TRY' => Money::fromDecimal('7000.00')], $sercan,
        );
        $ayseSession = $this->cashBook->acceptHandover($transfer, $ayse);

        // Ayşe (güncel vardiya sahibi) tahsilatı iptal eder.
        $this->payments->cancel($payment->fresh(), 'Yanlış tutar girildi', $ayse);

        $this->assertSame('0.00', $this->balance());
        // Düzeltme GÜNCEL (Ayşe'nin) vardiyasına yazılır — kapanmış Sercan
        // vardiyasının geçmişi değişmez, para BUGÜN düzeltilir.
        $this->assertSame('0.00', $this->expectedCash($ayseSession));

        $reversal = CashMovement::query()->where('is_reversal', true)->firstOrFail();
        $this->assertSame($ayseSession->id, $reversal->cash_session_id);
        $this->assertSame(Payment::LEDGER_SOURCE_TYPE, $reversal->reference_type);
        $this->assertSame($payment->id, $reversal->reference_id);
    }

    #[Test]
    public function acik_vardiya_yokken_nakit_iptali_reddedilir_carideki_hicbir_sey_degismez(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $session = $this->cashBook->openSession($admin);

        $payment = $this->makePayment($admin, ['payment_method' => 'cash']);

        // Vardiya kapatılır: düzeltme yazılacak fiziksel kasa artık yok.
        $this->cashBook->closeSession($session, ['TRY' => Money::fromDecimal('5000.00')], $admin);

        $this->expectException(NoOpenCashSession::class);

        try {
            $this->payments->cancel($payment->fresh(), 'Yanlış tutar', $admin);
        } finally {
            // Atomiklik: cari de GERİ ALINMIŞ olmalı — kısmi iptal yoktur.
            $this->assertTrue($payment->fresh()->isActive());
            $this->assertSame('-5000.00', $this->balance());
            $this->assertSame(1, LedgerEntry::query()->count());
        }
    }

    // ---------------------------------------------------- oturum kuralları

    #[Test]
    public function acik_vardiya_yokken_nakit_tahsilat_reddedilir_hicbir_kayit_olusmaz(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $this->expectException(NoOpenCashSession::class);

        try {
            $this->makePayment($admin, ['payment_method' => 'cash']);
        } finally {
            $this->assertSame(0, Payment::query()->count(), 'Kasa yazılamadıysa tahsilat da oluşmamalı');
            $this->assertSame(0, LedgerEntry::query()->count());
        }
    }

    #[Test]
    public function baskasinin_vardiyasindayken_nakit_tahsilat_reddedilir(): void
    {
        $sercan = $this->userWithRole(RoleName::Personel, ['name' => 'Sercan']);
        $ayse = $this->userWithRole(RoleName::Personel, ['name' => 'Ayşe']);

        $this->cashBook->openSession($sercan);

        $this->expectException(CashSessionNotOwnedByActor::class);

        try {
            // Ayşe kasayı yönetmiyor; admin/patron dahil kimse başkasının
            // vardiyasına hareket yazamaz (CLAUDE.md §8/Kasa Defteri).
            $this->makePayment($ayse, ['payment_method' => 'cash']);
        } finally {
            $this->assertSame(0, Payment::query()->count());
            $this->assertSame(0, LedgerEntry::query()->count());
        }
    }

    #[Test]
    public function vardiya_sahibi_admin_bile_olsa_baskasinin_vardiyasina_nakit_tahsilat_giremez(): void
    {
        $personel = $this->userWithRole(RoleName::Personel);
        $admin = $this->userWithRole(RoleName::Admin);

        $this->cashBook->openSession($personel);

        $this->expectException(CashSessionNotOwnedByActor::class);

        $this->makePayment($admin, ['payment_method' => 'cash']);
    }

    // -------------------------------------------------- doğrudan ters kayıt

    #[Test]
    public function baglantili_kasa_hareketi_genel_ekrandan_dogrudan_ters_kaydedilemez(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $this->cashBook->openSession($admin);

        $payment = $this->makePayment($admin, ['payment_method' => 'cash']);
        $movement = CashMovement::query()->where('reference_id', $payment->id)->firstOrFail();

        $this->expectException(CannotReverseMovement::class);

        // allowLinkedSource verilmeden çağrılan genel yol — Kasa Hareketleri
        // ekranının kullandığı TAM budur.
        $this->cashBook->reverseMovement($movement, $admin);
    }

    #[Test]
    public function baglantili_hareketin_iptal_butonu_kasa_hareketleri_ekraninda_gorunmez(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $this->cashBook->openSession($admin);

        $this->makePayment($admin, ['payment_method' => 'cash']);

        $this->actingAs($admin)
            ->get(route('cashbook.movements.index'))
            ->assertOk()
            ->assertSee('Cari Tahsilat')
            ->assertDontSee('İptal et');
    }

    // ------------------------------------------------------ 12) eski veri

    #[Test]
    public function baglantisiz_eski_nakit_tahsilat_sorunsuz_iptal_edilir(): void
    {
        // v2.1.1-A'dan ÖNCEKİ bir kayıt: payment_method=cash ama hiçbir kasa
        // hareketine bağlı DEĞİL (factory doğrudan DB'ye yazar, servisten geçmez).
        $admin = $this->userWithRole(RoleName::Admin);
        $legacy = Payment::factory()->create([
            'company_id' => $this->company->id,
            'payment_method' => 'cash',
            'amount' => '3000.00',
            'currency' => 'TRY',
        ]);
        $this->ledger->credit(
            companyId: $this->company->id,
            amount: Money::fromDecimal('3000.00'),
            currency: Currency::TRY,
            entryDate: $legacy->payment_date,
            description: 'Eski kayıt',
            sourceType: Payment::LEDGER_SOURCE_TYPE,
            sourceId: $legacy->id,
            actor: $admin,
        );

        // Kasıtlı: hiçbir vardiya açık değil — eski kayıtta bağlı hareket
        // olmadığı için buna hiç ihtiyaç yok.
        $this->payments->cancel($legacy, 'Geçmiş dönem düzeltmesi', $admin);

        $this->assertTrue($legacy->fresh()->isCancelled());
        $this->assertSame('0.00', $this->balance());
    }

    // --------------------------------------------------------- 13) yetki

    #[Test]
    public function ozellik_yeni_bir_yetki_gerektirmez_personel_zaten_ikisine_de_sahiptir(): void
    {
        $personel = $this->userWithRole(RoleName::Personel);

        $this->assertTrue($personel->can('payments.create'));
        $this->assertTrue($personel->can('cashbook.operate'));
    }
}
