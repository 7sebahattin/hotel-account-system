<?php

declare(strict_types=1);

namespace Tests\Feature\CashBook;

use App\Modules\CashBook\Enums\CashMovementType;
use App\Modules\CashBook\Enums\CashSessionStatus;
use App\Modules\CashBook\Enums\CashTransferStatus;
use App\Modules\CashBook\Exceptions\CannotReverseMovement;
use App\Modules\CashBook\Exceptions\CashTransferNotPending;
use App\Modules\CashBook\Exceptions\InvalidCashMovement;
use App\Modules\CashBook\Models\CashCount;
use App\Modules\CashBook\Models\CashSession;
use App\Modules\Core\Enums\Currency;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;

/**
 * Personelden personele devir — İKİ TARAFLI onay zinciri.
 *
 * Kullanıcı talimatındaki gerçek akış: X → Y → (patrona teslim) → Z.
 */
class CashTransferTest extends CashBookTestCase
{
    #[Test]
    public function devir_kabul_edilene_kadar_tamamlanmaz(): void
    {
        $x = $this->personel('X');
        $y = $this->personel('Y');
        $session = $this->openSession($x, ['TRY' => '7000.00']);

        $transfer = $this->cash->startHandover($session, $y, $this->amounts(['TRY' => '7000.00']), $x);

        $this->assertSame(CashTransferStatus::Pending, $transfer->status);
        $this->assertNull($transfer->to_session_id);

        // Kasa hâlâ X'in vardiyasında görünür.
        $this->assertSame(CashSessionStatus::Open, $session->refresh()->status);
        $this->assertSame($session->id, $this->cash->activeSession()?->id);
    }

    #[Test]
    public function devir_beklerken_vardiyaya_yeni_hareket_girilemez(): void
    {
        $x = $this->personel('X');
        $y = $this->personel('Y');
        $session = $this->openSession($x, ['TRY' => '1000.00']);

        $this->cash->startHandover($session, $y, $this->amounts(['TRY' => '1000.00']), $x);

        $this->expectException(InvalidCashMovement::class);

        $this->record($session->refresh(), CashMovementType::RoomIncome, '500.00', $x);
    }

    /**
     * §7 kullanıcı talimatı: bekleyen devir yalnızca YENİ hareketi değil, ters
     * kaydı ve kapanışı da engellemelidir — üçü de aynı `lockOpenSession()`
     * kapısından geçer (CashBookService).
     */
    #[Test]
    public function devir_beklerken_mevcut_hareket_ters_kaydedilemez(): void
    {
        $x = $this->personel('X');
        $y = $this->personel('Y');
        $session = $this->openSession($x, ['TRY' => '1000.00']);
        $movement = $this->record($session->refresh(), CashMovementType::RoomIncome, '500.00', $x);

        $this->cash->startHandover($session->refresh(), $y, $this->amounts(['TRY' => '1500.00']), $x);

        $this->expectException(CannotReverseMovement::class);

        $this->cash->reverseMovement($movement, $x);
    }

    #[Test]
    public function devir_beklerken_vardiya_devir_olmadan_kapatilamaz(): void
    {
        $x = $this->personel('X');
        $y = $this->personel('Y');
        $session = $this->openSession($x, ['TRY' => '1000.00']);

        $this->cash->startHandover($session->refresh(), $y, $this->amounts(['TRY' => '1000.00']), $x);

        $this->expectException(InvalidCashMovement::class);

        $this->cash->closeSession($session->refresh(), $this->amounts(['TRY' => '1000.00']), $x);
    }

