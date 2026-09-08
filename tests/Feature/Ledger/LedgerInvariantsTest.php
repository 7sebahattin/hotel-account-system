<?php

declare(strict_types=1);

namespace Tests\Feature\Ledger;

use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
use App\Modules\Ledger\Exceptions\InvalidLedgerEntry;
use App\Modules\Ledger\Models\LedgerEntry;
use App\Modules\Ledger\Services\LedgerService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * CLAUDE.md §6/L2, L3 — hareket değişmezleri.
 *
 * Kurallar hem serviste hem veritabanı CHECK kısıtlarında uygulanır; ikisi de test edilir.
 */
class LedgerInvariantsTest extends TestCase
{
    use RefreshDatabase;

    private LedgerService $ledger;

    private Company $company;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledger = app(LedgerService::class);
        $this->actor = User::factory()->create();
        $this->company = Company::factory()->create();
    }

    /** @param array<string, mixed> $overrides */
    private function rawInsert(array $overrides): void
    {
        DB::table('ledger_entries')->insert(array_merge([
            'company_id' => $this->company->id,
            'entry_date' => now()->toDateString(),
            'description' => 'Ham kayıt',
            'debit' => '0.00',
            'credit' => '0.00',
            'currency' => 'TRY',
            'is_reversal' => false,
            'created_by' => $this->actor->id,
            'created_at' => now(),
        ], $overrides));
    }

    #[Test]
    public function borc_ve_alacak_ayni_anda_dolu_olamaz(): void
    {
        $this->expectException(QueryException::class);

        $this->rawInsert(['debit' => '100.00', 'credit' => '50.00']);
    }

    #[Test]
    public function borc_ve_alacak_ayni_anda_sifir_olamaz(): void
    {
        $this->expectException(QueryException::class);

        $this->rawInsert(['debit' => '0.00', 'credit' => '0.00']);
    }

    #[Test]
    public function negatif_borc_veritabani_duzeyinde_reddedilir(): void
    {
        $this->expectException(QueryException::class);

        $this->rawInsert(['debit' => '-100.00', 'credit' => '0.00']);
    }

    #[Test]
    public function negatif_alacak_veritabani_duzeyinde_reddedilir(): void
    {
        $this->expectException(QueryException::class);

        $this->rawInsert(['debit' => '0.00', 'credit' => '-100.00']);
    }

    #[Test]
    public function servis_negatif_borc_kabul_etmez(): void
    {
        $this->expectException(InvalidLedgerEntry::class);

        $this->ledger->debit(
            $this->company->id, Money::fromDecimal('-100.00'), Currency::TRY,
            now(), 'Negatif borç', actor: $this->actor,
        );
    }

    #[Test]
    public function servis_negatif_alacak_kabul_etmez(): void
    {
        $this->expectException(InvalidLedgerEntry::class);

        $this->ledger->credit(
            $this->company->id, Money::fromDecimal('-100.00'), Currency::TRY,
            now(), 'Negatif alacak', actor: $this->actor,
        );
    }

    #[Test]
    public function servis_sifir_tutar_kabul_etmez(): void
    {
        foreach (['debit', 'credit'] as $method) {
            $this->assertThrows(
                fn () => $this->ledger->{$method}(
                    $this->company->id, Money::zero(), Currency::TRY,
                    now(), 'Sıfır tutar', actor: $this->actor,
                ),
                InvalidLedgerEntry::class,
            );
        }

        $this->assertSame(0, LedgerEntry::query()->count());
    }

    #[Test]
    public function servis_islemi_yapan_kullanici_olmadan_kayit_yazmaz(): void
    {
        $this->expectException(InvalidLedgerEntry::class);

        $this->ledger->debit(
            $this->company->id, Money::fromDecimal('100.00'), Currency::TRY,
            now(), 'Kullanıcısız', actor: null,
        );
    }

    #[Test]
    public function desteklenen_para_birimleri_enum_ile_sinirlidir(): void
    {
        // Gün 9.6: TRY/EUR/GBP'e sabitlenmiş üçlü kaldırıldı; ISO 4217 tabanlı
        // genişletilebilir kümeye geçildi (config/hotel.php, CurrencyTest).
        $this->assertSame(['TRY', 'EUR', 'GBP', 'USD', 'RUB', 'GEL'], Currency::values());
        $this->assertNotContains('CHF', Currency::values());
        $this->assertCount(6, Currency::cases());
    }

    #[Test]
    public function borc_kaydi_tek_satir_ve_dogru_yon_uretir(): void
    {
        $entry = $this->ledger->debit(
            $this->company->id, Money::fromDecimal('1000.00'), Currency::TRY,
            '2026-03-15', 'Konaklama bedeli', 'stay', 42, $this->actor,
        );

        $this->assertSame(1, LedgerEntry::query()->count());
        $this->assertSame('1000.00', $entry->debit);
        $this->assertSame('0.00', $entry->credit);
        $this->assertSame(Currency::TRY, $entry->currency);
        $this->assertSame('2026-03-15', $entry->entry_date->toDateString());
        $this->assertSame('stay', $entry->source_type);
        $this->assertSame(42, $entry->source_id);
        $this->assertSame($this->actor->id, $entry->created_by);
        $this->assertFalse($entry->is_reversal);
    }

    #[Test]
    public function alacak_kaydi_tek_satir_ve_dogru_yon_uretir(): void
    {
        $entry = $this->ledger->credit(
            $this->company->id, Money::fromDecimal('300.50'), Currency::TRY,
            '2026-03-20', 'Tahsilat', 'payment', 7, $this->actor,
        );

        $this->assertSame('0.00', $entry->debit);
        $this->assertSame('300.50', $entry->credit);
        $this->assertSame('payment', $entry->source_type);
    }

    #[Test]
    public function kaynak_bilgisi_zorunlu_degildir(): void
    {
        $entry = $this->ledger->debit(
            $this->company->id, Money::fromDecimal('50.00'), Currency::TRY,
            now(), 'Manuel borç', actor: $this->actor,
        );

        $this->assertNull($entry->source_type);
        $this->assertNull($entry->source_id);
    }

    /**
     * Açılış bakiyesi için ayrı tablo yoktur; source_type serbest metin olduğu
     * için Excel göçü 'opening' değerini teknik olarak kullanabilir.
     */
    #[Test]
    public function opening_kaynak_tipi_teknik_olarak_kullanilabilir(): void
    {
        $entry = $this->ledger->debit(
            $this->company->id, Money::fromDecimal('2500.00'), Currency::TRY,
            '2026-01-01', 'Devir bakiyesi', 'opening', null, $this->actor,
        );

        $this->assertSame('opening', $entry->source_type);
        $this->assertNull($entry->source_id);
    }
}
