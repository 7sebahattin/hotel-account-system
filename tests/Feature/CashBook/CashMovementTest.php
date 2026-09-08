<?php

declare(strict_types=1);

namespace Tests\Feature\CashBook;

use App\Modules\CashBook\Enums\CashMovementDirection;
use App\Modules\CashBook\Enums\CashMovementType;
use App\Modules\CashBook\Exceptions\CannotReverseMovement;
use App\Modules\CashBook\Exceptions\InvalidCashMovement;
use App\Modules\CashBook\Models\CashMovement;
use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * Beklenen kasa hesabı, gelir-gider ayrımı ve ters kayıt.
 *
 * EN KRİTİK KURAL (kullanıcı talimatı §6): ara kasa/patron teslimi fiziksel
 * kasayı azaltır ama GİDER DEĞİLDİR. Bu testler o ayrımı korur.
 */
class CashMovementTest extends CashBookTestCase
{
    #[Test]
    public function oda_geliri_beklenen_kasayi_artirir(): void
    {
        $user = $this->personel();
        $session = $this->openSession($user, ['TRY' => '1000.00']);

        $this->record($session, CashMovementType::RoomIncome, '2500.00', $user);

        $this->assertSame('3500.00', $this->expected($session));
    }

    #[Test]
    public function ek_gelir_beklenen_kasayi_artirir(): void
    {
        $user = $this->personel();
        $session = $this->openSession($user, ['TRY' => '1000.00']);

        $this->record($session, CashMovementType::ExtraIncome, '500.00', $user);

        $this->assertSame('1500.00', $this->expected($session));
    }

    #[Test]
    public function gider_beklenen_kasayi_azaltir(): void
    {
        $user = $this->personel();
        $session = $this->openSession($user, ['TRY' => '1000.00']);

        $this->record($session, CashMovementType::Expense, '300.00', $user);

        $this->assertSame('700.00', $this->expected($session));
    }

    #[Test]
    public function kasa_girisi_beklenen_kasayi_artirir(): void
    {
        $user = $this->personel();
        $session = $this->openSession($user, ['TRY' => '1000.00']);

        $this->record($session, CashMovementType::CashIn, '250.00', $user);

        $this->assertSame('1250.00', $this->expected($session));
    }

    /**
     * ARA KASA TESLİMİ: kasayı azaltır ama gider toplamına GİRMEZ.
     *
     * Kullanıcı talimatındaki senaryo: kasada 14.000 varken patrona 12.000
     * teslim edilir; gelir-gider raporunda 12.000'lik bir gider OLUŞMAZ.
     */
    #[Test]
    public function ara_kasa_teslimi_kasayi_azaltir_ama_gider_uretmez(): void
    {
        $user = $this->personel();
        $session = $this->openSession($user, ['TRY' => '2000.00']);

        $this->record($session, CashMovementType::RoomIncome, '12000.00', $user);
        $this->record($session, CashMovementType::CashOut, '12000.00', $user, counterparty: 'Patron');

        $totals = $this->cash->totalsFor($session->refresh())['TRY'];

        $this->assertSame('2000.00', $totals->expected()->toDecimal(), 'Fiziksel kasa azalmalı');
        $this->assertSame('-12000.00', $totals->cashOut->toDecimal());

        // Gider toplamı DEĞİŞMEDİ: ara kasa teslimi gider değildir.
        $this->assertSame('0.00', $totals->expense->toDecimal());
        $this->assertSame('12000.00', $totals->income()->toDecimal());
    }

    #[Test]
    #[DataProvider('gelirGiderRaporuEtkisi')]
    public function gelir_gider_raporu_yalnizca_gercek_gelir_ve_gideri_sayar(
        CashMovementType $type,
        bool $affectsIncomeStatement,
    ): void {
        $this->assertSame($affectsIncomeStatement, $type->affectsIncomeStatement());
    }

    /** @return array<string, array{CashMovementType, bool}> */
    public static function gelirGiderRaporuEtkisi(): array
    {
        return [
            'oda geliri' => [CashMovementType::RoomIncome, true],
            'ek gelir' => [CashMovementType::ExtraIncome, true],
            'gider' => [CashMovementType::Expense, true],
            'açılış bakiyesi' => [CashMovementType::Opening, false],
            'kasa çıkışı (ara kasa)' => [CashMovementType::CashOut, false],
            'kasa girişi' => [CashMovementType::CashIn, false],
        ];
    }