    #[Test]
    public function hedef_kullanici_kabul_edince_zincir_tamamlanir(): void
    {
        $x = $this->personel('X');
        $y = $this->personel('Y');
        $xSession = $this->openSession($x, ['TRY' => '7000.00', 'EUR' => '300.00']);

        $transfer = $this->cash->startHandover(
            $xSession,
            $y,
            $this->amounts(['TRY' => '7000.00', 'EUR' => '300.00']),
            $x,
        );

        $ySession = $this->cash->acceptHandover($transfer, $y);

        // 1) X'in vardiyası kapandı.
        $this->assertSame(CashSessionStatus::Closed, $xSession->refresh()->status);
        $this->assertSame($x->id, $xSession->closed_by);

        // 2) Y'nin vardiyası açık ve TEK açık vardiya.
        $this->assertSame(CashSessionStatus::Open, $ySession->status);
        $this->assertSame($y->id, $ySession->opened_by);
        $this->assertSame($ySession->id, $this->cash->activeSession()?->id);

        // 3) Devredilen tutarlar Y'nin AÇILIŞ bakiyesi oldu.
        $this->assertSame('7000.00', $this->expected($ySession, Currency::TRY));
        $this->assertSame('300.00', $this->expected($ySession, Currency::EUR));

        // 4) Devir kaydı sonuçlandı.
        $transfer->refresh();
        $this->assertSame(CashTransferStatus::Accepted, $transfer->status);
        $this->assertSame($ySession->id, $transfer->to_session_id);
        $this->assertNotNull($transfer->responded_at);
    }

    #[Test]
    public function devirde_kapanis_sayimi_ve_fark_otomatik_yazilir(): void
    {
        $x = $this->personel('X');
        $y = $this->personel('Y');
        $session = $this->openSession($x, ['TRY' => '1000.00']);
        $this->record($session, CashMovementType::RoomIncome, '500.00', $x);

        // Beklenen 1500 ama yalnızca 1400 devrediliyor: 100 eksik.
        $transfer = $this->cash->startHandover($session, $y, $this->amounts(['TRY' => '1400.00']), $x);
        $this->cash->acceptHandover($transfer, $y);

        $count = CashCount::query()
            ->where('cash_session_id', $session->id)
            ->where('currency', 'TRY')
            ->firstOrFail();

        $this->assertSame('1500.00', $count->expectedMoney()->toDecimal());
        $this->assertSame('1400.00', $count->countedMoney()->toDecimal());
        $this->assertSame('-100.00', $count->differenceMoney()->toDecimal());
    }

    #[Test]
    public function yanlis_kullanici_devri_kabul_edemez(): void
    {
        $x = $this->personel('X');
        $y = $this->personel('Y');
        $z = $this->personel('Z');
        $session = $this->openSession($x, ['TRY' => '1000.00']);

        $transfer = $this->cash->startHandover($session, $y, $this->amounts(['TRY' => '1000.00']), $x);

        // Z devrin hedefi değil.
        $this->assertFalse($z->can('accept', $transfer));
        // X kendi devrini kabul edemez.
        $this->assertFalse($x->can('accept', $transfer));
        // Y edebilir.
        $this->assertTrue($y->can('accept', $transfer));
    }

    #[Test]
    public function kabul_edilmis_devir_tekrar_kabul_edilemez(): void
    {
        $x = $this->personel('X');
        $y = $this->personel('Y');
        $session = $this->openSession($x, ['TRY' => '1000.00']);

        $transfer = $this->cash->startHandover($session, $y, $this->amounts(['TRY' => '1000.00']), $x);
        $this->cash->acceptHandover($transfer, $y);

        $this->expectException(CashTransferNotPending::class);

        $this->cash->acceptHandover($transfer->refresh(), $y);
    }

    #[Test]
    public function ayni_vardiyadan_ikinci_bekleyen_devir_acilamaz(): void
    {
        $x = $this->personel('X');
        $y = $this->personel('Y');
        $z = $this->personel('Z');
        $session = $this->openSession($x, ['TRY' => '1000.00']);

        $this->cash->startHandover($session, $y, $this->amounts(['TRY' => '1000.00']), $x);

        $this->expectException(InvalidCashMovement::class);

        $this->cash->startHandover($session->refresh(), $z, $this->amounts(['TRY' => '1000.00']), $x);
    }

