<?php

declare(strict_types=1);

namespace Tests\Feature\Ledger;

use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
use App\Modules\Ledger\Exceptions\LedgerEntryIsImmutable;
use App\Modules\Ledger\Models\LedgerEntry;
use App\Modules\Ledger\Services\LedgerService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * CLAUDE.md §6/L1 — cari hareketleri append-only'dir.
 *
 * Koruma üç katmanda: model olayları, model metotları ve veritabanı tetikleyicileri.
 */
class LedgerImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    private LedgerEntry $entry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entry = app(LedgerService::class)->debit(
            Company::factory()->create()->id,
            Money::fromDecimal('100.00'),
            Currency::TRY,
            now(),
            'Değişmezlik testi',
            actor: User::factory()->create(),
        );
    }

    #[Test]
    public function tabloda_updated_at_ve_deleted_at_kolonlari_yoktur(): void
    {
        $this->assertFalse(Schema::hasColumn('ledger_entries', 'updated_at'));
        $this->assertFalse(Schema::hasColumn('ledger_entries', 'deleted_at'));
    }

    #[Test]
    public function model_update_metodu_istisna_firlatir(): void
    {
        $this->expectException(LedgerEntryIsImmutable::class);

        $this->entry->update(['debit' => '999.00']);
    }

    #[Test]
    public function model_delete_metodu_istisna_firlatir(): void
    {
        $this->expectException(LedgerEntryIsImmutable::class);

        $this->entry->delete();
    }

    #[Test]
    public function save_ile_guncelleme_istisna_firlatir(): void
    {
        $this->entry->description = 'Değiştirilmiş açıklama';

        try {
            $this->entry->save();
            $this->fail('LedgerEntryIsImmutable bekleniyordu');
        } catch (LedgerEntryIsImmutable) {
            $this->assertSame('Değişmezlik testi', $this->entry->fresh()->description);
        }
    }

    #[Test]
    public function ham_sql_guncellemesi_veritabani_tarafindan_engellenir(): void
    {
        try {
            DB::statement('UPDATE ledger_entries SET debit = 999.00 WHERE id = ?', [$this->entry->id]);
            $this->fail('Veritabanı tetikleyicisi UPDATE işlemini engellemeliydi');
        } catch (QueryException $e) {
            $this->assertStringContainsString('degistirilemez', $e->getMessage());
        }

        $this->assertSame('100.00', $this->entry->fresh()->debit);
    }

    #[Test]
    public function ham_sql_silmesi_veritabani_tarafindan_engellenir(): void
    {
        try {
            DB::statement('DELETE FROM ledger_entries WHERE id = ?', [$this->entry->id]);
            $this->fail('Veritabanı tetikleyicisi DELETE işlemini engellemeliydi');
        } catch (QueryException $e) {
            $this->assertStringContainsString('silinemez', $e->getMessage());
        }

        $this->assertDatabaseHas('ledger_entries', ['id' => $this->entry->id]);
    }

    #[Test]
    public function query_builder_toplu_guncellemesi_engellenir(): void
    {
        $this->expectException(QueryException::class);

        LedgerEntry::query()->where('id', $this->entry->id)->update(['credit' => '50.00']);
    }

    #[Test]
    public function company_id_degistirilemez(): void
    {
        $baskaFirma = Company::factory()->create();

        $this->expectException(LedgerEntryIsImmutable::class);

        $this->entry->update(['company_id' => $baskaFirma->id]);
    }

    #[Test]
    public function ledger_icin_hicbir_http_ucu_tanimli_degildir(): void
    {
        $ledgerRoutes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route) => str_contains($route->uri(), 'ledger')
                || str_contains((string) $route->getName(), 'ledger'));

        $this->assertCount(
            0,
            $ledgerRoutes,
            'Bu fazda ledger için arayüz yoktur; yetkisiz erişim yüzeyi de yoktur.',
        );
    }
}
