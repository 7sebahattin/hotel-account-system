<?php

declare(strict_types=1);

namespace Tests\Feature\Ledger;

use App\Models\User;
use App\Modules\AuditLog\Enums\AuditEvent;
use App\Modules\AuditLog\Models\AuditLog;
use App\Modules\AuditLog\Services\AuditLogger;
use App\Modules\Companies\Models\Company;
use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
use App\Modules\Ledger\Exceptions\CannotReverseEntry;
use App\Modules\Ledger\Models\LedgerEntry;
use App\Modules\Ledger\Services\LedgerService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * CLAUDE.md §6/L7, L8 — düzeltme yalnızca ters kayıt ile yapılır.
 */
class LedgerReversalTest extends TestCase
{
    use RefreshDatabase;

    private LedgerService $ledger;

    private Company $company;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledger = app(LedgerService::class);
        $this->company = Company::factory()->create();
        $this->actor = User::factory()->create();
    }

    private function debit(string $amount = '1000.00', Currency $currency = Currency::TRY): LedgerEntry
    {
        return $this->ledger->debit(
            $this->company->id, Money::fromDecimal($amount), $currency,
            '2026-03-15', 'Konaklama bedeli', 'stay', 42, $this->actor,
        );
    }

    private function balance(Currency $currency = Currency::TRY): string
    {
        return $this->ledger->balanceForCompany($this->company->id, $currency)->toDecimal();
    }

    #[Test]
    public function ters_kayit_orijinalin_tam_tersini_uretir(): void
    {
        $original = $this->debit('1000.00');

        $reversal = $this->ledger->reverse($original, actor: $this->actor);

        $this->assertSame('0.00', $reversal->debit);
        $this->assertSame('1000.00', $reversal->credit);
        $this->assertSame($original->company_id, $reversal->company_id);
        $this->assertSame($original->currency, $reversal->currency);
        $this->assertSame($original->entry_date->toDateString(), $reversal->entry_date->toDateString());
        $this->assertSame($original->source_type, $reversal->source_type);
        $this->assertSame($original->source_id, $reversal->source_id);
        $this->assertTrue($reversal->is_reversal);
        $this->assertSame($original->id, $reversal->reverses_entry_id);
        $this->assertStringStartsWith('[İPTAL]', $reversal->description);
    }

    #[Test]
    public function alacak_hareketinin_tersi_borctur(): void
    {
        $credit = $this->ledger->credit(
            $this->company->id, Money::fromDecimal('300.00'), Currency::TRY,
            now(), 'Tahsilat', 'payment', 7, $this->actor,
        );

        $reversal = $this->ledger->reverse($credit, actor: $this->actor);

        $this->assertSame('300.00', $reversal->debit);
        $this->assertSame('0.00', $reversal->credit);
    }

    #[Test]
    public function ters_kayit_bakiyeyi_eski_haline_dondurur(): void
    {
        $this->debit('1000.00');
        $credit = $this->ledger->credit(
            $this->company->id, Money::fromDecimal('300.00'), Currency::TRY,
            now(), 'Tahsilat', actor: $this->actor,
        );

        $this->assertSame('700.00', $this->balance());

        $this->ledger->reverse($credit, actor: $this->actor);

        $this->assertSame('1000.00', $this->balance(), 'İptal edilen tahsilat bakiyeyi geri almalı');
    }

    #[Test]
    public function tam_iptalde_bakiye_sifira_doner(): void
    {
        $entry = $this->debit('1000.00');

        $this->ledger->reverse($entry, actor: $this->actor);

        $this->assertSame('0.00', $this->balance());
        $this->assertSame(2, LedgerEntry::query()->count(), 'Orijinal kayıt silinmez, yanına ters kayıt eklenir');
    }

    /**
     * Tutar düzeltmesi: ters kayıt + yeni kayıt (CLAUDE.md §6/L7).
     */
    #[Test]
    public function tutar_duzeltmesi_ters_kayit_arti_yeni_kayit_ile_yapilir(): void
    {
        $original = $this->debit('1000.00');

        $this->ledger->reverse($original, actor: $this->actor);
        $this->ledger->debit(
            $this->company->id, Money::fromDecimal('1250.00'), Currency::TRY,
            '2026-03-15', 'Konaklama bedeli (düzeltildi)', 'stay', 42, $this->actor,
        );

        $this->assertSame('1250.00', $this->balance());
        $this->assertSame(3, LedgerEntry::query()->count());
    }

    #[Test]
    public function ayni_kayit_ikinci_kez_ters_kaydedilemez(): void
    {
        $entry = $this->debit();
        $this->ledger->reverse($entry, actor: $this->actor);

        try {
            $this->ledger->reverse($entry, actor: $this->actor);
            $this->fail('CannotReverseEntry bekleniyordu');
        } catch (CannotReverseEntry $e) {
            $this->assertStringContainsString('zaten ters kaydedilmiş', $e->getMessage());
        }

        $this->assertSame(2, LedgerEntry::query()->count());
        $this->assertSame('0.00', $this->balance());
    }

    #[Test]
    public function veritabani_tekil_kisiti_ikinci_ters_kaydi_engeller(): void
    {
        $entry = $this->debit();
        $this->ledger->reverse($entry, actor: $this->actor);

        // Servis kontrolünü atlayıp doğrudan ikinci ters kayıt yazmayı dene.
        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('ledger_entries')->insert([
            'company_id' => $this->company->id,
            'entry_date' => now()->toDateString(),
            'description' => 'İkinci ters kayıt',
            'debit' => '0.00',
            'credit' => '1000.00',
            'currency' => 'TRY',
            'is_reversal' => true,
            'reverses_entry_id' => $entry->id,
            'created_by' => $this->actor->id,
            'created_at' => now(),
        ]);
    }

    #[Test]
    public function ters_kaydin_tersi_alinamaz_zincir_olusmaz(): void
    {
        $entry = $this->debit();
        $reversal = $this->ledger->reverse($entry, actor: $this->actor);

        try {
            $this->ledger->reverse($reversal, actor: $this->actor);
            $this->fail('CannotReverseEntry bekleniyordu');
        } catch (CannotReverseEntry $e) {
            $this->assertStringContainsString('zaten bir ters kayıttır', $e->getMessage());
        }

        $this->assertSame(2, LedgerEntry::query()->count());
    }

    #[Test]
    public function ters_kayit_islemi_atomiktir_yarim_kayit_kalmaz(): void
    {
        $entry = $this->debit();

        // Denetim kaydı yazımı başarısız olursa ters kayıt da geri alınmalıdır.
        $this->app->instance(AuditLogger::class, new class extends AuditLogger
        {
            public function log(
                AuditEvent $event,
                ?Model $subject = null,
                ?array $oldValues = null,
                ?array $newValues = null,
                ?User $user = null,
                ?string $subjectLabel = null,
            ): AuditLog {
                throw new RuntimeException('Denetim kaydı yazılamadı');
            }
        });

        try {
            app(LedgerService::class)->reverse($entry, actor: $this->actor);
            $this->fail('İstisna bekleniyordu');
        } catch (RuntimeException $e) {
            $this->assertSame('Denetim kaydı yazılamadı', $e->getMessage());
        }

        $this->assertSame(1, LedgerEntry::query()->count(), 'Yarım ters kayıt kalmamalı');
        $this->assertFalse(app(LedgerService::class)->isReversed($entry->fresh()));
        $this->assertSame('1000.00', $this->balance(), 'Bakiye değişmemeli');
    }

    #[Test]
    public function dis_transaction_geri_alinirsa_ters_kayit_kalmaz(): void
    {
        $entry = $this->debit();

        try {
            DB::transaction(function () use ($entry): void {
                $this->ledger->reverse($entry, actor: $this->actor);

                throw new RuntimeException('Dış işlem iptal edildi');
            });
        } catch (RuntimeException) {
            // beklenen
        }

        $this->assertSame(1, LedgerEntry::query()->count());
        $this->assertSame('1000.00', $this->balance());
    }

    #[Test]
    public function ters_kayit_aciklamasi_ozellestirilebilir(): void
    {
        $entry = $this->debit();

        $reversal = $this->ledger->reverse($entry, 'Rezervasyon iptali — misafir gelmedi', $this->actor);

        $this->assertSame('Rezervasyon iptali — misafir gelmedi', $reversal->description);
    }

    #[Test]
    public function ters_kayit_farkli_para_biriminde_uretilmez(): void
    {
        $entry = $this->debit('500.00', Currency::EUR);

        $reversal = $this->ledger->reverse($entry, actor: $this->actor);

        $this->assertSame(Currency::EUR, $reversal->currency);
        $this->assertSame('0.00', $this->balance(Currency::EUR));
        $this->assertSame('0.00', $this->balance(Currency::TRY));
    }
}