    #[Test]
    public function iptal_edilen_devirden_sonra_yeni_devir_baslatilabilir(): void
    {
        $x = $this->personel('X');
        $y = $this->personel('Y');
        $z = $this->personel('Z');
        $session = $this->openSession($x, ['TRY' => '1000.00']);

        $transfer = $this->cash->startHandover($session, $y, $this->amounts(['TRY' => '1000.00']), $x);
        $this->cash->cancelHandover($transfer, $x);

        $this->assertSame(CashTransferStatus::Cancelled, $transfer->refresh()->status);

        // Vardiya açık kaldı ve yeni devir başlatılabiliyor.
        $second = $this->cash->startHandover($session->refresh(), $z, $this->amounts(['TRY' => '1000.00']), $x);

        $this->assertSame(CashTransferStatus::Pending, $second->status);
        $this->assertSame(CashSessionStatus::Open, $session->refresh()->status);
    }

    #[Test]
    public function kendine_devir_yapilamaz(): void
    {
        $x = $this->personel('X');
        $session = $this->openSession($x, ['TRY' => '1000.00']);

        $this->expectException(InvalidCashMovement::class);

        $this->cash->startHandover($session, $x, $this->amounts(['TRY' => '1000.00']), $x);
    }

    #[Test]
    public function kendine_devir_veritabani_duzeyinde_de_reddedilir(): void
    {
        $x = $this->personel('X');
        $session = $this->openSession($x);

        $this->expectException(QueryException::class);

        DB::table('cash_transfers')->insert([
            'from_session_id' => $session->id,
            'from_user_id' => $x->id,
            'to_user_id' => $x->id,
            'status' => 'pending',
            'initiated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Kullanıcı talimatı §1'deki tam senaryo: X → Y, Y patrona teslim eder,
     * kalanı Z'ye devreder.
     */
    #[Test]
    public function tam_devir_zinciri_ucten_uca_calisir(): void
    {
        $x = $this->personel('X');
        $y = $this->personel('Y');
        $z = $this->personel('Z');

        // X: 9 hareket sonunda 7.000 TRY + 300 EUR
        $xSession = $this->openSession($x, ['TRY' => '5000.00', 'EUR' => '300.00']);
        $this->record($xSession, CashMovementType::RoomIncome, '2000.00', $x);
        $this->assertSame('7000.00', $this->expected($xSession, Currency::TRY));

        // X → Y
        $toY = $this->cash->startHandover($xSession, $y, $this->amounts(['TRY' => '7000.00', 'EUR' => '300.00']), $x);
        $ySession = $this->cash->acceptHandover($toY, $y);

        // Y: 7.000 → 14.000 TRY, 300 → 400 EUR
        $this->record($ySession, CashMovementType::RoomIncome, '7000.00', $y);
        $this->record($ySession, CashMovementType::ExtraIncome, '100.00', $y, Currency::EUR);
        $this->assertSame('14000.00', $this->expected($ySession, Currency::TRY));
        $this->assertSame('400.00', $this->expected($ySession, Currency::EUR));

        // Y patrona 12.000 TRY teslim eder — GİDER DEĞİL, kasa çıkışı.
        $this->record($ySession, CashMovementType::CashOut, '12000.00', $y, counterparty: 'Patron');
        $this->assertSame('2000.00', $this->expected($ySession, Currency::TRY));

        $yTotals = $this->cash->totalsFor($ySession->refresh())['TRY'];
        $this->assertSame('0.00', $yTotals->expense->toDecimal(), 'Patrona teslim gider üretmemeli');

        // Y → Z: 2.000 TRY + 400 EUR
        $toZ = $this->cash->startHandover($ySession, $z, $this->amounts(['TRY' => '2000.00', 'EUR' => '400.00']), $y);
        $zSession = $this->cash->acceptHandover($toZ, $z);

        $this->assertSame('2000.00', $this->expected($zSession, Currency::TRY));
        $this->assertSame('400.00', $this->expected($zSession, Currency::EUR));

        // Zincirin sonunda TEK açık vardiya var ve o Z'nin.
        $this->assertSame(1, CashSession::query()->where('status', CashSessionStatus::Open->value)->count());
        $this->assertSame($z->id, $this->cash->activeSession()?->opened_by);

        // Geçmiş vardiyalar kapalı ve sayımları yazılmış.
        $this->assertSame(CashSessionStatus::Closed, $xSession->refresh()->status);
        $this->assertSame(CashSessionStatus::Closed, $ySession->refresh()->status);
        $this->assertSame(2, CashCount::query()->where('cash_session_id', $ySession->id)->count());
    }
}
