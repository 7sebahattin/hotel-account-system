<?php

declare(strict_types=1);

namespace Tests\Feature\CashBook;

use App\Modules\CashBook\Enums\CashMovementType;
use App\Modules\CashBook\Enums\CashSessionStatus;
use App\Modules\CashBook\Enums\CashTransferStatus;
use App\Modules\CashBook\Exceptions\CashRecordIsImmutable;
use App\Modules\CashBook\Models\CashCount;
use App\Modules\CashBook\Models\CashMovement;
use App\Modules\CashBook\Models\CashSession;
use App\Modules\CashBook\Models\CashTransferAmount;
use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;

/**
 * Gün 10 red-team doğrulaması — gerçek otel kasa senaryolarıyla domain/matematik
 * doğruluğunu kanıtlar.
 *
 * Ana zincir: X (ilk açılış) → 9 hareket → X→Y devri → Y (5 hareket + ara kasa) →
 * Y→Z devri → Z. Her adımda expected cash elle hesaplanan rakamla karşılaştırılır.
 */
class CashBookScenarioTest extends CashBookTestCase
{
    // ============================================================ §1-3: ANA ZİNCİR

    #[Test]
    public function x_9_islemle_tam_7000_try_300_eur_uretir_ve_y_ye_devreder(): void
    {
        $x = $this->personel('X');
        $y = $this->personel('Y');

        // Açılış 0/0 — kullanıcı talimatı §1: "Başlangıç: 0 TRY, 0 EUR".
        $xSession = $this->openSession($x, []);
        $this->assertSame('0.00', $this->expected($xSession, Currency::TRY));
        $this->assertSame('0.00', $this->expected($xSession, Currency::EUR));

        // 9 GERÇEK hareket, tam 7.000 TRY + 300 EUR'ya varacak şekilde.
        $this->record($xSession, CashMovementType::RoomIncome, '2500.00', $x, Currency::TRY);   // 1
        $this->record($xSession, CashMovementType::RoomIncome, '3000.00', $x, Currency::TRY);   // 2
        $this->record($xSession, CashMovementType::ExtraIncome, '800.00', $x, Currency::TRY);   // 3
        $this->record($xSession, CashMovementType::ExtraIncome, '200.00', $x, Currency::TRY);   // 4
        $this->record($xSession, CashMovementType::CashIn, '1000.00', $x, Currency::TRY);       // 5
        $this->record($xSession, CashMovementType::Expense, '500.00', $x, Currency::TRY);       // 6
        $this->record($xSession, CashMovementType::RoomIncome, '250.00', $x, Currency::EUR);    // 7
        $this->record($xSession, CashMovementType::ExtraIncome, '30.00', $x, Currency::EUR);    // 8
        $this->record($xSession, CashMovementType::CashIn, '20.00', $x, Currency::EUR);         // 9

        // TRY: 2500+3000+800+200+1000-500 = 7000. EUR: 250+30+20 = 300.
        $this->assertSame('7000.00', $this->expected($xSession, Currency::TRY));
        $this->assertSame('300.00', $this->expected($xSession, Currency::EUR));

        $this->assertSame(9, CashMovement::query()->where('cash_session_id', $xSession->id)->count());

        // X fiziksel sayım yapıyor: tam beklenenle eşleşiyor (fark yok).
        // X → Y devrini başlatır, tam beklenen tutarları devreder.
        $transfer = $this->cash->startHandover(
            $xSession,
            $y,
            $this->amounts(['TRY' => '7000.00', 'EUR' => '300.00']),
            $x,
        );

        $ySession = $this->cash->acceptHandover($transfer, $y);

        // Kontrol: X CLOSED, Y OPEN.
        $this->assertSame(CashSessionStatus::Closed, $xSession->refresh()->status);
        $this->assertSame(CashSessionStatus::Open, $ySession->status);

        // Y opening = X'in devrettiği TAM tutar.
        $this->assertSame('7000.00', $this->expected($ySession, Currency::TRY));
        $this->assertSame('300.00', $this->expected($ySession, Currency::EUR));

        // Transfer accepted.
        $transfer->refresh();
        $this->assertSame(CashTransferStatus::Accepted, $transfer->status);
        $this->assertSame($x->id, $transfer->from_user_id);
        $this->assertSame($y->id, $transfer->to_user_id);
        $this->assertSame($ySession->id, $transfer->to_session_id);

        // Devir tutarları SONRADAN DEĞİŞTİRİLEMİYOR: ne route var, ne model
        // ne de veritabanı buna izin verir (üç katman, ledger/cash_movements
        // ile aynı ilke).
        $editRoutes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => str_contains($r->uri(), 'transfers') && in_array('PUT', $r->methods(), true));
        $this->assertCount(0, $editRoutes, 'Devir tutarlarını değiştiren bir PUT route olmamalı');