    /**
     * Kullanıcı talimatı §9'daki örnek senaryonun birebir doğrulaması.
     */
    #[Test]
    public function ornek_vardiya_senaryosu_beklenen_kasayi_dogru_hesaplar(): void
    {
        $user = $this->personel();
        $session = $this->openSession($user, ['TRY' => '7000.00', 'EUR' => '300.00']);

        $this->record($session, CashMovementType::RoomIncome, '8000.00', $user);
        $this->record($session, CashMovementType::ExtraIncome, '1000.00', $user);
        $this->record($session, CashMovementType::Expense, '500.00', $user);
        $this->record($session, CashMovementType::CashOut, '12000.00', $user, counterparty: 'Ara kasa');

        // 7000 + 8000 + 1000 - 500 - 12000 = 3500
        $this->assertSame('3500.00', $this->expected($session, Currency::TRY));

        // EUR hiç dokunulmadı ve TRY hesabına KARIŞMADI.
        $this->assertSame('300.00', $this->expected($session, Currency::EUR));
    }

    // ------------------------------------------------------- para birimleri

    #[Test]
    public function farkli_para_birimleri_birbirine_eklenmez(): void
    {
        $user = $this->personel();
        $session = $this->openSession($user);

        $this->record($session, CashMovementType::RoomIncome, '1000.00', $user, Currency::TRY);
        $this->record($session, CashMovementType::RoomIncome, '1000.00', $user, Currency::EUR);
        $this->record($session, CashMovementType::RoomIncome, '1000.00', $user, Currency::RUB);

        $expected = $this->cash->expectedFor($session->refresh());

        $this->assertSame('1000.00', $expected['TRY']->toDecimal());
        $this->assertSame('1000.00', $expected['EUR']->toDecimal());
        $this->assertSame('1000.00', $expected['RUB']->toDecimal());

        // Hiçbir yerde 3000 gibi birleşik bir değer üretilmez.
        foreach ($expected as $money) {
            $this->assertNotSame('3000.00', $money->toDecimal());
        }
    }

    #[Test]
    #[DataProvider('desteklenenParaBirimleri')]
    public function her_para_biriminde_kasa_hareketi_girilebilir(Currency $currency): void
    {
        $user = $this->personel();
        $session = $this->openSession($user);

        $movement = $this->record($session, CashMovementType::RoomIncome, '1500.00', $user, $currency);

        $this->assertSame($currency, $movement->currency);
        $this->assertSame('1500.00', $this->expected($session, $currency));
    }

    /** @return array<string, array{Currency}> */
    public static function desteklenenParaBirimleri(): array
    {
        return [
            'TRY' => [Currency::TRY],
            'EUR' => [Currency::EUR],
            'GBP' => [Currency::GBP],
            'USD' => [Currency::USD],
            'RUB' => [Currency::RUB],
            'GEL' => [Currency::GEL],
        ];
    }

    // ------------------------------------------------------------ ters kayıt

    #[Test]
    public function ters_kayit_beklenen_kasayi_eski_haline_dondurur(): void
    {
        $user = $this->personel();
        $session = $this->openSession($user, ['TRY' => '1000.00']);

        $movement = $this->record($session, CashMovementType::RoomIncome, '2500.00', $user);
        $this->assertSame('3500.00', $this->expected($session));

        $reversal = $this->cash->reverseMovement($movement, $user);

        $this->assertSame('1000.00', $this->expected($session));
        $this->assertTrue($reversal->is_reversal);
        $this->assertSame($movement->id, $reversal->reverses_movement_id);

        // Ters kayıt aynı TİPİ taşır, yalnızca YÖNÜ döner.
        $this->assertSame($movement->type, $reversal->type);
        $this->assertSame(CashMovementDirection::Out, $reversal->direction);

        // Orijinal kayıt defterde KALIR.
        $this->assertDatabaseHas('cash_movements', ['id' => $movement->id, 'amount' => '2500.00']);
    }

    #[Test]
    public function ters_kayit_gelir_toplamini_da_duzeltir(): void
    {
        $user = $this->personel();
        $session = $this->openSession($user);

        $movement = $this->record($session, CashMovementType::RoomIncome, '900.00', $user);
        $this->cash->reverseMovement($movement, $user);

        $totals = $this->cash->totalsFor($session->refresh())['TRY'];

        $this->assertSame('0.00', $totals->income()->toDecimal());
        $this->assertSame('0.00', $totals->roomIncome->toDecimal());
    }

