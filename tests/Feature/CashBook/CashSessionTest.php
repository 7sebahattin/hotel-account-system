<?php

declare(strict_types=1);

namespace Tests\Feature\CashBook;

use App\Modules\CashBook\Enums\CashMovementType;
use App\Modules\CashBook\Enums\CashSessionStatus;
use App\Modules\CashBook\Exceptions\CashMovementIsImmutable;
use App\Modules\CashBook\Exceptions\CashSessionAlreadyOpen;
use App\Modules\CashBook\Exceptions\CashSessionIsClosed;
use App\Modules\CashBook\Models\CashCount;
use App\Modules\CashBook\Models\CashMovement;
use App\Modules\CashBook\Services\CashBookService;
use App\Modules\Core\Enums\Currency;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;

/**
 * Vardiya açılışı, açılış bakiyeleri, kapanış ve sayım farkı.
 */
class CashSessionTest extends CashBookTestCase
{
    #[Test]
    public function ilk_kasa_acilisi_vardiya_baslatir(): void
    {
        $user = $this->personel();

        $session = $this->openSession($user);

        $this->assertSame(CashSessionStatus::Open, $session->status);
        $this->assertSame($user->id, $session->opened_by);
        $this->assertNull($session->closed_at);
        $this->assertSame($session->id, $this->cash->activeSession()?->id);
    }

    #[Test]
    public function acilis_bakiyeleri_para_birimi_bazinda_acilis_hareketi_uretir(): void
    {
        $user = $this->personel();

        $session = $this->openSession($user, ['TRY' => '7000.00', 'EUR' => '300.00']);

        $openings = CashMovement::query()
            ->where('cash_session_id', $session->id)
            ->where('type', CashMovementType::Opening->value)
            ->get();

        $this->assertCount(2, $openings, 'Her para birimi için bir açılış hareketi');
        $this->assertSame('7000.00', $this->expected($session, Currency::TRY));
        $this->assertSame('300.00', $this->expected($session, Currency::EUR));
    }

    #[Test]
    public function sifir_ve_bos_acilis_bakiyesi_hareket_uretmez(): void
    {
        $session = $this->openSession($this->personel(), ['TRY' => '0.00']);

        $this->assertSame(0, CashMovement::query()->where('cash_session_id', $session->id)->count());
    }

    /**
     * İşletmede fiziksel kasa tektir: aynı anda iki açık vardiya olamaz.
     * Kısıt uygulamada ve veritabanında ayrı ayrı test edilir.
     */
    #[Test]
    public function ayni_anda_ikinci_vardiya_acilamaz(): void
    {
        $this->openSession($this->personel('Birinci'));

        $this->expectException(CashSessionAlreadyOpen::class);

        $this->openSession($this->personel('İkinci'));
    }

