<?php

declare(strict_types=1);

namespace Tests\Feature\Statements;

use App\Models\User;
use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Companies\Models\Company;
use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
use App\Modules\Ledger\Enums\LedgerEntryType;
use App\Modules\Ledger\Services\LedgerService;
use App\Modules\Ledger\Services\LedgerStatementService;
use App\Modules\Ledger\Support\Statement;
use App\Modules\Ledger\Support\StatementCriteria;
use App\Modules\Payments\Services\PaymentService;
use App\Modules\Stays\Services\StayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cari ekstre: kronoloji, yürüyen bakiye, devreden bakiye ve para birimi
 * ayrımı. Ledger tek doğruluk kaynağıdır; burada hiçbir tutar saklanmaz.
 */
class StatementBalanceTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $actor;

    private LedgerStatementService $statements;

    private LedgerService $ledger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        $this->company = Company::factory()->create(['name' => 'Monarch']);
        $this->actor = $this->userWithRole(RoleName::Admin);
        $this->statements = app(LedgerStatementService::class);
        $this->ledger = app(LedgerService::class);
    }

    private function debit(string $amount, string $date, Currency $currency = Currency::TRY, string $description = 'Konaklama'): void
    {
        $this->ledger->debit(
            companyId: $this->company->id,
            amount: Money::fromDecimal($amount),
            currency: $currency,
            entryDate: $date,
            description: $description,
            sourceType: 'stay',
            sourceId: 1,
            actor: $this->actor,
        );
    }

    private function credit(string $amount, string $date, Currency $currency = Currency::TRY, string $description = 'Tahsilat'): void
    {
        $this->ledger->credit(
            companyId: $this->company->id,
            amount: Money::fromDecimal($amount),
            currency: $currency,
            entryDate: $date,
            description: $description,
            sourceType: 'payment',
            sourceId: 1,
            actor: $this->actor,
        );
    }

    private function statement(array $criteria = []): Statement
    {
        return $this->statements->forCompany($this->company->id, new StatementCriteria(...$criteria));
    }

    /** @return array<int, string> */
    private function runningBalances(Statement $statement): array
    {
        return array_map(fn ($row) => $row->runningBalance->toDecimal(), $statement->rows);
    }

    #[Test]
    public function hareketi_olmayan_firmanin_ekstresi_bostur(): void
    {
        $statement = $this->statement();

        $this->assertTrue($statement->isEmpty());
        $this->assertFalse($statement->hasSummaries());
        $this->assertSame([], $statement->summaries);
    }

    #[Test]
    public function konaklama_borcu_ekstrede_gorunur(): void
    {
        $this->debit('9000.00', '2026-09-01');

        $row = $this->statement()->rows[0];

        $this->assertSame(LedgerEntryType::Stay, $row->type());
        $this->assertSame('9000.00', $row->entry->debit);
        $this->assertSame('0.00', $row->entry->credit);
        $this->assertSame('9000.00', $row->runningBalance->toDecimal());
    }

    #[Test]
    public function tahsilat_alacagi_ekstrede_gorunur(): void
    {
        $this->credit('5000.00', '2026-09-02');

        $row = $this->statement()->rows[0];

        $this->assertSame(LedgerEntryType::Payment, $row->type());
        $this->assertSame('5000.00', $row->entry->credit);
        $this->assertSame('-5000.00', $row->runningBalance->toDecimal());
    }

    #[Test]
    public function ters_kayit_ekstrede_gizlenmez(): void
    {
        $this->credit('5000.00', '2026-09-02');
        $entry = $this->ledger->activeEntryForSource('payment', 1);
        $this->ledger->reverse($entry, actor: $this->actor);

        $statement = $this->statement();

        $this->assertCount(2, $statement->rows, 'Ters kayıt saklanmaz, satır olarak görünür');

        $reversal = $statement->rows[1];

        $this->assertSame(LedgerEntryType::Reversal, $reversal->type());
        $this->assertTrue($reversal->isReversal());
        $this->assertSame('5000.00', $reversal->entry->debit);
        $this->assertSame('0.00', $reversal->runningBalance->toDecimal(), 'Ters kayıt bakiyeyi sıfırlar');
    }

    /** İSTENEN SENARYO: 9.000 borç → 5.000 tahsilat → 2.000 borç. */
    #[Test]
    public function yuruyen_bakiye_try_dogru_hesaplanir(): void
    {
        $this->debit('9000.00', '2026-09-01');
        $this->credit('5000.00', '2026-09-02');
        $this->debit('2000.00', '2026-09-03');

        $this->assertSame(['9000.00', '4000.00', '6000.00'], $this->runningBalances($this->statement()));
    }

    #[Test]
    public function farkli_para_birimleri_ayri_yurur_ve_toplanmaz(): void
    {
        $this->debit('9000.00', '2026-09-01');
        $this->debit('500.00', '2026-09-01', Currency::EUR);
        $this->credit('5000.00', '2026-09-02');
        $this->debit('200.00', '2026-09-02', Currency::GBP);
        $this->debit('100.00', '2026-09-03', Currency::EUR);

        $statement = $this->statement();

        // Her satır YALNIZCA kendi para biriminin bakiyesini taşır.
        $this->assertSame(
            ['9000.00', '500.00', '4000.00', '200.00', '600.00'],
            $this->runningBalances($statement),
        );

        $this->assertSame('4000.00', $statement->summaries['TRY']->closing->toDecimal());
        $this->assertSame('600.00', $statement->summaries['EUR']->closing->toDecimal());
        $this->assertSame('200.00', $statement->summaries['GBP']->closing->toDecimal());
        $this->assertCount(3, $statement->summaries, 'Tek "toplam bakiye" üretilmez');
    }

    #[Test]
    public function yalnizca_hareketi_olan_para_birimleri_ozetlenir(): void
    {
        $this->debit('9000.00', '2026-09-01');

        $summaries = $this->statement()->summaries;

        $this->assertSame(['TRY'], array_keys($summaries));
    }

    #[Test]
    public function siralama_tarih_sonra_id_ile_deterministiktir(): void
    {
        // Aynı gün: orijinal, ters kayıt, yeni hareket — id sırasıyla görünmeli.
        $this->debit('9000.00', '2026-09-01', description: 'Orijinal');
        $entry = $this->ledger->activeEntryForSource('stay', 1);
        $this->ledger->reverse($entry, actor: $this->actor);
        $this->debit('9500.00', '2026-09-01', description: 'Düzeltilmiş');

        $statement = $this->statement();
        $ids = array_map(fn ($row) => $row->entry->id, $statement->rows);

        $this->assertSame($ids, array_values(array_filter($ids)), 'Sıra id ile deterministik olmalı');
        $sorted = $ids;
        sort($sorted);
        $this->assertSame($sorted, $ids);
        $this->assertSame(['9000.00', '0.00', '9500.00'], $this->runningBalances($statement));
    }

    #[Test]
    public function ters_siralamada_bakiye_kronolojik_dogrulukla_hesaplanir(): void
    {
        $this->debit('9000.00', '2026-09-01');
        $this->credit('5000.00', '2026-09-02');
        $this->debit('2000.00', '2026-09-03');

        $statement = $this->statement(['newestFirst' => true]);

        // Satırlar ters sırada ama her satırın bakiyesi kronolojik gerçeği taşır.
        $this->assertSame(['6000.00', '4000.00', '9000.00'], $this->runningBalances($statement));
        $this->assertSame('2026-09-03', $statement->rows[0]->entry->entry_date->toDateString());
    }

    // -------------------------------------------------------------- özetler

    #[Test]
    public function ozet_borc_alacak_ve_bakiyeyi_tasir(): void
    {
        $this->debit('9000.00', '2026-09-01');
        $this->debit('2000.00', '2026-09-03');
        $this->credit('5000.00', '2026-09-02');

        $summary = $this->statement()->summaries['TRY'];

        $this->assertSame('11000.00', $summary->totalDebit->toDecimal());
        $this->assertSame('5000.00', $summary->totalCredit->toDecimal());
        $this->assertSame('6000.00', $summary->closing->toDecimal());
        $this->assertSame('0.00', $summary->carryForward->toDecimal());
    }

    #[Test]
    public function pozitif_bakiye_firma_borclu_demektir(): void
    {
        $this->debit('9000.00', '2026-09-01');

        $summary = $this->statement()->summaries['TRY'];

        $this->assertSame('Firma bize borçlu', $summary->balanceLabel());
        $this->assertSame('danger', $summary->balanceTone());
    }

    #[Test]
    public function negatif_bakiye_fazla_tahsilat_demektir(): void
    {
        $this->debit('1000.00', '2026-09-01');
        $this->credit('3000.00', '2026-09-02');

        $summary = $this->statement()->summaries['TRY'];

        $this->assertSame('-2000.00', $summary->closing->toDecimal());
        $this->assertSame('Firma alacaklı (fazla tahsilat)', $summary->balanceLabel());
        $this->assertSame('success', $summary->balanceTone());
    }

    #[Test]
    public function sifir_bakiye_kapali_demektir(): void
    {
        $this->debit('5000.00', '2026-09-01');
        $this->credit('5000.00', '2026-09-02');

        $summary = $this->statement()->summaries['TRY'];

        $this->assertSame('0.00', $summary->closing->toDecimal());
        $this->assertSame('Kapalı', $summary->balanceLabel());
        $this->assertSame('neutral', $summary->balanceTone());
    }

    // --------------------------------------------------------- devreden bakiye

    /**
     * İSTENEN SENARYO: 01.09 9.000 borç, 02.09 5.000 tahsilat, 03.09 2.000 borç.
     * 02.09–03.09 filtresinde devreden 9.000 olmalı ve bakiye 4.000 → 6.000 gitmeli.
     */
    #[Test]
    public function tarih_filtresi_devreden_bakiyeden_devam_eder(): void
    {
        $this->debit('9000.00', '2026-09-01');
        $this->credit('5000.00', '2026-09-02');
        $this->debit('2000.00', '2026-09-03');

        $statement = $this->statement([
            'dateFrom' => Carbon::parse('2026-09-02'),
            'dateTo' => Carbon::parse('2026-09-03'),
        ]);

        $this->assertCount(2, $statement->rows);
        $this->assertSame('9000.00', $statement->summaries['TRY']->carryForward->toDecimal());
        $this->assertSame(['4000.00', '6000.00'], $this->runningBalances($statement));
        $this->assertSame('6000.00', $statement->summaries['TRY']->closing->toDecimal());
    }

    #[Test]
    public function devreden_bakiye_para_birimi_bazindadir(): void
    {
        $this->debit('9000.00', '2026-01-15');
        $this->debit('500.00', '2026-01-20', Currency::EUR);
        $this->credit('1000.00', '2026-02-10');
        $this->debit('100.00', '2026-02-12', Currency::EUR);

        $statement = $this->statement(['dateFrom' => Carbon::parse('2026-02-01')]);

        $this->assertSame('9000.00', $statement->summaries['TRY']->carryForward->toDecimal());
        $this->assertSame('500.00', $statement->summaries['EUR']->carryForward->toDecimal());
        $this->assertSame(['8000.00', '600.00'], $this->runningBalances($statement));
    }

    #[Test]
    public function tarih_filtresi_yoksa_devreden_bakiye_yoktur(): void
    {
        $this->debit('9000.00', '2026-09-01');

        $statement = $this->statement();

        $this->assertSame('0.00', $statement->summaries['TRY']->carryForward->toDecimal());
        $this->assertSame([], $statement->carryForwards());
    }

    #[Test]
    public function devreden_sifirsa_gosterilmez(): void
    {
        $this->debit('5000.00', '2026-01-10');
        $this->credit('5000.00', '2026-01-11');
        $this->debit('2000.00', '2026-02-05');

        $statement = $this->statement(['dateFrom' => Carbon::parse('2026-02-01')]);

        $this->assertSame([], $statement->carryForwards(), 'Sıfır devreden satırı kalabalık yapmaz');
        $this->assertSame(['2000.00'], $this->runningBalances($statement));
    }

    #[Test]
    public function bitis_tarihi_sonrasi_hareketler_disarida_kalir(): void
    {
        $this->debit('9000.00', '2026-09-01');
        $this->debit('2000.00', '2026-09-30');

        $statement = $this->statement(['dateTo' => Carbon::parse('2026-09-15')]);

        $this->assertCount(1, $statement->rows);
        $this->assertSame('9000.00', $statement->summaries['TRY']->closing->toDecimal());
    }

    // ------------------------------------------------------------- filtreler

    #[Test]
    public function para_birimi_filtresi_yalnizca_o_para_birimini_gosterir(): void
    {
        $this->debit('9000.00', '2026-09-01');
        $this->debit('500.00', '2026-09-01', Currency::EUR);

        $statement = $this->statement(['currency' => Currency::EUR]);

        $this->assertCount(1, $statement->rows);
        $this->assertSame(['EUR'], array_keys($statement->summaries));
        $this->assertSame('500.00', $statement->rows[0]->runningBalance->toDecimal());
    }

    /**
     * Tür filtresi yalnızca GÖRÜNEN satırları daraltır; bakiye kolonu defterin
     * tamamı üzerinden yürüyen gerçek bakiyeyi göstermeye devam eder.
     */
    #[Test]
    public function tur_filtresi_bakiyeyi_bozmaz(): void
    {
        $this->debit('9000.00', '2026-09-01');
        $this->credit('5000.00', '2026-09-02');
        $this->debit('2000.00', '2026-09-03');

        $statement = $this->statement(['type' => LedgerEntryType::Payment]);

        $this->assertCount(1, $statement->rows);
        $this->assertSame(LedgerEntryType::Payment, $statement->rows[0]->type());
        $this->assertSame('4000.00', $statement->rows[0]->runningBalance->toDecimal());
        // Özet defterin tamamını yansıtır.
        $this->assertSame('6000.00', $statement->summaries['TRY']->closing->toDecimal());
    }

    #[Test]
    public function aciklama_aramasi_satirlari_daraltir(): void
    {
        $this->debit('9000.00', '2026-09-01', description: 'Konaklama: Ahmet Yılmaz');
        $this->debit('2000.00', '2026-09-02', description: 'Konaklama: Mehmet Demir');

        $statement = $this->statement(['search' => 'ahmet']);

        $this->assertCount(1, $statement->rows);
        $this->assertStringContainsString('Ahmet', $statement->rows[0]->entry->description);
    }

    #[Test]
    public function ters_kayit_turu_ile_filtrelenebilir(): void
    {
        $this->debit('9000.00', '2026-09-01');
        $entry = $this->ledger->activeEntryForSource('stay', 1);
        $this->ledger->reverse($entry, actor: $this->actor);

        $statement = $this->statement(['type' => LedgerEntryType::Reversal]);

        $this->assertCount(1, $statement->rows);
        $this->assertTrue($statement->rows[0]->isReversal());
    }

    // --------------------------------------------------- gerçek modül akışı

    /** Gerçek Stay ve Payment servisleriyle uçtan uca. */
    #[Test]
    public function gercek_konaklama_ve_tahsilat_akisi_ekstrede_dogru_gorunur(): void
    {
        app(StayService::class)->create([
            'company_id' => $this->company->id,
            'guest_name' => 'John Smith',
            'check_in_date' => '2026-08-29',
            'check_out_date' => '2026-09-01',
            'pricing_mode' => 'total',
            'amount' => '9000.00',
            'currency' => 'TRY',
        ], $this->actor);

        $payment = app(PaymentService::class)->create([
            'company_id' => $this->company->id,
            'payment_date' => '2026-09-02',
            'amount' => '5000.00',
            'currency' => 'TRY',
            'payment_method' => 'bank_transfer',
        ], $this->actor);

        $statement = $this->statement();

        $this->assertSame(['9000.00', '4000.00'], $this->runningBalances($statement));
        $this->assertSame(LedgerEntryType::Stay, $statement->rows[0]->type());
        $this->assertSame(LedgerEntryType::Payment, $statement->rows[1]->type());

        app(PaymentService::class)->cancel($payment, 'Mükerrer', $this->actor);

        $afterCancel = $this->statement();

        $this->assertCount(3, $afterCancel->rows);
        $this->assertSame(['9000.00', '4000.00', '9000.00'], $this->runningBalances($afterCancel));
        $this->assertSame(LedgerEntryType::Reversal, $afterCancel->rows[2]->type());
    }
}
