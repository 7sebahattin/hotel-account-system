<?php

declare(strict_types=1);

namespace Tests\Feature\Stays;

use App\Models\User;
use App\Modules\AuditLog\Services\AuditLogger;
use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Companies\Models\Company;
use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
use App\Modules\Ledger\Models\LedgerEntry;
use App\Modules\Ledger\Services\LedgerService;
use App\Modules\Stays\Models\Stay;
use App\Modules\Stays\Services\StayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * DEĞİŞMEZ: bir konaklamanın her an tam olarak BİR geçerli borç hareketi vardır
 * (iptal edilmişse hiç yoktur). Düzeltilen hareketler ters kayıtla net sıfıra gelir.
 */
class StayLedgerIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $actor;

    private StayService $stays;

    private LedgerService $ledger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        $this->company = Company::factory()->create(['name' => 'Monarch']);
        $this->actor = $this->userWithRole(RoleName::Admin);
        $this->stays = app(StayService::class);
        $this->ledger = app(LedgerService::class);
    }

    /** @param array<string, mixed> $overrides */
    private function makeStay(array $overrides = []): Stay
    {
        return $this->stays->create(array_merge([
            'company_id' => $this->company->id,
            'guest_name' => 'John Smith',
            'check_in_date' => '2026-09-07',
            'check_out_date' => '2026-09-10',
            'amount' => '9000.00',
            'currency' => 'TRY',
        ], $overrides), $this->actor);
    }

    private function balance(?int $companyId = null, Currency $currency = Currency::TRY): string
    {
        return $this->ledger->balanceForCompany($companyId ?? $this->company->id, $currency)->toDecimal();
    }

    /** Konaklamanın geçerli (ters kaydedilmemiş) hareketleri. */
    private function activeEntries(Stay $stay)
    {
        return $this->ledger->activeEntriesForSource(Stay::LEDGER_SOURCE_TYPE, $stay->getKey());
    }

    private function assertExactlyOneActiveDebit(Stay $stay, string $amount, Currency $currency = Currency::TRY): void
    {
        $active = $this->activeEntries($stay);

        $this->assertCount(1, $active, 'Konaklamanın tam olarak bir geçerli hareketi olmalı');
        $this->assertSame($amount, $active->first()->debit);
        $this->assertSame('0.00', $active->first()->credit);
        $this->assertSame($currency, $active->first()->currency);
    }

    #[Test]
    public function konaklama_olusturuldugunda_borc_hareketi_dogar(): void
    {
        $stay = $this->makeStay();

        $entry = LedgerEntry::query()->firstOrFail();

        $this->assertSame($this->company->id, $entry->company_id);
        $this->assertSame('9000.00', $entry->debit);
        $this->assertSame('0.00', $entry->credit);
        $this->assertSame(Currency::TRY, $entry->currency);
        $this->assertSame('stay', $entry->source_type);
        $this->assertSame($stay->id, $entry->source_id);
        $this->assertSame('2026-09-10', $entry->entry_date->toDateString(), 'Hareket tarihi çıkış tarihidir');
        $this->assertSame('Konaklama: John Smith (07.09.2026 - 10.09.2026)', $entry->description);
        $this->assertSame('9000.00', $this->balance());
    }

    /**
     * REGRESYON: gerçek bir HTML formu her alanı METİN olarak gönderir; Laravel'in
     * test istemcisi ise diziye konan PHP tiplerini korur. Bu fark yüzünden
     * company_id'nin string gelmesi testlerden kaçmıştı ve tarayıcıdan gelen
     * gerçek istekte TypeError üretiyordu. Bu test tüm alanları metin gönderir.
     */
    #[Test]
    public function form_alanlari_metin_olarak_gelse_de_cari_hareketi_dogar(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $this->actingAs($admin)->post(route('stays.store'), [
            'company_id' => (string) $this->company->id,
            'guest_name' => 'Form Misafiri',
            'room_no' => '101',
            'check_in_date' => '2026-09-07',
            'check_out_date' => '2026-09-10',
            'amount' => '9000,00',
            'currency' => 'TRY',
        ])->assertSessionHasNoErrors();

        $stay = Stay::query()->firstOrFail();
        $entry = LedgerEntry::query()->firstOrFail();

        $this->assertSame($this->company->id, $entry->company_id);
        $this->assertIsInt($entry->company_id);
        $this->assertSame($stay->id, $entry->source_id);
        $this->assertSame('9000.00', $entry->debit);
        $this->assertSame('9000.00', $this->balance());
    }

    /** REGRESYON: metin alanlarla finansal düzeltme de çalışmalı. */
    #[Test]
    public function form_alanlari_metin_olarak_gelse_de_finansal_duzeltme_calisir(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $stay = $this->makeStay();

        $this->actingAs($admin)->put(route('stays.update', $stay), [
            'company_id' => (string) $this->company->id,
            'guest_name' => 'John Smith',
            'check_in_date' => '2026-09-07',
            'check_out_date' => '2026-09-10',
            'amount' => '9500,00',
            'currency' => 'TRY',
        ])->assertSessionHasNoErrors();

        $this->assertSame(3, LedgerEntry::query()->count());
        $this->assertSame('9500.00', $this->balance());
        $this->assertExactlyOneActiveDebit($stay->fresh(), '9500.00');
    }

    #[Test]
    public function ledger_yazilamazsa_konaklama_da_olusmaz(): void
    {
        $this->app->bind(LedgerService::class, fn () => new class(app(AuditLogger::class)) extends LedgerService
        {
            public function debit(
                int $companyId, Money $amount, Currency $currency,
                \DateTimeInterface|string $entryDate, string $description,
                ?string $sourceType = null, ?int $sourceId = null, ?User $actor = null,
            ): LedgerEntry {
                throw new RuntimeException('Cari hareketi yazılamadı');
            }
        });

        try {
            app(StayService::class)->create([
                'company_id' => $this->company->id,
                'guest_name' => 'Rollback',
                'check_in_date' => '2026-09-07',
                'check_out_date' => '2026-09-10',
                'amount' => '9000.00',
                'currency' => 'TRY',
            ], $this->actor);
            $this->fail('İstisna bekleniyordu');
        } catch (RuntimeException $e) {
            $this->assertSame('Cari hareketi yazılamadı', $e->getMessage());
        }

        $this->assertSame(0, Stay::query()->count(), 'Konaklama geri alınmalı');
        $this->assertSame(0, LedgerEntry::query()->count());
    }

    #[Test]
    public function finansal_olmayan_guncelleme_yeni_hareket_uretmez(): void
    {
        $stay = $this->makeStay();
        $entryId = LedgerEntry::query()->firstOrFail()->id;

        $this->stays->update($stay, [
            'company_id' => $this->company->id,
            'guest_name' => 'John A. Smith',
            'room_no' => '305',
            'check_in_date' => '2026-09-07',
            'check_out_date' => '2026-09-10',
            'amount' => '9000.00',
            'currency' => 'TRY',
            'notes' => 'Oda değişti',
        ], $this->actor);

        $this->assertSame(1, LedgerEntry::query()->count(), 'Finansal olmayan değişiklik hareket üretmez');
        $this->assertSame($entryId, LedgerEntry::query()->firstOrFail()->id);
        $this->assertSame('John A. Smith', $stay->fresh()->guest_name);
        $this->assertSame('9000.00', $this->balance());
        $this->assertExactlyOneActiveDebit($stay, '9000.00');
    }

    #[Test]
    public function tutar_degisimi_ters_kayit_arti_yeni_borc_uretir(): void
    {
        $stay = $this->makeStay();

        $this->stays->update($stay, [
            'company_id' => $this->company->id,
            'guest_name' => 'John Smith',
            'check_in_date' => '2026-09-07',
            'check_out_date' => '2026-09-10',
            'amount' => '9500.00',
            'currency' => 'TRY',
        ], $this->actor);

        $entries = LedgerEntry::query()->orderBy('id')->get();

        $this->assertCount(3, $entries);
        $this->assertSame('9000.00', $entries[0]->debit);
        $this->assertSame('9000.00', $entries[1]->credit, 'İkinci satır ters kayıt');
        $this->assertTrue($entries[1]->is_reversal);
        $this->assertSame($entries[0]->id, $entries[1]->reverses_entry_id);
        $this->assertSame('9500.00', $entries[2]->debit);

        $this->assertSame('9500.00', $this->balance());
        $this->assertExactlyOneActiveDebit($stay->fresh(), '9500.00');
    }

    #[Test]
    public function para_birimi_degisimi_eski_para_biriminde_ters_kayit_yeni_para_biriminde_borc_uretir(): void
    {
        $stay = $this->makeStay();

        $this->stays->update($stay, [
            'company_id' => $this->company->id,
            'guest_name' => 'John Smith',
            'check_in_date' => '2026-09-07',
            'check_out_date' => '2026-09-10',
            'amount' => '9000.00',
            'currency' => 'EUR',
        ], $this->actor);

        $this->assertSame('0.00', $this->balance(currency: Currency::TRY), 'TRY bakiyesi sıfırlanmalı');
        $this->assertSame('9000.00', $this->balance(currency: Currency::EUR));
        $this->assertExactlyOneActiveDebit($stay->fresh(), '9000.00', Currency::EUR);
    }

    #[Test]
    public function firma_degisimi_eski_firmada_ters_kayit_yeni_firmada_borc_uretir(): void
    {
        $stay = $this->makeStay();
        $yeniFirma = Company::factory()->create(['name' => 'Birdie Holiday']);

        $this->stays->update($stay, [
            'company_id' => $yeniFirma->id,
            'guest_name' => 'John Smith',
            'check_in_date' => '2026-09-07',
            'check_out_date' => '2026-09-10',
            'amount' => '9000.00',
            'currency' => 'TRY',
        ], $this->actor);

        $this->assertSame('0.00', $this->balance(), 'Eski firmanın bakiyesi sıfırlanmalı');
        $this->assertSame('9000.00', $this->balance($yeniFirma->id));

        $active = $this->activeEntries($stay->fresh());
        $this->assertCount(1, $active);
        $this->assertSame($yeniFirma->id, $active->first()->company_id);
    }

    #[Test]
    public function tarih_degisimi_gece_sayisini_ve_hareketi_yeniden_uretir(): void
    {
        $stay = $this->makeStay();

        $this->stays->update($stay, [
            'company_id' => $this->company->id,
            'guest_name' => 'John Smith',
            'check_in_date' => '2026-09-07',
            'check_out_date' => '2026-09-12',
            'amount' => '9000.00',
            'currency' => 'TRY',
        ], $this->actor);

        $stay->refresh();

        $this->assertSame(5, $stay->nights);
        $this->assertSame(3, LedgerEntry::query()->count());

        $active = $this->activeEntries($stay);
        $this->assertSame('2026-09-12', $active->first()->entry_date->toDateString());
        $this->assertSame('Konaklama: John Smith (07.09.2026 - 12.09.2026)', $active->first()->description);
        $this->assertSame('9000.00', $this->balance());
    }

    #[Test]
    public function art_arda_duzeltmeler_her_zaman_tek_gecerli_harekete_indirger(): void
    {
        $stay = $this->makeStay();

        foreach (['9500.00', '10000.00', '8750.50'] as $amount) {
            $this->stays->update($stay->fresh(), [
                'company_id' => $this->company->id,
                'guest_name' => 'John Smith',
                'check_in_date' => '2026-09-07',
                'check_out_date' => '2026-09-10',
                'amount' => $amount,
                'currency' => 'TRY',
            ], $this->actor);
        }

        // 1 orijinal + 3 × (ters + yeni) = 7 satır
        $this->assertSame(7, LedgerEntry::query()->count());
        $this->assertExactlyOneActiveDebit($stay->fresh(), '8750.50');
        $this->assertSame('8750.50', $this->balance());
    }

    #[Test]
    public function ters_kaydedilmis_hareketler_net_sifira_gelir(): void
    {
        $stay = $this->makeStay();

        $this->stays->update($stay, [
            'company_id' => $this->company->id,
            'guest_name' => 'John Smith',
            'check_in_date' => '2026-09-07',
            'check_out_date' => '2026-09-10',
            'amount' => '9500.00',
            'currency' => 'TRY',
        ], $this->actor);

        $reversed = LedgerEntry::query()->whereNotNull('reverses_entry_id')->get();

        foreach ($reversed as $reversal) {
            $original = LedgerEntry::query()->findOrFail($reversal->reverses_entry_id);

            $net = $original->signedAmount()->add($reversal->signedAmount());

            $this->assertTrue($net->isZero(), 'Ters kayıt orijinali tam olarak götürmeli');
        }
    }

    #[Test]
    public function ledger_yazilamazsa_guncelleme_de_geri_alinir(): void
    {
        $stay = $this->makeStay();

        $this->app->bind(LedgerService::class, fn () => new class(app(AuditLogger::class)) extends LedgerService
        {
            public function debit(
                int $companyId, Money $amount, Currency $currency,
                \DateTimeInterface|string $entryDate, string $description,
                ?string $sourceType = null, ?int $sourceId = null, ?User $actor = null,
            ): LedgerEntry {
                throw new RuntimeException('Yeni hareket yazılamadı');
            }
        });

        try {
            app(StayService::class)->update($stay, [
                'company_id' => $this->company->id,
                'guest_name' => 'John Smith',
                'check_in_date' => '2026-09-07',
                'check_out_date' => '2026-09-10',
                'amount' => '9500.00',
                'currency' => 'TRY',
            ], $this->actor);
            $this->fail('İstisna bekleniyordu');
        } catch (RuntimeException) {
            // beklenen
        }

        $this->assertSame('9000.00', $stay->fresh()->amount, 'Konaklama tutarı değişmemeli');
        $this->assertSame(1, LedgerEntry::query()->count(), 'Yarım ters kayıt kalmamalı');
        $this->assertSame('9000.00', $this->balance());
    }
}
