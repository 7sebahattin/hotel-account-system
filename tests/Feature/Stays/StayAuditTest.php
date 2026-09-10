<?php

declare(strict_types=1);

namespace Tests\Feature\Stays;

use App\Models\User;
use App\Modules\AuditLog\Enums\AuditEvent;
use App\Modules\AuditLog\Models\AuditLog;
use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Companies\Models\Company;
use App\Modules\Ledger\Models\LedgerEntry;
use App\Modules\Stays\Models\Stay;
use App\Modules\Stays\Services\StayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StayAuditTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $actor;

    private StayService $stays;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        $this->company = Company::factory()->create();
        $this->actor = $this->userWithRole(RoleName::Admin, ['name' => 'Resepsiyon Şefi']);
        $this->stays = app(StayService::class);
        AuditLog::query()->delete();
    }

    private function makeStay(): Stay
    {
        return $this->stays->create([
            'company_id' => $this->company->id,
            'guest_name' => 'John Smith',
            'check_in_date' => '2026-09-07',
            'check_out_date' => '2026-09-10',
            'amount' => '9000.00',
            'currency' => 'TRY',
        ], $this->actor);
    }

    private function logFor(Stay $stay, AuditEvent $event): AuditLog
    {
        return AuditLog::query()
            ->where('auditable_type', 'stay')
            ->where('auditable_id', $stay->id)
            ->where('event', $event->value)
            ->latest('id')
            ->firstOrFail();
    }

    #[Test]
    public function olusturma_denetim_kaydina_yazilir(): void
    {
        $stay = $this->makeStay();
        $log = $this->logFor($stay, AuditEvent::Created);

        $this->assertSame($this->actor->id, $log->user_id);
        $this->assertSame('Resepsiyon Şefi', $log->user_name);
        $this->assertSame('John Smith', $log->new_values['guest_name']);
        $this->assertSame('9000.00', $log->new_values['amount']);
        $this->assertSame('TRY', $log->new_values['currency']);
        $this->assertSame(3, $log->new_values['nights']);
        $this->assertSame(LedgerEntry::query()->firstOrFail()->id, $log->new_values['ledger_entry_id']);
    }

    #[Test]
    public function finansal_olmayan_guncelleme_yalnizca_degisen_alani_yazar(): void
    {
        $stay = $this->makeStay();
        AuditLog::query()->delete();

        $this->stays->update($stay, [
            'company_id' => $this->company->id,
            'guest_name' => 'John A. Smith',
            'check_in_date' => '2026-09-07',
            'check_out_date' => '2026-09-10',
            'amount' => '9000.00',
            'currency' => 'TRY',
        ], $this->actor);

        $log = $this->logFor($stay, AuditEvent::Updated);

        $this->assertSame(['guest_name' => 'John A. Smith'], $log->new_values);
        $this->assertSame(['guest_name' => 'John Smith'], $log->old_values);
        $this->assertArrayNotHasKey('financial_change', $log->new_values);
    }

    /**
     * Finansal düzeltmenin denetim kaydı, zincirin tamamını izlenebilir kılmalı:
     * eski değerler, yeni değerler, ters kaydedilen hareket, ters kayıt ve yeni hareket.
     */
    #[Test]
    public function finansal_duzeltme_denetim_kaydi_ledger_zincirini_tasir(): void
    {
        $stay = $this->makeStay();
        $originalEntryId = LedgerEntry::query()->firstOrFail()->id;
        AuditLog::query()->delete();

        $this->stays->update($stay, [
            'company_id' => $this->company->id,
            'guest_name' => 'John Smith',
            'check_in_date' => '2026-09-07',
            'check_out_date' => '2026-09-10',
            'amount' => '9500.00',
            'currency' => 'TRY',
        ], $this->actor);

        $log = $this->logFor($stay, AuditEvent::Updated);

        $reversal = LedgerEntry::query()->where('is_reversal', true)->firstOrFail();
        $newEntry = LedgerEntry::query()->where('debit', '9500.00')->firstOrFail();

        $this->assertSame('9000.00', $log->old_values['amount'], 'Eski finansal değer');
        $this->assertSame('9500.00', $log->new_values['amount'], 'Yeni finansal değer');
        $this->assertTrue($log->new_values['financial_change']);
        $this->assertSame($originalEntryId, $log->new_values['reversed_ledger_entry_id']);
        $this->assertSame($reversal->id, $log->new_values['reversal_ledger_entry_id']);
        $this->assertSame($newEntry->id, $log->new_values['new_ledger_entry_id']);
    }

    #[Test]
    public function tarih_duzeltmesinde_gece_sayisi_da_denetim_kaydina_girer(): void
    {
        $stay = $this->makeStay();
        AuditLog::query()->delete();

        $this->stays->update($stay, [
            'company_id' => $this->company->id,
            'guest_name' => 'John Smith',
            'check_in_date' => '2026-09-07',
            'check_out_date' => '2026-09-12',
            'amount' => '9000.00',
            'currency' => 'TRY',
        ], $this->actor);

        $log = $this->logFor($stay, AuditEvent::Updated);

        $this->assertSame(3, $log->old_values['nights']);
        $this->assertSame(5, $log->new_values['nights']);
        $this->assertSame('2026-09-12', $log->new_values['check_out_date']);
        $this->assertTrue($log->new_values['financial_change']);
    }

    #[Test]
    public function iptal_denetim_kaydina_ters_kayit_referansiyla_yazilir(): void
    {
        $stay = $this->makeStay();
        $originalEntryId = LedgerEntry::query()->firstOrFail()->id;
        AuditLog::query()->delete();

        $this->stays->cancel($stay, $this->actor);

        $log = $this->logFor($stay, AuditEvent::Cancelled);
        $reversal = LedgerEntry::query()->where('is_reversal', true)->firstOrFail();

        $this->assertSame('active', $log->old_values['status']);
        $this->assertSame('cancelled', $log->new_values['status']);
        $this->assertSame($originalEntryId, $log->new_values['reversed_ledger_entry_id']);
        $this->assertSame($reversal->id, $log->new_values['reversal_ledger_entry_id']);
    }

    #[Test]
    public function hicbir_alan_degismezse_denetim_kaydi_acilmaz(): void
    {
        $stay = $this->makeStay();
        AuditLog::query()->delete();

        $this->stays->update($stay, [
            'company_id' => $this->company->id,
            'guest_name' => 'John Smith',
            'check_in_date' => '2026-09-07',
            'check_out_date' => '2026-09-10',
            'amount' => '9000.00',
            'currency' => 'TRY',
        ], $this->actor);

        $this->assertSame(0, AuditLog::query()->where('auditable_type', 'stay')->count());
    }

    #[Test]
    public function konaklama_ve_cari_hareketi_ayri_ayri_denetlenir(): void
    {
        $stay = $this->makeStay();

        $this->assertSame(1, AuditLog::query()->where('auditable_type', 'stay')->count());
        $this->assertSame(1, AuditLog::query()->where('auditable_type', 'ledger_entry')->count());
        $this->assertSame($stay->id, AuditLog::query()->where('auditable_type', 'stay')->firstOrFail()->auditable_id);
    }
}