    #[Test]
    public function ikinci_acik_vardiya_veritabani_duzeyinde_de_reddedilir(): void
    {
        $user = $this->personel();
        $this->openSession($user);

        $this->expectException(QueryException::class);

        // Servis atlanarak doğrudan yazma denemesi.
        DB::table('cash_sessions')->insert([
            'opened_by' => $user->id,
            'opened_at' => now(),
            'status' => 'open',
            'is_open' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    #[Test]
    public function vardiya_kapaninca_yeni_vardiya_acilabilir(): void
    {
        $first = $this->personel('Birinci');
        $session = $this->openSession($first, ['TRY' => '1000.00']);

        $this->cash->closeSession($session, $this->amounts(['TRY' => '1000.00']), $first);

        $second = $this->openSession($this->personel('İkinci'), ['TRY' => '1000.00']);

        $this->assertSame(CashSessionStatus::Closed, $session->refresh()->status);
        $this->assertNull($session->is_open, 'Kapalı vardiyada is_open NULL olmalı');
        $this->assertSame($second->id, $this->cash->activeSession()?->id);
    }

    #[Test]
    public function kapanista_sayim_farki_para_birimi_bazinda_hesaplanir(): void
    {
        $user = $this->personel();
        $session = $this->openSession($user, ['TRY' => '3500.00', 'EUR' => '300.00']);

        // TRY'de 100 eksik sayıldı, EUR tam.
        $this->cash->closeSession($session, $this->amounts(['TRY' => '3400.00', 'EUR' => '300.00']), $user);

        $counts = CashCount::query()->where('cash_session_id', $session->id)->get()->keyBy('currency');

        $this->assertSame('3500.00', $counts['TRY']->expectedMoney()->toDecimal());
        $this->assertSame('3400.00', $counts['TRY']->countedMoney()->toDecimal());
        $this->assertSame('-100.00', $counts['TRY']->differenceMoney()->toDecimal());

        $this->assertSame('0.00', $counts['EUR']->differenceMoney()->toDecimal());
        $this->assertFalse($counts['EUR']->hasDifference());
    }

    /**
     * Beklenen var ama hiç sayılmadıysa fark GİZLENMEZ: eksik para
     * sessizce kaybolmaz, satır yine yazılır.
     */
    #[Test]
    public function sayilmayan_para_birimi_icin_de_fark_satiri_yazilir(): void
    {
        $user = $this->personel();
        $session = $this->openSession($user, ['TRY' => '1000.00', 'EUR' => '300.00']);

        $this->cash->closeSession($session, $this->amounts(['TRY' => '1000.00']), $user);

        $eur = CashCount::query()
            ->where('cash_session_id', $session->id)
            ->where('currency', Currency::EUR->value)
            ->firstOrFail();

        $this->assertSame('-300.00', $eur->differenceMoney()->toDecimal());
    }

    #[Test]
    public function kapali_vardiya_tekrar_kapatilamaz(): void
    {
        $user = $this->personel();
        $session = $this->openSession($user);
        $this->cash->closeSession($session, [], $user);

        $this->expectException(CashSessionIsClosed::class);

        $this->cash->closeSession($session->refresh(), [], $user);
    }

    #[Test]
    public function kapali_vardiyaya_hareket_eklenemez(): void
    {
        $user = $this->personel();
        $session = $this->openSession($user);
        $this->cash->closeSession($session, [], $user);

        $this->expectException(CashSessionIsClosed::class);

        $this->record($session->refresh(), CashMovementType::RoomIncome, '100.00', $user);
    }

    /** Servis atlansa bile veritabanı tetikleyicisi kapalı vardiyayı korur. */
    #[Test]
    public function kapali_vardiyaya_ham_sql_ile_de_hareket_yazilamaz(): void
    {
        $user = $this->personel();
        $session = $this->openSession($user);
        $this->cash->closeSession($session, [], $user);

        $this->expectException(QueryException::class);

        DB::table('cash_movements')->insert([
            'cash_session_id' => $session->id,
            'type' => 'room_income',
            'direction' => 'in',
            'amount' => '100.00',
            'currency' => 'TRY',
            'entry_at' => now(),
            'is_reversal' => false,
            'created_by' => $user->id,
            'created_at' => now(),
        ]);
    }

    #[Test]
    public function kapali_vardiyanin_sayimi_degistirilemez_kayit_silinemez(): void
    {
        $user = $this->personel();
        $session = $this->openSession($user, ['TRY' => '500.00']);
        $this->cash->closeSession($session, $this->amounts(['TRY' => '500.00']), $user);

        $movement = CashMovement::query()->where('cash_session_id', $session->id)->firstOrFail();

        // Model katmanı
        $this->assertThrows(
            fn () => $movement->update(['amount' => '999.00']),
            CashMovementIsImmutable::class,
        );

        $this->assertThrows(
            fn () => $movement->delete(),
            CashMovementIsImmutable::class,
        );

        // Veritabanı katmanı (Eloquent atlanarak)
        $this->assertThrows(
            fn () => DB::table('cash_movements')->where('id', $movement->id)->update(['amount' => '999.00']),
            QueryException::class,
        );

        $this->assertThrows(
            fn () => DB::table('cash_movements')->where('id', $movement->id)->delete(),
            QueryException::class,
        );

        $this->assertSame('500.00', $movement->refresh()->amountMoney()->toDecimal());
    }

    #[Test]
    public function kasa_hareketi_silme_ucu_yoktur(): void
    {
        $deleteRoutes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route) => str_contains($route->uri(), 'cash')
                && in_array('DELETE', $route->methods(), true));

        $this->assertCount(0, $deleteRoutes, 'Kasa kaydı silinmez, ters kayıtla düzeltilir');
        $this->assertFalse(method_exists(CashBookService::class, 'deleteMovement'));

        // Soft delete kolonu da yoktur: kayıt listeden düşürülemez.
        $this->assertFalse(Schema::hasColumn('cash_movements', 'deleted_at'));
        $this->assertFalse(Schema::hasColumn('cash_sessions', 'deleted_at'));
    }

    #[Test]
    public function acilis_hareketi_kullanicidan_alinmaz(): void
    {
        // `opening` seçilebilir türler arasında DEĞİLDİR: personel vardiya
        // ortasında açılış bakiyesi yazarak beklenen kasayı bozamaz.
        $selectable = array_column(CashMovementType::selectable(), 'value');

        $this->assertNotContains(CashMovementType::Opening->value, $selectable);
    }
}