    #[Test]
    public function ayni_hareket_ikinci_kez_ters_kaydedilemez(): void
    {
        $user = $this->personel();
        $session = $this->openSession($user);
        $movement = $this->record($session, CashMovementType::Expense, '100.00', $user);

        $this->cash->reverseMovement($movement, $user);

        $this->expectException(CannotReverseMovement::class);

        $this->cash->reverseMovement($movement->refresh(), $user);
    }

    #[Test]
    public function ikinci_ters_kayit_veritabani_duzeyinde_de_reddedilir(): void
    {
        $user = $this->personel();
        $session = $this->openSession($user);
        $movement = $this->record($session, CashMovementType::Expense, '100.00', $user);
        $this->cash->reverseMovement($movement, $user);

        $this->expectException(QueryException::class);

        DB::table('cash_movements')->insert([
            'cash_session_id' => $session->id,
            'type' => 'expense',
            'direction' => 'in',
            'amount' => '100.00',
            'currency' => 'TRY',
            'entry_at' => now(),
            'is_reversal' => true,
            'reverses_movement_id' => $movement->id,
            'created_by' => $user->id,
            'created_at' => now(),
        ]);
    }

    #[Test]
    public function ters_kaydin_tersi_alinamaz(): void
    {
        $user = $this->personel();
        $session = $this->openSession($user);
        $movement = $this->record($session, CashMovementType::RoomIncome, '100.00', $user);
        $reversal = $this->cash->reverseMovement($movement, $user);

        $this->expectException(CannotReverseMovement::class);

        $this->cash->reverseMovement($reversal, $user);
    }

    /**
     * Açılış bakiyesi ters kaydedilemez.
     *
     * Devirle gelen açılış tutarı, teslim eden tarafın onayladığı devir
     * kaydıyla birebir eşleşir; ters kaydedilebilseydi devredilen para ile
     * kasadaki para sessizce çelişirdi. Kural hem Policy'de hem serviste.
     */
    #[Test]
    public function acilis_bakiyesi_ters_kaydedilemez(): void
    {
        $user = $this->personel();
        $session = $this->openSession($user, ['TRY' => '7000.00']);

        $opening = CashMovement::query()
            ->where('cash_session_id', $session->id)
            ->where('type', CashMovementType::Opening->value)
            ->firstOrFail();

        $this->assertFalse($user->can('reverse', $opening), 'Policy açılış bakiyesini korumalı');

        $this->assertThrows(
            fn () => $this->cash->reverseMovement($opening, $user),
            CannotReverseMovement::class,
        );

        // HTTP üzerinden de engellenir.
        $this->actingAs($user)
            ->patch(route('cashbook.movements.reverse', $opening))
            ->assertForbidden();

        $this->assertSame('7000.00', $this->expected($session));
    }

    #[Test]
    public function kapali_vardiyadaki_hareket_ters_kaydedilemez(): void
    {
        $user = $this->personel();
        $session = $this->openSession($user);
        $movement = $this->record($session, CashMovementType::RoomIncome, '100.00', $user);
        $this->cash->closeSession($session, $this->amounts(['TRY' => '100.00']), $user);

        $this->expectException(CannotReverseMovement::class);

        $this->cash->reverseMovement($movement->refresh(), $user);
    }

    // ------------------------------------------------------------ doğrulama

    #[Test]
    public function sifir_ve_negatif_tutar_kabul_edilmez(): void
    {
        $user = $this->personel();
        $session = $this->openSession($user);

        foreach (['0.00', '-100.00'] as $amount) {
            $this->assertThrows(
                fn () => $this->cash->recordMovement(
                    session: $session,
                    type: CashMovementType::RoomIncome,
                    currency: Currency::TRY,
                    amount: Money::fromDecimal($amount),
                    actor: $user,
                ),
                InvalidCashMovement::class,
            );
        }

        $this->assertSame(0, CashMovement::query()->count());
    }

    #[Test]
    public function tip_ile_uyusmayan_yon_veritabani_duzeyinde_reddedilir(): void
    {
        $user = $this->personel();
        $session = $this->openSession($user);

        $this->expectException(QueryException::class);

        // "Gelir ama kasadan çıkan" anlamsız satır.
        DB::table('cash_movements')->insert([
            'cash_session_id' => $session->id,
            'type' => 'room_income',
            'direction' => 'out',
            'amount' => '100.00',
            'currency' => 'TRY',
            'entry_at' => now(),
            'is_reversal' => false,
            'created_by' => $user->id,
            'created_at' => now(),
        ]);
    }
}