        $amountRow = CashTransferAmount::query()
            ->where('cash_transfer_id', $transfer->id)->firstOrFail();

        $this->assertThrows(
            fn () => $amountRow->update(['amount' => '999999.00']),
            CashRecordIsImmutable::class,
        );
        $this->assertThrows(
            fn () => DB::table('cash_transfer_amounts')
                ->where('id', $amountRow->id)->update(['amount' => '999999.00']),
            QueryException::class,
        );
        $this->assertSame(
            $amountRow->amount,
            $amountRow->fresh()->amount,
            'Devir tutarı hiçbir yolla değişmemiş olmalı'
        );
    }

    #[Test]
    public function y_5_hareketle_14000_try_400_eur_uretir_ara_kasa_gider_sayilmaz(): void
    {
        [$x, $y] = [$this->personel('X'), $this->personel('Y')];
        $xSession = $this->openSession($x, ['TRY' => '7000.00', 'EUR' => '300.00']);
        $transfer = $this->cash->startHandover($xSession, $y, $this->amounts(['TRY' => '7000.00', 'EUR' => '300.00']), $x);
        $ySession = $this->cash->acceptHandover($transfer, $y);

        // Y 5 GERÇEK hareket yapıyor → 14.000 TRY / 400 EUR.
        $this->record($ySession, CashMovementType::RoomIncome, '5000.00', $y, Currency::TRY);   // 1
        $this->record($ySession, CashMovementType::RoomIncome, '2000.00', $y, Currency::TRY);   // 2
        $this->record($ySession, CashMovementType::ExtraIncome, '100.00', $y, Currency::EUR);   // 3
        $this->record($ySession, CashMovementType::CashIn, '100.00', $y, Currency::TRY);        // 4
        $this->record($ySession, CashMovementType::Expense, '100.00', $y, Currency::TRY);       // 5

        // TRY: 7000+5000+2000+100-100 = 14000. EUR: 300+100 = 400.
        $this->assertSame('14000.00', $this->expected($ySession, Currency::TRY));
        $this->assertSame('400.00', $this->expected($ySession, Currency::EUR));

        // Y patrona 12.000 TRY teslim ediyor — CASH_OUT'tur, GİDER DEĞİLDİR.
        $before = $this->cash->totalsFor($ySession->refresh())['TRY'];
        $movement = $this->record($ySession, CashMovementType::CashOut, '12000.00', $y, Currency::TRY, 'Patron');
        $after = $this->cash->totalsFor($ySession->refresh())['TRY'];

        $this->assertSame(CashMovementType::CashOut, $movement->type);
        // Testte zaten gerçek bir 100 TRY'lik gider vardı (5. hareket); ara
        // kasa teslimi o toplamı DEĞİŞTİRMEMELİ — -100'de sabit kalmalı.
        $this->assertSame('-100.00', $before->expense->toDecimal());
        $this->assertSame($before->expense->toDecimal(), $after->expense->toDecimal(), 'Ara kasa teslimi gider toplamına GİRMEMELİ');
        $this->assertSame('-12000.00', $after->cashOut->toDecimal());
        $this->assertSame('2000.00', $this->expected($ySession, Currency::TRY), '14000 - 12000 = 2000');

        // Fiziksel kasayı azalttı ama audit trail oluştu.
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => 'cash_movement',
            'auditable_id' => $movement->id,
        ]);

        // income/expense raporunda gider gibi görünmüyor: affectsIncomeStatement false.
        $this->assertFalse(CashMovementType::CashOut->affectsIncomeStatement());
    }

    #[Test]
    public function y_z_devrinde_hicbir_donusum_yapilmaz(): void
    {
        [$x, $y, $z] = [$this->personel('X'), $this->personel('Y'), $this->personel('Z')];
        $xSession = $this->openSession($x, ['TRY' => '7000.00', 'EUR' => '300.00']);
        $t1 = $this->cash->startHandover($xSession, $y, $this->amounts(['TRY' => '7000.00', 'EUR' => '300.00']), $x);
        $ySession = $this->cash->acceptHandover($t1, $y);

        $this->record($ySession, CashMovementType::RoomIncome, '7000.00', $y, Currency::TRY);
        $this->record($ySession, CashMovementType::ExtraIncome, '100.00', $y, Currency::EUR);
        $this->record($ySession, CashMovementType::CashOut, '12000.00', $y, Currency::TRY, 'Patron');

        // 7000+7000-12000 = 2000 TRY; 300+100 = 400 EUR.
        $this->assertSame('2000.00', $this->expected($ySession, Currency::TRY));
        $this->assertSame('400.00', $this->expected($ySession, Currency::EUR));

        // Y sayım yapıyor: 2000 TRY, 400 EUR — Y → Z devri.
        $t2 = $this->cash->startHandover($ySession, $z, $this->amounts(['TRY' => '2000.00', 'EUR' => '400.00']), $y);
        $zSession = $this->cash->acceptHandover($t2, $z);

        $this->assertSame(CashSessionStatus::Closed, $ySession->refresh()->status);
        $this->assertSame(CashSessionStatus::Open, $zSession->status);
        $this->assertSame('2000.00', $this->expected($zSession, Currency::TRY));
        $this->assertSame('400.00', $this->expected($zSession, Currency::EUR));

        // TRY hiçbir zaman EUR'ya ya da tam tersi çevrilmedi: iki para birimi
        // her adımda BAĞIMSIZ tutuldu (400 hiçbir yerde TRY'ye eklenmedi/çıkmadı).
        $tryMovements = CashMovement::query()->where('currency', 'TRY')->pluck('type')->all();
        $eurMovements = CashMovement::query()->where('currency', 'EUR')->pluck('type')->all();
        $this->assertNotEmpty($tryMovements);
        $this->assertNotEmpty($eurMovements);

        // Sadece TEK vardiya açık kalır (Z'ninki).
        $this->assertSame(1, CashSession::query()->where('status', 'open')->count());
    }

    // ==================================================== §4: SIFIR BAKİYELİ EUR

    /**
     * Y patrona TRY + TÜM EUR'yu teslim ederse (EUR sıfırlanır), Z'ye devirde
     * sıfır değerli gereksiz bir EUR açılış hareketi ÜRETİLMEMELİDİR.
     */
    #[Test]
    public function sifir_bakiyeli_para_birimi_yeni_vardiyada_gereksiz_satir_uretmez(): void
    {
        [$x, $y, $z] = [$this->personel('X'), $this->personel('Y'), $this->personel('Z')];
        $xSession = $this->openSession($x, ['TRY' => '7000.00', 'EUR' => '300.00']);
        $t1 = $this->cash->startHandover($xSession, $y, $this->amounts(['TRY' => '7000.00', 'EUR' => '300.00']), $x);
        $ySession = $this->cash->acceptHandover($t1, $y);

        $this->record($ySession, CashMovementType::RoomIncome, '7000.00', $y, Currency::TRY);
        $this->record($ySession, CashMovementType::ExtraIncome, '100.00', $y, Currency::EUR);
        // Y patrona TRY VE TÜM EUR'yu teslim ediyor.
        $this->record($ySession, CashMovementType::CashOut, '12000.00', $y, Currency::TRY, 'Patron');
        $this->record($ySession, CashMovementType::CashOut, '400.00', $y, Currency::EUR, 'Patron');

        $this->assertSame('2000.00', $this->expected($ySession, Currency::TRY));
        $this->assertSame('0.00', $this->expected($ySession, Currency::EUR));

        // Z'ye YALNIZCA TRY devredilir; EUR alanı boş/0 bırakılır.
        $t2 = $this->cash->startHandover($ySession, $z, $this->amounts(['TRY' => '2000.00']), $y);
        $zSession = $this->cash->acceptHandover($t2, $z);

        $this->assertSame('2000.00', $this->expected($zSession, Currency::TRY));

        // Z'nin vardiyasında EUR hiç görülmedi: 0 değerli 'opening' hareketi YOK.
        $eurMovements = CashMovement::query()
            ->where('cash_session_id', $zSession->id)
            ->where('currency', 'EUR')
            ->count();
        $this->assertSame(0, $eurMovements, 'Sıfır bakiyeli EUR için gereksiz açılış hareketi üretilmemeli');

        // totalsFor()'da EUR anahtarı bile hiç görünmez (hiç hareket yok).
        $this->assertArrayNotHasKey('EUR', $this->cash->totalsFor($zSession));

        // Y'nin (kapanan) vardiyasında EUR sayım satırı da YOKTUR: hem
        // beklenen hem devredilen 0.00 olduğunda `writeCounts()` satırı hiç
        // yazmaz — gereksiz sıfır-değerli satır kuralı sayım tablosu için de
        // geçerlidir, yalnızca hareket tablosu için değil.
        $yEurCount = CashCount::query()
            ->where('cash_session_id', $ySession->id)
            ->where('currency', 'EUR')
            ->first();
        $this->assertNull($yEurCount, 'Beklenen ve sayılan ikisi de 0 olduğunda gereksiz sayım satırı yazılmamalı');
    }

    // ============================================== §5-6: EXPECTED vs COUNTED

    /**
     * KRİTİK KURAL: yeni vardiyanın açılış bakiyesi EXPECTED değil, GERÇEKTEN
     * DEVREDİLEN (sayılan/onaylanan) tutardan oluşur.
     *
     * Senaryo: expected 7.000 TRY ama X yalnızca 6.900 TRY sayıp devrediyor
     * (100 TRY eksik). Y'nin açılışı 6.900 olmalı — 7.000 DEĞİL. Aksi hâlde
     * fiziksel olmayan 100 TRY sistemde "yaratılmış" olurdu.
     */
    #[Test]
    public function devirde_yeni_vardiyanin_acilisi_expected_degil_counted_devredilen_tutardan_olusur(): void
    {
        $x = $this->personel('X');
        $y = $this->personel('Y');
        $xSession = $this->openSession($x, ['TRY' => '7000.00', 'EUR' => '300.00']);

        $this->assertSame('7000.00', $this->expected($xSession, Currency::TRY));

        // X yalnızca 6.900 TRY devrediyor (kasa eksiği var) — EUR tam.
        $transfer = $this->cash->startHandover(
            $xSession,
            $y,
            $this->amounts(['TRY' => '6900.00', 'EUR' => '300.00']),
            $x,
        );
        $ySession = $this->cash->acceptHandover($transfer, $y);

        // Y'nin açılışı DEVREDİLEN (6.900) tutardır — EXPECTED (7.000) DEĞİL.
        $this->assertSame('6900.00', $this->expected($ySession, Currency::TRY),
            'Yeni vardiyanın açılışı fiziksel olarak devredilen tutar olmalı, beklenen değil');
        $this->assertSame('300.00', $this->expected($ySession, Currency::EUR));

        // X'in kapanış sayımında fark AÇIKÇA görünür: expected 7000, counted 6900.
        $xCount = CashCount::query()
            ->where('cash_session_id', $xSession->id)
            ->where('currency', 'TRY')
            ->firstOrFail();
        $this->assertSame('7000.00', $xCount->expectedMoney()->toDecimal());
        $this->assertSame('6900.00', $xCount->countedMoney()->toDecimal());
        $this->assertSame('-100.00', $xCount->differenceMoney()->toDecimal());

        // 100 TRY hiçbir yerde "fiziksel olmayan" para olarak yaratılmadı:
        // Y'nin toplam açılış hareketi TAM 6900'dür, 7000 değil.
        $openingMovement = CashMovement::query()
            ->where('cash_session_id', $ySession->id)
            ->where('type', CashMovementType::Opening->value)
            ->where('currency', 'TRY')
            ->firstOrFail();
        $this->assertSame('6900.00', $openingMovement->amountMoney()->toDecimal());
    }

    /**
     * Aynı kural kasa FAZLASI için de geçerli: yeni vardiyanın açılışı yine
     * GERÇEKTEN devredilen/sayılan tutardır; fazla para otomatik gelir sayılmaz.
     */
    #[Test]
    public function kasa_fazlasinda_da_yeni_vardiyanin_acilisi_devredilen_tutardan_olusur(): void
    {
        $x = $this->personel('X');
        $y = $this->personel('Y');
        $xSession = $this->openSession($x, ['TRY' => '7000.00']);

        // X 7.100 TRY sayıp devrediyor (100 TRY fazla).
        $transfer = $this->cash->startHandover($xSession, $y, $this->amounts(['TRY' => '7100.00']), $x);
        $ySession = $this->cash->acceptHandover($transfer, $y);

        $this->assertSame('7100.00', $this->expected($ySession, Currency::TRY));

        $xCount = CashCount::query()->where('cash_session_id', $xSession->id)->where('currency', 'TRY')->firstOrFail();
        $this->assertSame('7000.00', $xCount->expectedMoney()->toDecimal());
        $this->assertSame('7100.00', $xCount->countedMoney()->toDecimal());
        $this->assertSame('100.00', $xCount->differenceMoney()->toDecimal());

        // Fazla 100 TRY bir 'room_income'/'extra_income' hareketi olarak
        // OTOMATİK YAZILMADI — yalnızca açılış hareketi olarak Y'ye geçti.
        $incomeAtY = CashMovement::query()
            ->where('cash_session_id', $ySession->id)
            ->whereIn('type', [CashMovementType::RoomIncome->value, CashMovementType::ExtraIncome->value])
            ->count();
        $this->assertSame(0, $incomeAtY, 'Kasa fazlası otomatik gelir sayılmamalı');

        $openingMovement = CashMovement::query()
            ->where('cash_session_id', $ySession->id)
            ->where('type', CashMovementType::Opening->value)
            ->firstOrFail();
        $this->assertSame('7100.00', $openingMovement->amountMoney()->toDecimal());
    }

    // =========================================================== §5: KASA EKSİĞİ

    #[Test]
    public function kasa_eksigi_devir_olmadan_kapanista_da_kayda_gecer_ve_gider_uretmez(): void
    {
        $user = $this->personel();
        $session = $this->openSession($user, ['TRY' => '7000.00', 'EUR' => '300.00']);

        // Expected 7000/300, counted 6900/300.
        $closed = $this->cash->closeSession(
            $session,
            $this->amounts(['TRY' => '6900.00', 'EUR' => '300.00']),
            $user,
        );

        $tryCount = CashCount::query()->where('cash_session_id', $closed->id)->where('currency', 'TRY')->firstOrFail();
        $eurCount = CashCount::query()->where('cash_session_id', $closed->id)->where('currency', 'EUR')->firstOrFail();

        $this->assertSame('-100.00', $tryCount->differenceMoney()->toDecimal());
        $this->assertSame('0.00', $eurCount->differenceMoney()->toDecimal());

        // Fark OTOMATİK bir expense hareketi ÜRETMEDİ: kapanan vardiyanın
        // hareket sayısı sayımdan ETKİLENMEDİ (yalnızca iki açılış hareketi
        // var — biri TRY, biri EUR için, çünkü vardiya iki para biriminde
        // açıldı).
        $this->assertSame(
            2,
            CashMovement::query()->where('cash_session_id', $closed->id)->count(),
            'Kapanış sayım farkı otomatik bir hareket üretmemeli'
        );
    }

    // ======================================================== §9: TERS KAYIT

    #[Test]
    public function room_income_ters_kayit_net_fiziksel_etki_sifirdir(): void
    {
        $user = $this->personel();
        $session = $this->openSession($user, ['TRY' => '1000.00']);

        $movement = $this->record($session, CashMovementType::RoomIncome, '2000.00', $user);
        $this->cash->reverseMovement($movement, $user);

        $this->assertSame('1000.00', $this->expected($session), 'Net fiziksel etki sıfır olmalı (yalnızca açılış kalır)');

        $totals = $this->cash->totalsFor($session->refresh())['TRY'];
        $this->assertSame('0.00', $totals->roomIncome->toDecimal());
        $this->assertSame('0.00', $totals->income()->toDecimal());
    }

    #[Test]
    public function expense_ters_kayit_net_fiziksel_etki_sifirdir(): void
    {
        $user = $this->personel();
        $session = $this->openSession($user, ['TRY' => '1000.00']);

        $movement = $this->record($session, CashMovementType::Expense, '500.00', $user);
        $this->assertSame('500.00', $this->expected($session));

        $this->cash->reverseMovement($movement, $user);

        $this->assertSame('1000.00', $this->expected($session));
        $totals = $this->cash->totalsFor($session->refresh())['TRY'];
        $this->assertSame('0.00', $totals->expense->toDecimal());
    }

    #[Test]
    public function cash_out_ters_kayit_net_fiziksel_etki_sifirdir(): void
    {
        $user = $this->personel();
        $session = $this->openSession($user, ['TRY' => '5000.00']);

        $movement = $this->record($session, CashMovementType::CashOut, '1000.00', $user, counterparty: 'Patron');
        $this->assertSame('4000.00', $this->expected($session));

        $this->cash->reverseMovement($movement, $user);

        $this->assertSame('5000.00', $this->expected($session));
        $totals = $this->cash->totalsFor($session->refresh())['TRY'];
        $this->assertSame('0.00', $totals->cashOut->toDecimal());
    }

    // ========================================================= §10: MULTI-CURRENCY

    #[Test]
    public function bes_para_birimi_ayni_vardiyada_asla_karisiklik_uretmez(): void
    {
        $user = $this->personel();
        $session = $this->openSession($user, [
            'TRY' => '2000.00',
            'EUR' => '100.00',
            'RUB' => '5000.00',
            'USD' => '150.00',
            'GEL' => '300.00',
        ]);

        $this->record($session, CashMovementType::RoomIncome, '500.00', $user, Currency::TRY);
        $this->record($session, CashMovementType::ExtraIncome, '25.00', $user, Currency::EUR);
        $this->record($session, CashMovementType::RoomIncome, '1000.00', $user, Currency::RUB);
        $this->record($session, CashMovementType::Expense, '50.00', $user, Currency::USD);
        $this->record($session, CashMovementType::CashOut, '100.00', $user, Currency::GEL, 'Patron');

        $expected = $this->cash->expectedFor($session->refresh());

        $this->assertSame('2500.00', $expected['TRY']->toDecimal());
        $this->assertSame('125.00', $expected['EUR']->toDecimal());
        $this->assertSame('6000.00', $expected['RUB']->toDecimal());
        $this->assertSame('100.00', $expected['USD']->toDecimal());
        $this->assertSame('200.00', $expected['GEL']->toDecimal());

        // Hiçbir global toplam yok — beş currency, beş bağımsız Money.
        $this->assertCount(5, $expected);
        foreach ($expected as $money) {
            $this->assertNotSame('8925.00', $money->toDecimal(), 'Global toplam üretilmemeli');
        }

        // Sıfır değer olmadığı için tüm satırlar yazılır (sıfır satır testi
        // ayrı testte: sifir_bakiyeli_para_birimi...).
        $this->assertSame(10, CashMovement::query()->where('cash_session_id', $session->id)->count());

        // Devirde tüm 5 currency korunur.
        $toUser = $this->personel('Devralan');
        $transfer = $this->cash->startHandover($session, $toUser, $expected, $user);
        $newSession = $this->cash->acceptHandover($transfer, $toUser);

        $newExpected = $this->cash->expectedFor($newSession);
        $this->assertCount(5, $newExpected);
        foreach ($expected as $code => $money) {
            $this->assertSame($money->toDecimal(), $newExpected[$code]->toDecimal(), "{$code} devirde korunmalı");
        }
    }

    // ============================================ §12: STAFF HANDOVER OLMADAN KAPANIŞ

    /**
     * Sonraki personel henüz yokken vardiya kapanabilmeli: kasa tamamen
     * patrona/ana kasaya teslim edilip devir başlatılmadan session kapatılır.
     */
    #[Test]
    public function vardiya_sonraki_personel_olmadan_da_guvenle_kapanabilir(): void
    {
        $user = $this->personel();
        $session = $this->openSession($user, ['TRY' => '10000.00']);

        // Tüm kasa patrona teslim ediliyor (cash_out).
        $this->record($session, CashMovementType::CashOut, '8000.00', $user, Currency::TRY, 'Patron');
        $this->assertSame('2000.00', $this->expected($session));

        // Kalan 2.000 TRY fiilen sayılıyor ve vardiya DEVİR OLMADAN kapanıyor.
        $closed = $this->cash->closeSession($session, $this->amounts(['TRY' => '2000.00']), $user);

        $this->assertSame(CashSessionStatus::Closed, $closed->status);
        $this->assertNull($closed->is_open);
        $this->assertNull($this->cash->activeSession(), 'Hiçbir açık vardiya kalmamalı');

        $count = CashCount::query()->where('cash_session_id', $closed->id)->firstOrFail();
        $this->assertSame('0.00', $count->differenceMoney()->toDecimal());

        // Sonraki personel istediği zaman yeni bir vardiya açabilir (kilitlenme yok).
        $newSession = $this->openSession($this->personel('Sonraki'), ['TRY' => '0.00']);
        $this->assertSame(CashSessionStatus::Open, $newSession->status);
    }

    /** Aynı senaryo — TÜM para patrona teslim edilip counted 0 olduğunda. */
    #[Test]
    public function tum_kasa_patrona_teslim_edilip_sifir_sayimla_da_kapanabilir(): void
    {
        $user = $this->personel();
        $session = $this->openSession($user, ['TRY' => '5000.00']);

        $this->record($session, CashMovementType::CashOut, '5000.00', $user, Currency::TRY, 'Patron');
        $this->assertSame('0.00', $this->expected($session));

        $closed = $this->cash->closeSession($session, $this->amounts(['TRY' => '0.00']), $user);

        $this->assertSame(CashSessionStatus::Closed, $closed->status);
        // 0 beklenen / 0 sayılan olduğu için writeCounts satır YAZMAZ (bilinçli).
        $this->assertSame(
            0,
            CashCount::query()->where('cash_session_id', $closed->id)->count(),
            'Hem beklenen hem sayılan sıfırsa sayım satırı gereksizdir'
        );
    }
}
